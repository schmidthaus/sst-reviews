<?php
/* 
Name: Reviews Histogram with MOD and Courses Update - AJAX Edition
Description: This creates a histogram for product ratings with a method of delivery filter and updates for course handling, now with dynamic AJAX support
Version: 3.5.5  
*/
function get_reviews_histogram_shortcode($atts)
{
	// Check if the function has already been executed
	static $already_executed = false;
	if ($already_executed) return;
	$already_executed = true;
	global $wpdb;

	// Set acceptable 'course' values
	$valid_courses = [
		'MS Excel Beginner Course',
		'MS Excel Intermediate Course',
		'MS Excel Advanced Course',
		'MS Excel Automation Course',
		'MS Excel Foundation Course',
		'MS Outlook Course',
		'MS Windows Foundation Course'
	];

	// Set acceptable 'mod' values
	$valid_mods = ['spo', 'lons', 'lonl'];

	// Unpack and sanitize the attributes
	$a = shortcode_atts(array('course' => null, 'mod' => null, 'stars' => null, 'course-dynamic' => null, 'mod-dynamic' => null, 'stars-dynamic' => null), $atts);
	$a = array_map('sanitize_text_field', $a);
	
	// Validate 'course', 'mod', and 'stars' values
	if (in_array($a['course-dynamic'], $valid_courses)) {
		$a['course'] = $a['course-dynamic'];
	} elseif (!in_array($a['course'], $valid_courses)) {
		$a['course'] = null;
	}
	
	if (in_array($a['mod-dynamic'], $valid_mods)) {
		$a['mod'] = $a['mod-dynamic'];
	} elseif (!in_array($a['mod'], $valid_mods)) {
		$a['mod'] = null;
	}
	
	if (isset($a['stars-dynamic']) && is_numeric($a['stars-dynamic']) && $a['stars-dynamic'] >= 1 && $a['stars-dynamic'] <= 5) {
		$a['stars'] = $a['stars-dynamic'];
	} elseif (!isset($a['stars']) || !is_numeric($a['stars']) || $a['stars'] < 1 || $a['stars'] > 5) {
		$a['stars'] = null;
	}
	
	// prepare the SQL query based on attribute values
	if ($a['course'] && $a['mod'] && $a['stars']) {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars' AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course' AND meta_value = %s AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-mod' AND meta_value = %s AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-stars' AND meta_value = %d)))";
		$prepared_query = $wpdb->prepare($query, $a['course'], $a['mod'], $a['stars']);
		error_log('SQL query: course, mod, stars');
	} elseif ($a['course'] && $a['mod']) {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars' AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course' AND meta_value = %s AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-mod' AND meta_value = %s))";
		$prepared_query = $wpdb->prepare($query, $a['course'], $a['mod']);
		error_log('SQL query: course, mod');
	} elseif ($a['course'] && $a['stars']) {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars' AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course' AND meta_value = %s AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-stars' AND meta_value = %d))";
		$prepared_query = $wpdb->prepare($query, $a['course'], $a['stars']);
		error_log('SQL query: course, stars');
	} elseif ($a['mod'] && $a['stars']) {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars' AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-mod' AND meta_value = %s AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-stars' AND meta_value = %d))";
		$prepared_query = $wpdb->prepare($query, $a['mod'], $a['stars']);
		error_log('SQL query: mod, stars');
	} elseif ($a['course']) {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars' AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course' AND meta_value = %s)";
		$prepared_query = $wpdb->prepare($query, $a['course']);
		error_log('SQL query: course');
	} elseif ($a['mod']) {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars' AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-mod' AND meta_value = %s)";
		$prepared_query = $wpdb->prepare($query, $a['mod']);
		error_log('SQL query: mod');
	} elseif ($a['stars']) {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars' AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-stars' AND meta_value = %d)";
		$prepared_query = $wpdb->prepare($query, $a['stars']);
		error_log('SQL query: stars');
	} else {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars'";
		$prepared_query = $query;
		error_log('SQL query: all');
	}

	// Execute the query
	$results = $wpdb->get_col($prepared_query);
	
	// Log the SQL query results
	error_log('SQL query Results: ' . print_r($results, true));
	
	// Trigger an action so that other plugins/themes can modify the result if needed
	do_action('get_reviews_histogram_after_query', $results, $a);

	// Filter the results to keep only integer values and convert them to integer type
	$results = array_map('intval', array_filter($results, 'is_numeric'));
	
	 // If no results are found, return early with a message
	if (empty($results)) {
		error_log('No results found from SQL query.');
	
		if (defined('DOING_AJAX') && DOING_AJAX) {
			wp_send_json(array(
				'html' => 'No course, mod, or stars reviews found.',
			));
		} else {
			return 'No course, mod, or stars reviews found.';
		}
	}

	// Tally the results and calculate the total number of reviews
	$ratings = array_count_values($results);
	$total_ratings = array_sum($ratings);
	
	error_log('Tally ratings: ' . print_r($ratings, true) . ', Total ratings: ' . $total_ratings);
	
	// Calculate the ratings percentages
	$ratings_percentages = [];
	foreach ($ratings as $stars => $rating_count) {
		$percentage = round((($rating_count / $total_ratings) * 100), 1);
		$ratings_percentages[$stars] = $percentage;
	}

	ob_start();

	// Return JSON for AJAX requests
	if (defined('DOING_AJAX') && DOING_AJAX) {
		// Check if output buffering is active
		if (ob_get_length() === false) {
			error_log('Output buffering is not active.');
			ob_start();
		}

		// If content is available in the output buffer, send it
		if (ob_get_contents() !== false) {
			error_log('Content found in output buffer: ' . ob_get_contents());
			wp_send_json(array(
				'html' => ob_get_clean(),
			));
		} else {
			error_log('No content found in output buffer.');
			wp_send_json(array(
				'html' => 'No content available.',
			));
		}
	} else {
		?>
		<div class="reviews-histogram">
			<?php for ($stars = 5; $stars >= 1; $stars--) : ?>
				<div class="histogram-row">
					<div class="nowrap">
						<span><?php echo $stars; ?> star</span>
					</div>
					<div class="span10">
						<div class="meter" role="progressbar" aria-valuenow="<?php echo isset($ratings_percentages[$stars]) ? $ratings_percentages[$stars] : 0; ?>">
							<div class="meter-bar meter-filled" style="width: <?php echo isset($ratings_percentages[$stars]) ? $ratings_percentages[$stars] : 0; ?>%; background-color: <?php echo $stars > 3 ? '#FF9900' : ($stars == 3 ? '#A6A6A6' : '#D63737'); ?>;"></div>
						</div>
					</div>
					<div class="text-right nowrap">
						<span><?php echo isset($ratings_percentages[$stars]) ? $ratings_percentages[$stars] : 0; ?>%</span>
					</div>
				</div>
			<?php endfor; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
add_shortcode('reviews_shortcode', 'get_reviews_histogram_shortcode');

// Generate nonce
function get_reviews_histogram_nonce() {
	return wp_create_nonce('reviews_histogram_nonce');
}

function get_reviews_histogram_handler() {
	error_log('AJAX request received at: ' . admin_url('admin-ajax.php'));

	// Check AJAX nonce for security
	if (!check_ajax_referer(get_reviews_histogram_nonce(), 'security', false)) {
		error_log('Nonce check failed.');
		wp_die('Nonce check failed.', 403);
	}
	
	// Use the $_POST array to retrieve filter values
	$atts = array(
		'course-dynamic' => isset($_POST['course-dynamic']) ? sanitize_text_field($_POST['course-dynamic']) : null,
		'mod-dynamic' => isset($_POST['mod-dynamic']) ? sanitize_text_field($_POST['mod-dynamic']) : null,
		'stars-dynamic' => isset($_POST['stars-dynamic']) ? sanitize_text_field($_POST['stars-dynamic']) : null,
	);

	// Return the result as JSON
	echo get_reviews_histogram_shortcode($atts); 
	wp_die(); // All ajax handlers should die when finished
}

add_action('wp_ajax_get_reviews_histogram', 'get_reviews_histogram_handler');
add_action('wp_ajax_nopriv_get_reviews_histogram', 'get_reviews_histogram_handler');

// Hook to the wpv_filter_wpv_view_widget_output to replace the shortcode
add_filter('wpv_filter_wpv_view_widget_output', function($out) {
	// Evaluate all combinations of attributes passed to the shortcode
	if (preg_match('/\[reviews_shortcode( course="(.*?)")?( mod="(.*?)")?( stars="(.*?)")?]/', $out, $matches)) {
		$atts = array(
			'course-dynamic' => isset($matches[2]) ? sanitize_text_field($matches[2]) : null,
			'mod-dynamic' => isset($matches[4]) ? sanitize_text_field($matches[4]) : null,
			'stars-dynamic' => isset($matches[6]) ? sanitize_text_field($matches[6]) : null,
		);
		$out = str_replace($matches[0], get_reviews_histogram_shortcode($atts), $out);
	}
	return $out;
}, 10, 1);

// Enqueue script
function enqueue_reviews_histogram_script() {
	wp_enqueue_script(
		'reviews-histogram-ajax-script',
		plugins_url('/reviews-histogram/reviews-histogram-script.js'),
		array('jquery'),
		'3.5.4',
		true
	);

	wp_localize_script(
		'reviews-histogram-ajax-script',
		'ajax_object',
		array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'security' => get_reviews_histogram_nonce(),
		)
	);
}

