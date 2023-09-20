<?php
// [REVIEW]
add_shortcode( 'course_comments', 'sbma_course_comments_rating_shortcode' );
function sbma_course_comments_rating_shortcode( $atts ) {
	if ( ! class_exists( 'Types_Main' ) ) {
		return '<p>The Toolset Types plugin is not active</p>';
	}

	$atts = shortcode_atts( array(
		'course_id' => '',
		'output' => 'Average',
		'permission' => 'No',
	), $atts );

	// Validate Course ID parameter value
	$course = $atts['course_id'];
	if (!is_numeric($course)) {
		return '<p class="color:red;">Error: Course ID should be a number</p>';
	}

	// Sanitize and validate the Output parameter value
	$output = ucwords(preg_replace("/[^a-zA-Z]+/", "", $atts['output']));

	if (!in_array($output, array('Average', 'Stars', 'Total'))) {
		return '<p class="color:red;">Error: Invalid value for the "output" parameter. Expected "Average" or "Stars" or "Total".</p>';
	}


	// Sanitize and validate the Permission parameter value
	$permission = ucwords(preg_replace("/[^a-zA-Z]+/", "", $atts['permission']));
	if ($permission !== 'Yes' && $permission !== 'No') {
	  return '<p class="color:red;">Error: Invalid value for the "permission" parameter. Expected "Yes" or "No".</p>';
	}


	$args = array(
		'post_type' => 'course-testimonials',
		'posts_per_page' => -1,
		'post_status' => 'publish',
	);

	if ($permission == "Yes") {
		$args = array_merge( $args, array(
			'meta_key' => 'wpcf-testimonial-permission',
			'meta_value' => 'Yes',
			'meta_compare' => '='
		));
	}

	$reviews = get_posts($args);

	$total_rating = 0;
	$count = 0;

	foreach ($reviews as $review) {
		$review_course_id = get_post_meta($review->ID, 'wpcf-testimonial-course-id', true);
		if ($review_course_id == $course) {
			$rating = get_post_meta($review->ID, 'wpcf-testimonial-course-stars', true);
			if ($rating) {
				$total_rating += $rating;
				$count++;
			}
		}
	}

	if ($count > 0) {
		$average_rating = round($total_rating / $count, 1);
		$result = '';

		switch ($output) {
			case 'Average':
				$result = "<h4>" . $average_rating . " out of 5</h4>";
				break;
			case 'Stars':
				$result = $average_rating;
				break;
			case 'Total':
				$result = "<h4 style='text-align:centre;'>" . $count . " Reviews</h4>";
				break;
			default:
				$result = "<h4 style='text-align:centre;'>" . $average_rating . " out of 5</h4>";
				break;
		}
		return $result;
	} else {
		return "<h4>No reviews found</h4>";
	}
}
