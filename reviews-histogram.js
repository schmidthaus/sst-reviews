// Reviews Histogram AJAX event handler v3.7.1

jQuery(document).ready(function ($) {
	// Variables to store the current filter values
	let currentCourseFilter = "";
	let currentModFilter = "";
	let currentStarsFilter = "";

	// Function to handle filter changes
	function handleFilterChange() {
		const newCourseFilter = $(
			"#wpv_control_select_wpcf-testimonial-course"
		).val();
		const newModFilter = $(
			"#wpv_control_select_wpcf-testimonial-mod"
		).val();
		const newStarsFilter = $(
			'input[name="wpv-wpcf-testimonial-course-stars"]:checked'
		).val();

		// Check if any filter value has changed
		if (
			newCourseFilter !== currentCourseFilter ||
			newModFilter !== currentModFilter ||
			newStarsFilter !== currentStarsFilter
		) {
			// Update the current filter values
			currentCourseFilter = newCourseFilter;
			currentModFilter = newModFilter;
			currentStarsFilter = newStarsFilter;

			// Call the updateHistogram function (which sends the AJAX request)
			updateHistogram();
		}
	}

	// Function to update the histogram
	function updateHistogram() {
		const data = {
			action: "sbma_reviews_histogram_ajax_action",
			course: currentCourseFilter,
			mod: currentModFilter,
			stars: currentStarsFilter,
			nonce: reviews_histogram_ajax_obj.nonce,
		};

		$.ajax({
			type: "POST",
			url: reviews_histogram_ajax_obj.ajax_url,
			data: data,
			success: function (response) {
				if (response && response.html) {
					$(".reviews-histogram").html(response.html);
				} else {
					console.error("AJAX error updating histogram:", response);
				}
			},
			error: function (error) {
				console.error("AJAX request failed:", error);
			},
		});
	}

	// Attach event listener to the form to detect changes in filters
	$(".wpv-filter-form").on(
		"change",
		".js-wpv-filter-trigger",
		handleFilterChange
	);

	// Initial logging of filter values
	console.log(
		"Initial courseFilter:",
		$("#wpv_control_select_wpcf-testimonial-course").val()
	);
	console.log(
		"Initial modFilter:",
		$("#wpv_control_select_wpcf-testimonial-mod").val()
	);
	console.log(
		"Initial starsFilter:",
		$('input[name="wpv-wpcf-testimonial-course-stars"]:checked').val()
	);
});
