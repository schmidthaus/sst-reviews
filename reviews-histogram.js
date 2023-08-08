// Reviews Histogram AJAX functionality v3.5.4
jQuery(function($) {
	var courseFilter = $('[name="wpv-wpcf-testimonial-course"]');
	var modFilter = $('[name="wpv-wpcf-testimonial-mod"]');
	var starsFilter = $('[name="wpcf-testimonial-course-stars"]');
	var filters = [courseFilter, modFilter, starsFilter];

	// Function to handle filter change
	function handleFilterChange() {
		var data = {
			action: 'get_reviews_histogram',
			'course-dynamic': courseFilter.val() ? courseFilter.val() : null,
			'mod-dynamic': modFilter.val() ? modFilter.val() : null,
			'stars-dynamic': starsFilter.val() ? starsFilter.val() : null,
			security: ajax_object.security // Use nonce for added security
		};

		// Insert the check here
		if (!data.security || data.security === "") {
			console.error("Nonce security token is missing.");
			return;
		}

		$.post(ajax_object.ajax_url, data, function(response) { 
			// Update the histogram with the new data only if the HTML is not empty
			if(response && response.html && response.html.trim() !== "") {
				$('.reviews-histogram').replaceWith(response.html); 
			} else {
				console.error('Error updating histogram: ', response);
			}
		}).fail(function(jqXHR, textStatus, errorThrown){
			console.error('Error requesting histogram: ', textStatus, errorThrown);
		});
	}

	// Debounce function
	function debounce(func, wait) {
		var timeout;
	
		return function executedFunction() {
			var context = this;
			var args = arguments;
			
			var later = function() {
				timeout = null;
				func.apply(context, args);
			};
	
			clearTimeout(timeout);
			timeout = setTimeout(later, wait);
		};
	}
	
	// Listen for changes on the filters
	filters.forEach(function(filter) {
		filter.change(debounce(handleFilterChange, 1000)); // debouncing the event handler to 1000 ms
	});

	// Initial call to handleFilterChange to ensure histogram is updated on page load
	handleFilterChange();
});

