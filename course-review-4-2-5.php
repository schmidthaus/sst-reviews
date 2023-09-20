<?php
// Version 4.3.3

// Constants for Gravity Form and field IDs
define("SBMA_GRAVITY_FORM", 11);
define("SBMA_FIELD_ID_METHOD_OF_DELIVERY", 13);
define("SBMA_FIELD_ID_COURSE_NAME", 11);
define("SBMA_FIELD_ID_COURSE_ID", 7);
define("SBMA_FIELD_ID_FIRST_NAME", 4.3);
define("SBMA_FIELD_ID_LAST_NAME", 4.6);
define("SBMA_FIELD_ID_COMPANY", 5);
define("SBMA_FIELD_ID_USER_ID", 8);
define("SBMA_FIELD_ID_EMAIL", 17);
define("SBMA_FIELD_IS_DUPLICATE", 19);

// Add filters and actions
add_filter("gform_pre_render_" . SBMA_GRAVITY_FORM, "sbma_populate_fields");
add_action("gform_validation_" . SBMA_GRAVITY_FORM, 'sbma_prevent_duplicate_entries');
add_action(
	"gform_after_submission_" . SBMA_GRAVITY_FORM,
	"sbma_mark_course_as_complete_redirect",
	10,
	2
);
add_filter("gform_confirmation_" . SBMA_GRAVITY_FORM, 'sbma_duplicate_custom_confirmation', 10, 4);

/**
 * Utility to check if Wordpress user
 * is logged in.
 *
 * @return true (if the user is logged in) or false (if the user is not logged in).
 */
function sbma_is_user_logged_in() {
	if (function_exists('is_user_logged_in')) {
		// The is_user_logged_in() function is available
		error_log("The is_user_logged_in() function is available.");
		$isLoggedIn = (bool) is_user_logged_in();
	} else {
		// The is_user_logged_in() function is not available
		// Use alternative method to determine $isLoggedIn
		error_log("The is_user_logged_in() function is not available. Use alternative method.");
	
		// Check if a user is logged in using the global $current_user object
		global $current_user;
		get_currentuserinfo();
	
		$isLoggedIn = ($current_user->ID > 0) ? true : false;
	}
	return $isLoggedIn;
}

/**
 * Conditionally populate Gravity Form fields
 * with URL Parameters, derived or default values.
 *
 * @param array $form The form object.
 * @return array The modified form object.
 */