add_action('wp_enqueue_scripts', 'enqueue_reviews_histogram_script');
add_action('wp_enqueue_scripts', 'enqueue_reviews_histogram_script');

/* 
Name: Mark lesson complete when Course Review is submitted
Description: Mark the LearnDash Course that the logged in user is currently on as complete when
they submit the course review form. Redirect the user to to the course completion URL.
Version: 2.1  
*/

add_action( 'gform_after_submission_11', 'sbma_complete_lesson_on_form_submission', 20, 2 );
function sbma_complete_lesson_on_form_submission( $entry, $form ) {
	// Check if user is logged in
	if ( ! is_user_logged_in() ) {
		error_log( "User not logged in" );
		return;
	}

	// Get current user info
	$current_user = wp_get_current_user();
	$user_id = $current_user->ID;
	error_log( "User ID: " . $user_id );

	// Check for current post type containing 'sfwd'
	$post_type = get_post_type();
	if ( strpos( $post_type, 'sfwd' ) !== false ) {
		// Get LearnDash lesson ID
		$lesson_id = get_the_ID();
		error_log( "Lesson ID: " . $lesson_id );

		// Mark lesson as complete for the user
		if ( function_exists( 'learndash_process_mark_complete' ) ) {
			learndash_process_mark_complete( $user_id, $lesson_id );
			error_log( "Marked lesson as complete for user" );
		} else {
			error_log( "learndash_process_mark_complete function not available" );
		}
	} else {
		error_log( "Post type '{$post_type}' does not contain 'sfwd'" );
	}
	
	// Log the form field values
	error_log( "Mark Complete: Form field values at gform_after_submission hook: " . print_r( $entry, true ) );
}


