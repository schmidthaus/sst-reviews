<?php
/* 
Name: Reviews Histogram with MOD and Courses Update - AJAX Edition
Description: This creates a histogram for product ratings with a method of delivery filter and updates for course handling, now with dynamic AJAX support
Version: 3.5.7  
*/

/**
  * Set up AJAX Histogram
  * updating when filters are changed
  *
  * @return 
  */
add_action('wp_enqueue_scripts', 'sbma_create_reviews_histogram_ajax_nonce');
add_action( 'wp_ajax_sbma_reviews_histogram_ajax_action', 'sbma_reviews_histogram_ajax_action' );
add_action('wp_ajax_nopriv_sbma_reviews_histogram_ajax_action', 'sbma_reviews_histogram_ajax_action');
//add_filter('wpv_filter_wpv_view_widget_output', 'sbma_views_filter_callback', 10, 1);

/**
  * Generate the Reviews Histogram Shortcode
  * 
  *
  * @return 
  */
add_shortcode('reviews_shortcode', 'generate_reviews_histogram_shortcode');


// ---------------------------------------- Front end Events Handlers ----------------------------------------

/**
 * Enqueue the AJAX client side script event handler
 * Create NONCE for AJAX Histogram
 * 
 *
 * @return 
 */
function sbma_create_reviews_histogram_ajax_nonce() {
	// Enqueue the AJAX client side script
	wp_enqueue_script(
		'reviews-histogram-ajax-script',
		plugins_url('/reviews-histogram/reviews-histogram.js'),
		array('jquery'),
		'3..6.1',
		true
	);
	
	// Create the NONCE
	wp_localize_script( 'reviews-histogram-ajax-script', 'reviews_histogram_ajax_obj', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'sbma_reviews_histogram_ajax_nonce' ),
	) );
 }

/**
  * Verify NONCE for AJAX Histogram
  * updating when filters are changed
  *
  * @return 
  */
function sbma_reviews_histogram_ajax_action() {
	error_log("sbma_reviews_histogram_ajax_action function called");
	error_log('AJAX request received at: ' . admin_url('admin-ajax.php'));
	 
	// Check AJAX nonce for security
	if ( ! wp_verify_nonce( $_POST['nonce'], 'sbma_reviews_histogram_ajax_nonce' ) ) {
		error_log('Nonce check failed.');
		wp_die('Nonce check failed.', 403);
	}
 
	// Handle the AJAX request
	// Use the $_POST array to retrieve filter values
	$atts = array(
		 'course-dynamic' => isset($_POST['course-dynamic']) ? sanitize_text_field($_POST['course-dynamic']) : null,
		 'mod-dynamic' => isset($_POST['mod-dynamic']) ? sanitize_text_field($_POST['mod-dynamic']) : null,
		 'stars-dynamic' => isset($_POST['stars-dynamic']) ? sanitize_text_field($_POST['stars-dynamic']) : null,
	);
	 
	// Return the result as JSON
	echo generate_reviews_histogram_shortcode($atts);
	error_log("Exit sbma_reviews_histogram_ajax_action function");
	 
	wp_die(); // All ajax handlers should die when finished
 }
 
/**
 * Hook into the Toolset Views event handler to replace
 * Reviews Histogram shortcode when a filter is changed
 * Triggers after the View has fetched and processed the content,
 * but before it's displayed on the frontend
 * 
 *
 * @return 
 */
function sbma_views_filter_callback($out) {
	// Modify the $out variable, which contains the View's output
	// Evaluate all combinations of attributes passed to the shortcode
	if (preg_match('/\[reviews_shortcode( course="(.*?)")?( mod="(.*?)")?( stars="(.*?)")?]/', $out, $matches)) {
		$atts = array(
			'course-dynamic' => isset($matches[2]) ? sanitize_text_field($matches[2]) : null,
			'mod-dynamic' => isset($matches[4]) ? sanitize_text_field($matches[4]) : null,
			'stars-dynamic' => isset($matches[6]) ? sanitize_text_field($matches[6]) : null,
		);
		$out = str_replace($matches[0], generate_reviews_histogram_shortcode($atts), $out);
	}
	
	return $out;
 }

