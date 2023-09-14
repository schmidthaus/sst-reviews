<?php
// Version 4.2.8

// Constants for Gravity Form and field IDs
define('SBMA_GRAVITY_FORM', 11);
define('SBMA_FIELD_ID_METHOD_OF_DELIVERY', 13);
define('SBMA_FIELD_ID_COURSE_NAME', 11);
define('SBMA_FIELD_ID_COURSE_ID', 7);
define('SBMA_FIELD_ID_FIRST_NAME', 4.3);
define('SBMA_FIELD_ID_LAST_NAME', 4.6);
define('SBMA_FIELD_ID_COMPANY', 5);
define('SBMA_FIELD_ID_USER_ID', 8);
define('SBMA_FIELD_ID_EMAIL', 17);

// Add filters and actions
add_filter('gform_pre_render_'.SBMA_GRAVITY_FORM, 'sbma_populate_fields');
add_filter('gform_validation_'.SBMA_GRAVITY_FORM, 'sbma_prevent_duplicate_entries');
add_action('gform_after_submission_'.SBMA_GRAVITY_FORM, 'sbma_mark_course_as_complete_redirect', 10, 2);

/**
 * Populate Gravity Form fields with derived or default values.
 *
 * @param array $form The form object.
 * @return array The modified form object.
 */
 function sbma_populate_fields($form) {
	 if ($form['id'] != SBMA_GRAVITY_FORM) return $form;
	 
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
 
		 // Conditions for processing URL parameters
		 if (!($isLoggedIn && $isSfwdPage) || ($isLoggedIn && !$isSfwdPage)) {
			 $url_value = isset($params["field_$field_id"]) ? $params["field_$field_id"] : null;
			 $course_name_from_url = isset($params['lms_course_name']) ? $params['lms_course_name'] : null;
			 $course_id_from_url = isset($params['lms_course_id']) ? (int) $params['lms_course_id'] : null;
			 $mod_from_url = isset($params['lms_mod']) ? $params['lms_mod'] : null;
 
			 // Field 13: Method of Delivery
			 if ($field_id == SBMA_FIELD_ID_METHOD_OF_DELIVERY) {
				 $mod_value = $modMappings[$mod_from_url] ?? $mod_from_url;
				 $field->defaultValue = $mod_value;
			 }
 
			 // Fields 11 and 7: Course Name and Course ID
			 if ($field_id == SBMA_FIELD_ID_COURSE_NAME || $field_id == SBMA_FIELD_ID_COURSE_ID) {
				 $url_course_name_valid = isset($courseMappings[$course_name_from_url]);
				 $url_course_id_valid = in_array($course_id_from_url, $courseMappings);
 
				 // Set lms_course_name from lms_course_id if lms_course_name is missing
				 if (!$url_course_name_valid && $url_course_id_valid) {
					 $course_name_from_url = array_search($course_id_from_url, $courseMappings);
				 }
 
				 if ($url_course_name_valid && $url_course_id_valid && $courseMappings[$course_name_from_url] === $course_id_from_url) {
					 $field->defaultValue = $field_id == SBMA_FIELD_ID_COURSE_NAME ? $course_name_from_url : $course_id_from_url;
				 } elseif ($url_course_name_valid) {
					 $field->defaultValue = $field_id == SBMA_FIELD_ID_COURSE_NAME ? $course_name_from_url : $courseMappings[$course_name_from_url];
				 } elseif ($url_course_id_valid) {
					 $field->defaultValue = $field_id == SBMA_FIELD_ID_COURSE_NAME ? array_search($course_id_from_url, $courseMappings) : $course_id_from_url;
				 }
			 }
		 }
 
		 // Continue processing fields based on existing logic
		 if ($isLoggedIn && $isSfwdPage) {
			 if ($field_id == SBMA_FIELD_ID_COURSE_NAME) {
				 // Set the course name based on the current LearnDash course
				 $field->defaultValue = get_the_title();
			 }
			 if ($field_id == SBMA_FIELD_ID_COURSE_ID) {
				 $field->defaultValue = learndash_get_course_id();
			 }
			 if ($field_id == SBMA_FIELD_ID_METHOD_OF_DELIVERY) {
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
	if ($form['id'] != SBMA_GRAVITY_FORM) return $validationResult;
	
	global $wpdb;
	$isLoggedIn = is_user_logged_in();
	$currentUser = $isLoggedIn ? wp_get_current_user() : null;
	$email = rgpost('input_'.SBMA_FIELD_ID_EMAIL);
	$courseId = rgpost('input_'.SBMA_FIELD_ID_COURSE_ID);
	$mod = rgpost('input_'.SBMA_FIELD_ID_METHOD_OF_DELIVERY);
	
	$where = $isLoggedIn ? $wpdb->prepare("meta_key = %s AND meta_value = %s", '_gform-entry-user-id', $currentUser->ID) : $wpdb->prepare("meta_key = %s AND meta_value = %s", SBMA_FIELD_ID_EMAIL, $email);
	$where .= $wpdb->prepare(" AND meta_key = %s AND meta_value = %s", SBMA_FIELD_ID_COURSE_ID, $courseId);
	$where .= $wpdb->prepare(" AND meta_key = %s AND meta_value = %s", SBMA_FIELD_ID_METHOD_OF_DELIVERY, $mod);
	
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
 * Redirect user after form submission.
 *
 * @param array $entry The entry object.
 * @param array $form The form object.
 */
function sbma_mark_course_as_complete_redirect($entry, $form) {
	if ($form['id'] != SBMA_GRAVITY_FORM) return;
	
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