add_filter('gform_confirmation_11', 'sbma_redirect_after_form_submission', 20, 4);
function sbma_redirect_after_form_submission($confirmation, $form, $entry, $ajax) {
	// Get current post type
	$post_type = get_post_type();

	// Check if the current post type is a LearnDash lesson
	if (strpos($post_type, 'sfwd') !== false) {
		// Pass the current post ID to get the correct course ID
		$course_id = learndash_get_course_id(get_the_ID());

		// Build the course completion URL
		$completion_url = get_permalink($course_id);

		// Set the confirmation to redirect to the course completion URL
		$confirmation = array(
			'redirect' => $completion_url,
		);
	} else {
		// If not on a LearnDash lesson page, use the default Gravity Forms confirmation
		// (No changes needed, the default confirmation will be used)
	}

	return $confirmation;
}

/* 
Name: Pre-populate Course Review form
Description: Populate the current User and course info fields on the Course Review form
if the user is logged in and on a LearnDash course page.
Version: 1.9  
*/

// If the user is logged in, populate fields with user and course data.
add_filter( 'gform_field_value_11', 'sbma_populate_course_comment_fields', 10, 3 );
function sbma_populate_course_comment_fields( $value, $field, $name ) {
	// If it's not a logged-in user viewing this, never mind
	if( ! is_user_logged_in() ) return $value;

	// Get Logged in user info
	$current_user = wp_get_current_user();
	$fname = $current_user->user_firstname;
	$lname = $current_user->user_lastname;
	$lms_org = $current_user->billing_company;
	$lms_user_id = $current_user->ID;
	
	// Initialize course id and course name variables
	$course_id = '';
	$course_name = '';
	
	// Check for current page being of post_type containing 'sfwd'
	$post_type = get_post_type();
	if (strpos($post_type, 'sfwd') !== false) {
		// Get LearnDash Course info
		$lesson_id = get_the_ID();
		$course_id = learndash_get_course_id($lesson_id);//get course id
		$course = get_post($course_id); //get course
		if ($course) {
			$course_name = $course->post_title;
		}
	}

	// Populate Gravity form Dynamic Population Parameter names
	$values = array(
		'lms_fname' => $fname,
		'lms_lname' => $lname,
		'lms_org' => $lms_org,
		// 'lms_user_id'   => $lms_user_id,
		'lms_course_id'   => $course_id,
		'lms_course_name' => $course_name
	);

	return $values[$name] ?? $value;
}


