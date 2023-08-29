<?php
// Version 4.2.3

// Constants for Gravity Forms field IDs
define('SBMA_FIELD_ID_METHOD_OF_DELIVERY', 13);
define('SBMA_FIELD_ID_COURSE_NAME', 11);
define('SBMA_FIELD_ID_COURSE_ID', 7);
define('SBMA_FIELD_ID_FIRST_NAME', 4.3);
define('SBMA_FIELD_ID_LAST_NAME', 4.6);
define('SBMA_FIELD_ID_COMPANY', 5);
define('SBMA_FIELD_ID_USER_ID', 8);
define('SBMA_FIELD_ID_EMAIL', 17);

add_filter('gform_pre_render_11', 'sbma_populate_fields');
add_filter('gform_validation_11', 'sbma_prevent_duplicate_entries');
add_action('gform_after_submission_11', 'sbma_mark_course_as_complete_redirect', 10, 2);

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
		$fieldId = $field->id;
		$urlValue = $params["field_$fieldId"] ?? null;
		$courseNameFromUrl = $params['lms_course_name'] ?? null;
		$courseIdFromUrl = $params['lms_course_id'] ? (int)$params['lms_course_id'] : null;
		$modFromUrl = $params['lms_mod'] ?? null;

		if ($fieldId == SBMA_FIELD_ID_METHOD_OF_DELIVERY) {
			if ($isLoggedIn && !$isSfwdPage && $modFromUrl) {
				$field->defaultValue = $modMappings[$modFromUrl] ?? $modFromUrl;
			} elseif ($isLoggedIn && !$isSfwdPage) {
				$field->defaultValue = 'lonl';
			} elseif (!$isLoggedIn && !$isSfwdPage && $modFromUrl) {
				$field->defaultValue = $modMappings[$modFromUrl] ?? $modFromUrl;
			} elseif (!$isLoggedIn && !$isSfwdPage) {
				$field->defaultValue = 'lons';
			} elseif ($isLoggedIn && $isSfwdPage) {
				$field->defaultValue = 'spo';
			}
		}

		if (($fieldId == SBMA_FIELD_ID_COURSE_NAME || $fieldId == SBMA_FIELD_ID_COURSE_ID) && (!$isLoggedIn || ($isLoggedIn && !$isSfwdPage))) {
			$urlCourseNameValid = isset($courseMappings[$courseNameFromUrl]);
			$urlCourseIdValid = in_array($courseIdFromUrl, $courseMappings);

			if ($urlCourseNameValid) {
				$field->defaultValue = $fieldId == SBMA_FIELD_ID_COURSE_NAME ? $courseNameFromUrl : $courseMappings[$courseNameFromUrl];
			} elseif ($urlCourseIdValid) {
				$field->defaultValue = $fieldId == SBMA_FIELD_ID_COURSE_NAME ? array_search($courseIdFromUrl, $courseMappings) : $courseIdFromUrl;
			}
		}

		if ($isLoggedIn && !$isSfwdPage) {
			if ($fieldId == SBMA_FIELD_ID_FIRST_NAME) {
				$field->defaultValue = $currentUser->user_firstname;
			}
			if ($fieldId == SBMA_FIELD_ID_LAST_NAME) {
				$field->defaultValue = $currentUser->user_lastname;
			}
			if ($fieldId == SBMA_FIELD_ID_COMPANY) {
				$field->defaultValue = get_user_meta($currentUser->ID, 'billing_company', true);
			}
			if ($fieldId == SBMA_FIELD_ID_USER_ID) {
				$field->defaultValue = $currentUser->ID;
			}
			if ($fieldId == SBMA_FIELD_ID_EMAIL) {
				$field->defaultValue = $currentUser->user_email;
			}
		}

		if ($isSfwdPage && $isLoggedIn) {
			if ($fieldId == SBMA_FIELD_ID_COURSE_ID) {
				$field->defaultValue = learndash_get_course_id();
			}
			if ($fieldId == SBMA_FIELD_ID_COURSE_NAME) {
				$field->defaultValue = get_the_title(learndash_get_course_id());
			}
		}
	}

	return $form;
}

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

function sbma_mark_course_as_complete_redirect($entry, $form) {
	if ($form['id'] != 11) return;

	$isLoggedIn = is_user_logged_in();
	$postType = get_post_type();
	$isSfwdPage = strpos($postType, 'sfwd') !== false;
	$courseId = rgar($entry, SBMA_FIELD_ID_COURSE_ID);

	if ($isLoggedIn && $isSfwdPage && $courseId) {
		ld_update_course_access(get_current_user_id(), $courseId, true);
		wp_redirect(get_permalink($courseId));
		exit;
	}
}
