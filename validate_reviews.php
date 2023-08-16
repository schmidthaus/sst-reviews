<?php
/**
 * Gravity Forms pre-submission filter for form ID 11.
 * Version: 1.14
 *
 * This function performs the following actions:
 * 1. Sanitizes the URL input parameters (lms_mod, lms_course_name, and lms_course_id).
 * 2. Defines a mapping of course names to course IDs.
 * 3. Retrieves the page ID, post type, and current user.
 * 4. Iterates through the form fields to set appropriate default values, validate, and correct input parameters.
 * 5. Returns the modified form.
 *
 * @param array $form The Gravity Forms form object.
 * @return array The modified form object.
 */
function sbma_validate_reviews($form)
{
	// Define field IDs
	$FIELD_LMS_COURSE_ID = 7; // Field ID 7 = Course ID
	$FIELD_USER_ID = 8; // Field ID 8 = User ID
	$FIELD_LMS_COURSE_NAME = 11; // Field ID 11 = Course Completed
	$FIELD_LMS_MOD = 13; // Field ID 13 = Method of Delivery

	// Sanitize form input
	$lms_mod = trim(filter_input(INPUT_GET, 'lms_mod', FILTER_SANITIZE_STRING));
	$lms_mod = in_array($lms_mod, ['spo', 'lonl', 'lons']) ? $lms_mod : 'lons'; // Validate lms_mod
	$lms_course_name = trim(urldecode(filter_input(INPUT_GET, 'lms_course_name', FILTER_SANITIZE_STRING)));
	$lms_course_id = trim(filter_input(INPUT_GET, 'lms_course_id', FILTER_SANITIZE_NUMBER_INT));

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

	// Get the post ID/post type/user object
	$page_id = get_the_ID();
	$post_type = get_post_type();
	$user = wp_get_current_user();

	// Loop through form fields
	foreach ($form['fields'] as &$field) {
		$field_value = '';

		// Check if user is logged in and set the User ID
		if ($field->id == $FIELD_USER_ID && is_user_logged_in()) {
			$field_value = $user->ID;
		}

		// Handle lms_mod
		if ($field->id == $FIELD_LMS_MOD) {
			$field_value = $lms_mod;
		}

		// Validate and correct lms_course_id and lms_course_name
		if ($field->id == $FIELD_LMS_COURSE_NAME) {
			if (!empty($lms_course_name) && isset($course_mappings[$lms_course_name])) {
				$field_value = $lms_course_name; // Set the sanitized lms_course_name
			} elseif (!empty($lms_mod)) {
				// Set lms_course_name based on lms_mod if it's missing
				$field_value = array_search($lms_mod, $course_mappings);
			}
		}

		if ($field->id == $FIELD_LMS_COURSE_ID) {
			if (!empty($lms_course_name) && isset($course_mappings[$lms_course_name])) {
				$field_value = $course_mappings[$lms_course_name]; // Correct lms_course_id based on lms_course_name
			} elseif (!empty($lms_mod)) {
				// Set lms_course_id based on lms_mod if it's missing
				$field_value = $lms_mod;
			}
		}

		// Handle missing combinations of lms_course_name & lms_course_id
		if (empty($lms_course_name) && empty($lms_course_id) && !is_user_logged_in() && strpos($post_type, 'sfwd') === false) {
			$field_value = "MS Excel Beginner Course";
			$_POST["input_{$FIELD_LMS_COURSE_ID}"] = 909; // Set Course ID
		}

		// Store the sanitized and validated values back to the $_POST array only if field value is not empty
		if (!empty($field_value)) {
			$_POST["input_{$field->id}"] = $field_value;
		}
	}

	return $form;
}

// Hook the function to Gravity Forms pre-submission filter for form ID 11
add_filter('gform_pre_submission_filter_11', 'sbma_validate_reviews');