function sbma_populate_fields($form)
{
	if ($form["id"] != SBMA_GRAVITY_FORM) {
		return $form;
	}
	
	// Server-side logging
	error_log("Entering sbma_populate_fields function.");

	$params = array_map("sanitize_text_field", $_GET);
	$postType = get_post_type();
	$isSfwdPage = strpos($postType, "sfwd") !== false;
	$isLoggedIn = sbma_is_user_logged_in();

	// Now $isLoggedIn contains the appropriate value
	$current_user = null;
	$currentUser = $isLoggedIn ? wp_get_current_user() : null;
		
	error_log("URL Parameters: " .json_encode($params));
	error_log("Post Type: $postType");
	error_log("Is LearnDash page: $isSfwdPage");
	error_log("Is User Logged In: $isLoggedIn");
	//error_log("Current User ID: $currentUser");

	// Populate the $courseMappings array with published courses and course id's
	$learndash_course_args = [
		"post_type" => "sfwd-courses",
		"post_status" => "publish",
		"posts_per_page" => -1,
	];

	$courses = get_posts($learndash_course_args);
	$courseMappings = [];

	foreach ($courses as $course) {
		$courseMappings[$course->post_title] = $course->ID;
	}
	error_log("LearnDash Course ←→ ID array: " . json_encode($courseMappings));

	$modMappings = [
		"Self-paced Online" => "spo",
		"Live Online" => "lonl",
		"Live Onsite" => "lons",
	];
	
	try {
		foreach ($form["fields"] as &$field) {
			$field_id = $field->id;
	
			// If user is NOT on a LearnDash page, process URL parameters
			if ((!$isLoggedIn || $isLoggedIn) && !$isSfwdPage) {
				error_log("User is NOT on a LearnDash page, process URL parameters.");
				$url_value = isset($params["field_$field_id"])
					? $params["field_$field_id"]
					: null;
				$course_name_from_url = isset($params["lms_course_name"])
					? $params["lms_course_name"]
					: null;
				$course_id_from_url = isset($params["lms_course_id"])
					? (int) $params["lms_course_id"]
					: null;
				$mod_from_url = isset($params["lms_mod"])
					? $params["lms_mod"]
					: null;
	
				// Field 13: Method of Delivery
				// TASK What to do if incorrect (non array value passed)?
				if ($field_id == 13) {
					error_log("Field 13: Method of Delivery.");
					if (isset($modMappings[$mod_from_url])) {
						$field->defaultValue = $modMappings[$mod_from_url];
					} elseif ($isLoggedIn) {
						$field->defaultValue = "lonl";
					} else {
						$field->defaultValue = "lons";
					}
				}
	
				// Fields 11 and 7: Course Name and Course ID
				if ($field_id == 11 || $field_id == 7) {
					error_log("Fields 11 and 7: Course Name and Course ID.");
					$url_course_name_valid = isset(
						$courseMappings[$course_name_from_url]
					);
					$url_course_id_valid = in_array(
						$course_id_from_url,
						$courseMappings
					);
	
					if (
						$url_course_name_valid &&
						$url_course_id_valid &&
						$courseMappings[$course_name_from_url] ===
							$course_id_from_url
					) {
						$field->defaultValue =
							$field_id == 11
								? $course_name_from_url
								: $course_id_from_url;
					} elseif ($url_course_name_valid) {
						$field->defaultValue =
							$field_id == 11
								? $course_name_from_url
								: $courseMappings[$course_name_from_url];
					} elseif ($url_course_id_valid) {
						$field->defaultValue =
							$field_id == 11
								? array_search($course_id_from_url, $courseMappings)
								: $course_id_from_url;
					} else {
						// Edge cases: Invalid or non-matching course name and ID
						error_log("Edge cases: Invalid or non-matching course name and ID.");
						if ($field_id == 11) {
							error_log("Please select a valid course from the list validation. Is the Course Name field visible?");
							$field->isRequired = true;
							$field->errorMessage =
								"Please select a valid course from the list.";
							$field->visibility = "visible";
						}
						// Output the client-side script within a <script> tag
						$script = "
							<script>
								jQuery(document).ready(function($) {
									// Hardcoded courseMappings array
									var courseMappings = {
										'MS Excel Beginner Course': 909,
										'MS Excel Intermediate Course': 1221,
										'MS Excel Advanced Course': 1548,
										'MS Excel Automation Course': 1920,
										'MS Excel Foundation Course': 6606,
										'MS Outlook Foundation Course': 6248,
										'MS Windows Foundation Course': 5349
									};
						
									// Select the node (the <li> containing the field) that will be observed for mutations
									var targetNode = $('select[name=\"input_11\"]').closest('li');
						
									// Options for the observer (which mutations to observe)
									var config = { attributes: true, attributeFilter: ['class'] };
						
									// Callback function to execute when mutations are observed
									var callback = function(mutationsList, observer) {
										for(var mutation of mutationsList) {
											if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
												if (!$(mutation.target).hasClass('gf_hidden')) {
													// The field has become visible
													attachChangeListener();
												}
											}
										}
									};
						
									// Create an observer instance linked to the callback function
									var observer = new MutationObserver(callback);
						
									// Start observing the target node for configured mutations
									observer.observe(targetNode[0], config);
						
									function attachChangeListener() {
										// Listen for changes on the Course Name field
										$('select[name=\"input_11\"]').change(function() {
											var selectedCourseName = $(this).val();
											var correspondingCourseID = courseMappings[selectedCourseName];
						
											// Populate the Course ID field
											if (correspondingCourseID) {
												$('input[name=\"input_7\"]').val(correspondingCourseID);
											} else {
												// Handle cases where the course name is not in the mappings
												$('input[name=\"input_7\"]').val('Invalid Course ID');
											}
										});
									}
								});
							</script>
						";
	
						// Output the script
						echo $script;
					}
				}
			}
	
			// User is logged in and on a LearnDash page, ignore URL parameters
			if ($isLoggedIn && $isSfwdPage) {
				error_log("User is logged in and on a LearnDash page, ignore URL parameters.");
				$learndash_course_id = learndash_get_course_id();
				$learndash_course_name = get_the_title($learndash_course_id);
				error_log("LearnDash Course ID: $learndash_course_id");
				error_log("LearnDash Course Name: $learndash_course_name");
				if ($field_id == 13) {
					$field->defaultValue = "spo";
				}
				if ($field_id == 11) {
					$field->defaultValue = $learndash_course_name;
				}
				if ($field_id == 7) {
					$field->defaultValue = $learndash_course_id;
				}
			}
	
			// User is logged in, set user details
			if ($isLoggedIn) {
				error_log("User is logged in, set user details.");
				if ($field_id == 4.3) {
					// First Name field
					$field->defaultValue = $current_user->user_firstname;
				}
				if ($field_id == 4.6) {
					// Last Name field
					$field->defaultValue = $current_user->user_lastname;
				}
				if ($field_id == 5) {
					// Company field
					$field->defaultValue = get_user_meta(
						$current_user->ID,
						"billing_company",
						true
					);
				}
				if ($field_id == 8) {
					// User ID field
					$field->defaultValue = $current_user->ID;
				}
				if ($field_id == 17) {
					// Email field
					$field->defaultValue = $current_user->user_email;
				}
			}
		}
	} catch (Exception $e) {
		error_log("Exception caught in sbma_populate_fields: " . $e->getMessage());
	}
	
	error_log("Exiting sbma_populate_fields function.");
	return $form;
}

