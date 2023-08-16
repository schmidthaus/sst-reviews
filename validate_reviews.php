<?php


add_filter('gform_pre_submission_filter_11', 'sbma_validate_reviews_v1_12');
function sbma_validate_reviews_v1_12($form)
{
	// Sanitize form input
	$lms_mod = filter_input(INPUT_GET, 'lms_mod', FILTER_SANITIZE_STRING);
	$lms_course_name = filter_input(INPUT_GET, 'lms_course_name', FILTER_SANITIZE_STRING);
	$lms_course_id = filter_input(INPUT_GET, 'lms_course_id', FILTER_SANITIZE_NUMBER_INT);

	// Log sanitized inputs
	error_log("Sanitized inputs: lms_mod=$lms_mod, lms_course_name=$lms_course_name, lms_course_id=$lms_course_id");

	// Define Course Name to Course ID mapping
	$course_mappings = array(
		"MS Excel Beginner Course" => 909,
		"MS Excel Intermediate Course" => 1221,
		"MS Excel Advanced Course" => 1548,
		"MS Excel Automation Course" => 1920,
		"MS Excel Foundation Course" => 6606,
		"MS Outlook Foundation Course" => 6248,
		"MS Windows Foundation Course" => 5349
	);

	// Log course mappings
	error_log('Course mappings: ' . print_r($course_mappings, true));

	// Get the post ID/post type/user object
	$page_id = get_the_ID();
	$post_type = get_post_type();
	$user = wp_get_current_user();

	// Log the post ID/post type/user object
	error_log("Page ID=$page_id, Post Type=$post_type, User ID=" . ($user ? $user->ID : 'null'));

	// Loop through form fields
	foreach ($form['fields'] as &$field) {
		$field_value = '';

		// Check if user is logged in and set the User ID
		if ($field->id == 8 && is_user_logged_in()) {
			$field_value = $user->ID;
		}

		// Check if user is logged in and post type contains 'sfwd' and 'lms_mod' is not set
		if ($field->id == 13) {
			// ...rest of the logic...
			$field->defaultValue = $field_value;
		}

		// Log field ID and its default value
		error_log("Field ID=$field->id, defaultValue=$field->defaultValue");

		// Validate and correct lms_course_id and lms_course_name
		if ($field->id == 11) {
			// ...rest of the logic...
			$field_value = $lms_course_name;
		}

		if ($field->id == 7) {
			// ...rest of the logic...
			$field_value = $lms_course_id;
		}

		// Log field ID and its value
		error_log("Field ID=$field->id, value=$field_value");

		// Store the sanitized and validated values back to the $_POST array only if field value is not empty
		if (!empty($field_value)) {
			$_POST["input_{$field->id}"] = $field_value;
		}
	}

	return $form;
}