/* 
Name: Prevent duplicate submissions of the Course Review form
Description: Users occasionally resubmit the Course Review form. This function works 
to prevent these duplicate submissions by checking if an entry already exists in the Gravity Forms entries in the database.
Version: 2.32  
*/

// If the user is logged in, populate fields with user and course data.
add_filter( 'gform_field_value_11', 'sbma_populate_course_comment_fields', 10, 3 );
function sbma_populate_course_comment_fields( $value, $field, $name ) {
	// If it's not a logged-in user viewing this, never mind
	if( ! is_user_logged_in() ) return $value;

	// Get Logged in user info
	$current_user = wp_get_current_user();
	$fname = $current_user->user_firstname;
	$lname = $current_user->user_lastname;
	$lms_org = $current_user->billing_company;
	$lms_user_id = $current_user->ID;
	
	// Initialize course id and course name variables
	$course_id = '';
	$course_name = '';
	
	// Check for current page being of post_type containing 'sfwd'
	$post_type = get_post_type();
	if (strpos($post_type, 'sfwd') !== false) {
		// Get LearnDash Course info
		$lesson_id = get_the_ID();
		$course_id = learndash_get_course_id($lesson_id);//get course id
		$course = get_post($course_id); //get course
		if ($course) {
			$course_name = $course->post_title;
		}
	}

	// Populate Gravity form Dynamic Population Parameter names
	$values = array(
		'lms_fname' => $fname,
		'lms_lname' => $lname,
		'lms_org' => $lms_org,
		// 'lms_user_id'   => $lms_user_id,
		'lms_course_id'   => $course_id,
		'lms_course_name' => $course_name
	);

	return $values[$name] ?? $value;
}

// 
// // Defined constants for Gravity Forms field IDs
// Constants to replace hardcoded field IDs in the script
define('FIELD_ID_USER', '8'); // User ID field
define('FIELD_ID_COURSE', '7'); // Course ID field
define('FIELD_ID_MOD', '13'); // Course module field
define('FIELD_ID_EMAIL', '17'); // Email field
define('FIELD_ID_DUPLICATE', '19'); // Duplicate detection field

global $current_user_id;
$current_user_id = get_current_user_id(); // Get the ID of the currently logged in user.

function save_user_id_pre_submission_v22($form) {
	global $current_user_id;
	foreach($form['fields'] as &$field) {
		// This part sets the default user ID field
		if($field->id == 8) {
			$field->defaultValue = $current_user_id;
			error_log('save_user_id_pre_submission_v23: Current User ID (get_current_user_id): ' . $current_user_id);
		}

		// Set the default Course ID field when the form is on a post of type 'sfwd'
		if($field->id == 7 && strpos(get_post_type(), 'sfwd') !== false) {
			$lesson_id = get_the_ID();
			$course_id = learndash_get_course_id($lesson_id);
			error_log('save_user_id_pre_submission_v23: Course ID (learndash_get_course_id): ' . $course_id);
			$field->defaultValue = $course_id;
		}
	}
	return $form;
}
add_filter('gform_pre_render_11', 'save_user_id_pre_submission_v22');

function check_for_duplicate_entries_v22($validation_result) {
	global $current_user_id;

	error_log('check_for_duplicate_entries_v22: Starting Current User ID (get_current_user_id): ' . $current_user_id);

	$form = $validation_result["form"];

	// Check if the post type contains 'sfwd'
	if(strpos(get_post_type(), 'sfwd') !== false) {
		// Get the current LearnDash course ID
		$lesson_id = get_the_ID();
		error_log( "Retrieved Lesson ID: " . $lesson_id );
		$course_id = learndash_get_course_id($lesson_id);
		error_log( "learndash_get_course_id() return value: " . $course_id );
		$course_mod = 'spo';
	} else if(get_the_ID() == 12463) {
		// Retrieve course ID and course mod from the form submission
		$course_id = rgpost('input_' . FIELD_ID_COURSE);
		$course_mod = (rgpost('input_' . FIELD_ID_MOD)) ? rgpost('input_' . FIELD_ID_MOD) : 'lonl';
	} else {
		error_log('check_for_duplicate_entries_v22: Returning validation_result as-is.');
		return $validation_result;
	}

	// Create search criteria based on whether the user is logged in or not
	if(is_user_logged_in()) {
		$search_criteria = get_search_criteria_logged_in($current_user_id, $course_id, $course_mod);
	} else {
		$email = rgpost('input_' . FIELD_ID_EMAIL);
		$search_criteria = get_search_criteria_logged_out($email, $course_id, $course_mod);
	}

	error_log('check_for_duplicate_entries_v22: Search Criteria: ' . print_r($search_criteria, true));

	// Check for existing entries that match the search criteria
	$dup_check = GFAPI::count_entries($form['id'], $search_criteria);
	error_log('check_for_duplicate_entries_v22: Duplication Check Result: ' . $dup_check);

	// If a duplicate was found, mark the form as duplicate
	if($dup_check > 0) {
		$_POST['input_' . FIELD_ID_DUPLICATE] = 'true'; // Update duplicate field in POST data
		$validation_result["is_valid"] = true; // Override validation to show confirmation message
		error_log('check_for_duplicate_entries_v22: Duplication Check True: ' . $validation_result["is_valid"]);
	}

	return $validation_result;
}
add_filter('gform_validation_11', 'check_for_duplicate_entries_v22');