/**
 * Prevent duplicate entries in Gravity Forms.
 *
 * @param array $validationResult The validation result object.
 * @return array The modified validation result object.
 */
function sbma_prevent_duplicate_entries($validationResult)
{
	$form = $validationResult["form"];
	if ($form["id"] != SBMA_GRAVITY_FORM) {
		return $validationResult;
	}
	// Server-side logging
	error_log("Entering sbma_prevent_duplicate_entries function.");
	
	global $wpdb;
	// Check if the is_user_logged_in() function exists
	// FIXME Use sbma_is_user_logged_in()
	if (function_exists('is_user_logged_in')) {
		// The is_user_logged_in() function is available
		$isLoggedIn = is_user_logged_in();
	} else {
		// The is_user_logged_in() function is not available
		// Use alternative method to determine $isLoggedIn
	
		// Check if a user is logged in using the global $current_user object
		global $current_user;
		get_currentuserinfo();
	
		$isLoggedIn = ($current_user->ID > 0);
	}
	
	// Now $isLoggedIn contains the appropriate value
	$currentUser = $isLoggedIn ? wp_get_current_user() : null;
	$email = rgpost("input_" . SBMA_FIELD_ID_EMAIL);
	$courseId = rgpost("input_" . SBMA_FIELD_ID_COURSE_ID);
	$courseName = rgpost("input_" . SBMA_FIELD_ID_COURSE_NAME);
	$mod = rgpost("input_" . SBMA_FIELD_ID_METHOD_OF_DELIVERY);
	
	error_log("User Logged In: " . ($isLoggedIn ? "Yes" : "No"));
	error_log("Email: $email");
	error_log("Course ID: $courseId");
	error_log("Course Name: $courseName");
	error_log("Method of Delivery: $mod");

	$query = "
		SELECT COUNT(*) 
		FROM {$wpdb->prefix}gf_entry_meta AS em1
		JOIN {$wpdb->prefix}gf_entry_meta AS em2 ON em1.entry_id = em2.entry_id
		JOIN {$wpdb->prefix}gf_entry_meta AS em3 ON em1.entry_id = em3.entry_id
		JOIN {$wpdb->prefix}gf_entry_meta AS em4 ON em1.entry_id = em4.entry_id
		WHERE 
			em1.meta_key = %s AND em1.meta_value = %s AND
			em2.meta_key = %s AND em2.meta_value = %s AND
			em3.meta_key = %s AND em3.meta_value = %s AND
			em4.meta_key = %s AND em4.meta_value = %s
	";
	
	$query = $wpdb->prepare($query, SBMA_FIELD_ID_EMAIL, $email, SBMA_FIELD_ID_COURSE_ID, $courseId, SBMA_FIELD_ID_COURSE_NAME, $courseName, SBMA_FIELD_ID_METHOD_OF_DELIVERY, $mod);
	error_log("SQL Query: $query");
	
	$count = $wpdb->get_var($query);
	error_log("Duplicate Count: $count");
	
	if ($count > 0) {
		// Set the SBMA_FIELD_IS_DUPLICATE field value to true
		foreach ($form["fields"] as &$field) {
			if ($field->id == SBMA_FIELD_IS_DUPLICATE) {
				$_POST["input_" . SBMA_FIELD_IS_DUPLICATE] = "true";
			}
		}
		error_log("Duplicate entry detected. Setting GF field ID: " . SBMA_FIELD_IS_DUPLICATE . " to true.");
	} else {
		// If no duplicates, ensure the SBMA_FIELD_IS_DUPLICATE field value is false
		foreach ($form["fields"] as &$field) {
			if ($field->id == SBMA_FIELD_IS_DUPLICATE) {
				$_POST["input_" . SBMA_FIELD_IS_DUPLICATE] = "false";
			}
		}
		error_log("Duplicate entry not detected. Setting GF field ID: " . SBMA_FIELD_IS_DUPLICATE . " to false.");
	}
	
	$validationResult["form"] = $form;
	error_log("Exiting sbma_prevent_duplicate_entries function.");
	return $validationResult;
}

