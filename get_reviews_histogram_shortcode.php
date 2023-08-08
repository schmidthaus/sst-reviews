<?php
/* 
Name: Reviews Histogram with MOD and Courses Update - AJAX Edition
Description: This creates a histogram for product ratings with a method of delivery filter and updates for course handling, now with dynamic AJAX support
Version: 3.5.1  
*/
function get_reviews_histogram_shortcode($atts)
{
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
	} elseif ($a['course'] && $a['mod']) {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars' AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course' AND meta_value = %s AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-mod' AND meta_value = %s))";
		$prepared_query = $wpdb->prepare($query, $a['course'], $a['mod']);
	} elseif ($a['course'] && $a['stars']) {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars' AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course' AND meta_value = %s AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-stars' AND meta_value = %d))";
		$prepared_query = $wpdb->prepare($query, $a['course'], $a['stars']);
	} elseif ($a['mod'] && $a['stars']) {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars' AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-mod' AND meta_value = %s AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-stars' AND meta_value = %d))";
		$prepared_query = $wpdb->prepare($query, $a['mod'], $a['stars']);
	} elseif ($a['course']) {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars' AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course' AND meta_value = %s)";
		$prepared_query = $wpdb->prepare($query, $a['course']);
	} elseif ($a['mod']) {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars' AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-mod' AND meta_value = %s)";
		$prepared_query = $wpdb->prepare($query, $a['mod']);
	} elseif ($a['stars']) {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars' AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-stars' AND meta_value = %d)";
		$prepared_query = $wpdb->prepare($query, $a['stars']);
	} else {
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'wpcf-testimonial-course-stars'";
		$prepared_query = $query;
	}

	// Execute the query
	$results = $wpdb->get_col($prepared_query);
	
	// Log the SQL query results
	error_log(print_r($results, true));
	
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

function get_reviews_histogram_handler() {
	error_log('AJAX request received at: ' . admin_url('admin-ajax.php'));

	// Check AJAX nonce for security
	if (!check_ajax_referer('reviews_histogram_nonce', 'security', false)) {
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
		'3.4.12',
		true
	);

	wp_localize_script(
		'reviews-histogram-ajax-script',
		'ajax_object',
		array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'security' => wp_create_nonce('reviews_histogram_nonce'),
		)
	);
}
<<<<<<< HEAD
add_action('wp_enqueue_scripts', 'enqueue_reviews_histogram_script');
=======
add_action('wp_enqueue_scripts', 'enqueue_reviews_histogram_script');
>>>>>>> main