function get_search_criteria_logged_in($user_id, $course_id, $course_mod) {
	// Define search criteria for logged in users
	return array(
		'status' => 'active',
		'field_filters' => array(
			'mode' => 'all',
			array(
				'key' => FIELD_ID_USER,
				'value' => $user_id
			),
			array(
				'key' => FIELD_ID_COURSE,
				'value' => $course_id
			),
			array(
				'key' => FIELD_ID_MOD,
				'value' => $course_mod
			)
		)
	);
}

function get_search_criteria_logged_out($email, $course_id, $course_mod) {
	// Define search criteria for logged out users
	return array(
		'status' => 'active',
		'field_filters' => array(
			'mode' => 'all',
			array(
				'key' => FIELD_ID_EMAIL,
				'value' => $email
			),
			array(
				'key' => FIELD_ID_COURSE,
				'value' => $course_id
			),
			array(
				'key' => FIELD_ID_MOD,
				'value' => $course_mod
			)
		)
	);
}

function modify_confirmation_message_v22($confirmation, $form, $entry, $ajax) {
	// Change the confirmation message if the form was marked as duplicate
	if(rgar($entry, FIELD_ID_DUPLICATE) === 'true') {
		$confirmation = "<h4>Thank you, you have already sent in a review for this course. No need to resend.</h4>";
	}
	return $confirmation;
}
add_filter('gform_confirmation_11', 'modify_confirmation_message_v22', 10, 4);

function delete_duplicate_entry_v22($entry, $form) {
	global $current_user_id;
	$course_id = rgar($entry, FIELD_ID_COURSE); // Retrieve course id from the entry
	$course_mod = rgar($entry, FIELD_ID_MOD); // Retrieve course mod from the entry
	$email = rgar($entry, FIELD_ID_EMAIL); // Retrieve email from the entry

	// Create search criteria based on whether the user is logged in or not
	if(is_user_logged_in()) {
		$search_criteria = get_search_criteria_logged_in($current_user_id, $course_id, $course_mod);
	} else {
		$search_criteria = get_search_criteria_logged_out($email, $course_id, $course_mod);
	}

	// Check for existing entries that match the search criteria
	$dup_check = GFAPI::count_entries($form['id'], $search_criteria);

	// If more than one entry was found, mark the current entry as duplicate and delete it
	if($dup_check > 1) {
		GFAPI::update_entry_field($entry['id'], FIELD_ID_DUPLICATE, 'true');
		GFAPI::delete_entry($entry['id']);
		error_log( "GF Entry Deleted: " . $entry['id'] );
	}
}
add_action('gform_after_submission_11', 'delete_duplicate_entry_v22', 10, 2);


/**
 * Gravity Forms pre-submission filter for form ID 11.
 * Version: 1.16
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
 
	 // Validate page ID and post type
	 if (empty($page_id) || empty($post_type)) {
		 error_log("Page ID or Post Type is missing in sbma_validate_reviews.");
	 }
 
	 // Loop through form fields
	 try {
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
				 } elseif (empty($lms_course_name) && !empty($lms_course_id)) {
					 $field_value = array_search($lms_course_id, $course_mappings); // Set lms_course_name based on lms_course_id
				 }
			 }
 
			 if ($field->id == $FIELD_LMS_COURSE_ID) {
				 if (!empty($lms_course_name) && isset($course_mappings[$lms_course_name])) {
					 $field_value = $course_mappings[$lms_course_name]; // Correct lms_course_id based on lms_course_name
				 } elseif (!empty($lms_course_id)) {
					 // Set lms_course_id if it's provided
					 $field_value = $lms_course_id;
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
	 } catch (Exception $e) {
		 error_log("Exception caught in sbma_validate_reviews: " . $e->getMessage());
	 }
 
	 return $form;
 }
 
 // Hook the function to Gravity Forms pre-submission filter for form ID 11
 add_filter('gform_pre_submission_filter_11', 'sbma_validate_reviews');

