jQuery(function($) {
	var courseFilter = $('[name="wpv-wpcf-testimonial-course"]');
	var starsFilter = $('[name="wpv-wpcf-testimonial-course-stars"]');
	var modFilter = $('[name="wpv-wpcf-testimonial-mod"]');
	var filters = [courseFilter, starsFilter, modFilter];

	// Listen for changes on the filters
	filters.forEach(function(filter) {
		filter.change(function() {
			var data = {
				action: 'get_reviews_histogram_handler', 
				course: courseFilter.val(),
				mod: modFilter.val(),
			};

			$.post(MyAjax.ajaxurl, data, function(response) {
				// Update the histogram with the new data
				$('.reviews-histogram').replaceWith(response.html);
			});
		});
	});
});