// ---------------------------------------- Shortcode ---------------------------------------- 
/**
 * Generate Reviews Histogram
 * Shortcode Attributes:
 * Setup values: course, mod, stars
 * AJAX values: course-dynamic, mod-dynamic, stars-dynamic
 *
 *
 * @param array $atts.
 * @return 
 */
function generate_reviews_histogram_shortcode($atts) {
	error_log("Enter generate_reviews_histogram_shortcode function");
	// Check if the function has already been executed
	static $already_executed = false;
	if ($already_executed) return;
	$already_executed = true;
	
	global $wpdb;
	
	// Set up the parameter validation arrays
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
	
	// [DEPRECIATED]: REMOVE AFTER TESTING $courseMappings
	/* Set acceptable 'course' values
	$valid_courses = [
		'MS Excel Beginner Course',
		'MS Excel Intermediate Course',
		'MS Excel Advanced Course',
		'MS Excel Automation Course',
		'MS Excel Foundation Course',
		'MS Outlook Course',
		'MS Windows Foundation Course'
	]; */
	
	// Set acceptable 'mod' values
	$modMappings = [
		"Self-paced Online" => "spo",
		"Live Online" => "lonl",
		"Live Onsite" => "lons",
	];
	
	
	// [DEPRECIATED]: REMOVE AFTER TESTING $modMappings
	// $valid_mods = ['spo', 'lons', 'lonl'];

	// Unpack and sanitize the attributes passed to the shortcode
	$a = shortcode_atts(array('course' => null, 'mod' => null, 'stars' => null, 'course-dynamic' => null, 'mod-dynamic' => null, 'stars-dynamic' => null), $atts);
	$a = array_map('sanitize_text_field', $a);
	
	// Validate 'course' values
	//[TASK] ENSURE ALL COMBINATIONS AND FALLBACK VALUES ARE IMPLEMENTED
	if (in_array($a['course-dynamic'], $courseMappings)) {
		$a['course'] = $a['course-dynamic'];
	} elseif (!in_array($a['course'], $courseMappings)) {
		$a['course'] = null;
	}
	
	// Validate 'mod' values
	//[TASK] ENSURE ALL COMBINATIONS AND FALL BACK VALUES ARE IMPLEMENTED
	if (in_array($a['mod-dynamic'], $modMappings)) {
		$a['mod'] = $a['mod-dynamic'];
	} elseif (!in_array($a['mod'], $modMappings)) {
		$a['mod'] = null;
	}
	
	// Validate 'stars' values
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
	//error_log('SQL query Results: ' . print_r($results, true));
	
	// Trigger an action so that other plugins/themes can modify the result if needed
	// [TASK] The function doesn't exist. Is it depreciated or necessary?
	// do_action('get_reviews_histogram_after_query', $results, $a);

	// Filter the results to keep only integer values and convert them to integer type
	$results = array_map('intval', array_filter($results, 'is_numeric'));
	
	// If no results are found, return early with a message
	// Return an empty Reviews Histogram message
	if (empty($results)) {
		error_log('No results found from SQL query.');
	
		if (defined('DOING_AJAX') && DOING_AJAX) {
			wp_send_json(array(
				'html' => 'No Courses, Methods of Delivery, or Stars found for this filter. Please select different filter values',
			));
		} else {
			// [TASK] This doesn't make any sense. Revisit.
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
		// This code will only run during AJAX requests
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
		// This code will only run when it's not an AJAX request
		// [TASK] Is there a way to use html attributes to repopulate the histogram values via AJAX?
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
	error_log("Exit generate_reviews_histogram_shortcode function");
}