/**
 * Duplicate entry message in Gravity Forms.
 *
 * @param array $confirmation The form confirmation message object.
 * @return array The modified confirmation message result object.
 */
function sbma_duplicate_custom_confirmation($confirmation, $form, $entry, $ajax) {
	error_log("Entering sbma_duplicate_custom_confirmation function.");
	error_log("SBMA_FIELD_IS_DUPLICATE: " . $entry[SBMA_FIELD_IS_DUPLICATE]);
	if ($entry[SBMA_FIELD_IS_DUPLICATE] === "true") {
		error_log("SBMA_FIELD_IS_DUPLICATE evaluated true.");
		$confirmation = '<h4>Thank you, you have already sent in a review for this course. No need to resend.</h4>';
		error_log("Duplicate entry detected. GF confirmation message sbma_duplicate_custom_confirmation: " . $entry['id']);
		
		// Delete the duplicate entry
		if (class_exists('GFAPI')) {
			error_log("class_exists('GFAPI') validated to true.");
			try {
				GFAPI::delete_entry($entry['id']);
				error_log( "GF Entry Deleted: " . $entry['id'] );
			} catch (Exception $e) {
				error_log("Exception caught in sbma_duplicate_custom_confirmation: " . $e->getMessage());
			}
		} else {
			error_log("class_exists('GFAPI') validated to false.");
		}
	}
	return $confirmation;
}

/**
 * Mark Course Complete.
 * Redirect user after form submission.
 *
 * @param array $entry The entry object.
 * @param array $form The form object.
 */
function sbma_mark_course_as_complete_redirect($entry, $form)
{
	if ($form["id"] != SBMA_GRAVITY_FORM) {
		return;
	}
	
	// Check if the is_user_logged_in() function exists
	// FIXME Use sbma_is_user_logged_in()
	if (function_exists('is_user_logged_in')) {
		// The is_user_logged_in() function is available
		$isLoggedIn = is_user_logged_in();
	} else {
		// The is_user_logged_in() function is not available
		// Use alternative method to determine $isLoggedIn
	
		// Check if a user is logged in using the global $current_user object
		global $current_user;
		get_currentuserinfo();
	
		$isLoggedIn = ($current_user->ID > 0);
	}

	$postType = get_post_type();
	$isSfwdPage = strpos($postType, "sfwd") !== false;
	$courseId = rgar($entry, SBMA_FIELD_ID_COURSE_ID);

	// Additional logic from v4.2.3
	if ($isLoggedIn && $isSfwdPage) {
		$user_id = get_current_user_id();
		$lesson_id = get_the_ID(); // Get LearnDash lesson ID

		if ($lesson_id) {
			// Mark lesson as complete for the user
			if (function_exists("learndash_process_mark_complete")) {
				learndash_process_mark_complete($user_id, $lesson_id);
				error_log(
					"Marked lesson " .
						$lesson_id .
						" as complete for user: " .
						$user_id
				);
				$courseId = learndash_get_course_id(get_the_ID());
				$completionURL = get_permalink($courseId); // Build the course completion URL

				// Set the confirmation to redirect to the course completion URL
				$confirmation = [
					"redirect" => $completionURL,
				];
				return $confirmation;
			} else {
				error_log(
					"learndash_process_mark_complete function not available"
				);
			}
		}
	}
}
