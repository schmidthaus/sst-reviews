<?php
// Version 4.2.6

// Constants for Gravity Forms field IDs
define('SBMA_FIELD_ID_METHOD_OF_DELIVERY', 13);
define('SBMA_FIELD_ID_COURSE_NAME', 11);
define('SBMA_FIELD_ID_COURSE_ID', 7);
define('SBMA_FIELD_ID_FIRST_NAME', 4.3);
define('SBMA_FIELD_ID_LAST_NAME', 4.6);
define('SBMA_FIELD_ID_COMPANY', 5);
define('SBMA_FIELD_ID_USER_ID', 8);
define('SBMA_FIELD_ID_EMAIL', 17);

// Add filters and actions
add_filter('gform_pre_render_11', 'sbma_populate_fields');
add_filter('gform_validation_11', 'sbma_prevent_duplicate_entries');
add_action('gform_after_submission_11', 'sbma_mark_course_as_complete_redirect', 10, 2);

/**
 * Populate Gravity Form fields with default values.
 *
 * @param array $form The form object.
 * @return array The modified form object.
 */
function sbma_populate_fields($form) {
	if ($form['id'] != 11) return $form;
	
	$params = array_map('sanitize_text_field', $_GET);
	$isLoggedIn = is_user_logged_in();
	$currentUser = $isLoggedIn ? wp_get_current_user() : null;
	$postType = get_post_type();
	$isSfwdPage = strpos($postType, 'sfwd') !== false;
	
	$courseMappings = [
		"MS Excel Beginner Course" => 909,
		"MS Excel Intermediate Course" => 1221,
		"MS Excel Advanced Course" => 1548,
		"MS Excel Automation Course" => 1920,
		"MS Excel Foundation Course" => 6606,
		"MS Outlook Foundation Course" => 6248,
		"MS Windows Foundation Course" => 5349
	];
	
	$modMappings = [
		"Self-paced Online" => "spo",
		"Live Online" => "lonl",
		"Live Onsite" => "lons"
	];
	
	foreach ($form['fields'] as &$field) {
		$field_id = $field->id;
	
		$url_value = isset($params["field_$field_id"]) ? $params["field_$field_id"] : null;
		$course_name_from_url = isset($params['lms_course_name']) ? $params['lms_course_name'] : null;
		$course_id_from_url = isset($params['lms_course_id']) ? (int) $params['lms_course_id'] : null;
		$mod_from_url = isset($params['lms_mod']) ? $params['lms_mod'] : null;
	
		// Field 13: Method of Delivery
		if ($field_id == 13) {
			$mod_value = $mod_mappings[$mod_from_url] ?? $mod_from_url;
			$field->defaultValue = $mod_value;
		}
	
		// Fields 11 and 7: Course Name and Course ID
		if ($field_id == 11 || $field_id == 7) {
			$url_course_name_valid = isset($course_mappings[$course_name_from_url]);
			$url_course_id_valid = in_array($course_id_from_url, $course_mappings);
	
			if ($url_course_name_valid && $url_course_id_valid && $course_mappings[$course_name_from_url] === $course_id_from_url) {
				$field->defaultValue = $field_id == 11 ? $course_name_from_url : $course_id_from_url;
			} elseif ($url_course_name_valid) {
				$field->defaultValue = $field_id == 11 ? $course_name_from_url : $course_mappings[$course_name_from_url];
			} elseif ($url_course_id_valid) {
				$field->defaultValue = $field_id == 11 ? array_search($course_id_from_url, $course_mappings) : $course_id_from_url;
			}
		}
	
		// Other fields when not on 'sfwd' pages
		if (!$contains_sfwd) {
			if ($is_logged_in) {
				if ($field_id == 4.3) { // First Name field
					$field->defaultValue = $current_user->user_firstname;
				}
				if ($field_id == 4.6) { // Last Name field
					$field->defaultValue = $current_user->user_lastname;
				}
				if ($field_id == 5) {// Company field
					$field->defaultValue = get_user_meta($current_user->ID, 'billing_company', true);
				}
				if ($field_id == 8) { // User ID field
					$field->defaultValue = $current_user->ID;
				}
				if ($field_id == 17) { // Email field
					$field->defaultValue = $current_user->user_email;
				}
			}
	
			if ($url_value !== null) {
				$field->defaultValue = $url_value;
			}
		}
	
		// When on 'sfwd' pages
		if ($contains_sfwd && $is_logged_in) {
			if ($field_id == 7) { // Course ID field
				$field->defaultValue = learndash_get_course_id();
			}
			if ($field_id == 11) { // Course ID field
				$field->defaultValue = get_the_title(learndash_get_course_id());
			}
			if ($field_id == 13) { // Course MOD field
				$field->defaultValue = 'spo';
			}
		}
	}
	
	return $form;
}

/**
 * Prevent duplicate entries in Gravity Forms.
 *
 * @param array $validationResult The validation result object.
 * @return array The modified validation result object.
 */
function sbma_prevent_duplicate_entries($validationResult) {
	$form = $validationResult['form'];
	if ($form['id'] != 11) return $validationResult;
	
	global $wpdb;
	$isLoggedIn = is_user_logged_in();
	$currentUser = $isLoggedIn ? wp_get_current_user() : null;
	$email = rgpost('input_17');
	$courseId = rgpost('input_7');
	$mod = rgpost('input_13');
	
	$where = $isLoggedIn ? $wpdb->prepare("meta_key = %s AND meta_value = %s", '_gform-entry-user-id', $currentUser->ID) : $wpdb->prepare("meta_key = %s AND meta_value = %s", '17', $email);
	$where .= $wpdb->prepare(" AND meta_key = %s AND meta_value = %s", '7', $courseId);
	$where .= $wpdb->prepare(" AND meta_key = %s AND meta_value = %s", '13', $mod);
	
	$query = "SELECT COUNT(*) FROM {$wpdb->prefix}gf_entry_meta WHERE {$where}";
	$count = $wpdb->get_var($query);
	
	if ($count > 0) {
		$validationResult['is_valid'] = false;
		$form['confirmation']['message'] = '<h4>Thank you, you have already sent in a review for this course. No need to resend.</h4>';
	}
	
	$validationResult['form'] = $form;
	return $validationResult;
}

/**
 * Mark Course Complete.
 * Redirect user to Course completion page after form submission.
 *
 * @param array $entry The entry object.
 * @param array $form The form object.
 */
function sbma_mark_course_as_complete_redirect($entry, $form) {
	if ($form['id'] != 11) return;
	
	$isLoggedIn = is_user_logged_in();
	$postType = get_post_type();
	$isSfwdPage = strpos($postType, 'sfwd') !== false;
	$courseId = rgar($entry, SBMA_FIELD_ID_COURSE_ID);

	// Additional logic from v4.2.3
	if ($is_logged_in && $contains_sfwd) {
		$user_id = get_current_user_id();
		$lesson_id = get_the_ID(); // Get LearnDash lesson ID

		if ($lesson_id) {
			// Mark lesson as complete for the user
			if (function_exists('learndash_process_mark_complete')) {
				learndash_process_mark_complete($user_id, $lesson_id);
				error_log("Marked lesson " . $lesson_id . " as complete for user: " . $user_id);
				$course_id = learndash_get_course_id(get_the_ID());
				$completion_url = get_permalink($course_id); // Build the course completion URL

				// Set the confirmation to redirect to the course completion URL
				$confirmation = array(
					'redirect' => $completion_url,
				);
				return $confirmation;
			} else {
				error_log("learndash_process_mark_complete function not available");
			}
		}
	}
}
