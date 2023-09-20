// Reviews Histogram AJAX event handler v3.6.2

jQuery(function ($) {
	// Retrieve Filter values from URL parameters
	var courseFilter = $('[name="wpv-wpcf-testimonial-course"]');
	var modFilter = $('[name="wpv-wpcf-testimonial-mod"]');
	var starsFilter = $('[name="wpcf-testimonial-course-stars"]');
	var filters = [courseFilter, modFilter, starsFilter];

	console.log("Initial courseFilter: " + courseFilter);
	console.log("Initial modFilter: " + modFilter);
	console.log("Initial starsFilter: " + starsFilter);

	// Debounce function
	function debounce(func, wait) {
		var timeout;

		return function executedFunction() {
			var context = this;
			var args = arguments;

			var later = function () {
				timeout = null;
				func.apply(context, args);
			};

			clearTimeout(timeout);
			timeout = setTimeout(later, wait);
		};
	}

	// Listen for changes on the filters
	filters.forEach(function (filter) {
		filter.change(debounce(handleToolsetViewFilterChange, 1000)); // debouncing the event handler to 1000 ms
	});

	// Trigger the histogram update whenever any AJAX request completes
	jQuery(document).ajaxComplete(function () {
		console.log("ajaxComplete event triggered.");
		handleToolsetViewFilterChange();
	});

	// Handle Toolset View Filter changes
	function handleToolsetViewFilterChange() {
		console.log("Enter handleToolsetViewFilterChange function.");
		jQuery.ajax({
			url: reviews_histogram_ajax_obj.ajax_url,
			type: "post",
			data: {
				action: "sbma_reviews_histogram_ajax_action",
				nonce: reviews_histogram_ajax_obj.nonce,
				// Other data to send with the request
				"course-dynamic": courseFilter.val()
					? courseFilter.val()
					: null,
				"mod-dynamic": modFilter.val() ? modFilter.val() : null,
				"stars-dynamic": starsFilter.val() ? starsFilter.val() : null,
			},
			success: function (response) {
				// Handle the response
				// Update the histogram with the new data only if the HTML is not empty
				if (response && response.html && response.html.trim() !== "") {
					$(".reviews-histogram").replaceWith(response.html);
					console.log("AJAX updated histogram: " + response.html);
				} else {
					console.error("AJAX error updating histogram: ", response);
				}
			},
		});
		console.log("Exit handleToolsetViewFilterChange function.");
	}
});
