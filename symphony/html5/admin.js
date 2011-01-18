jQuery(document).ready(function($) {
	// Toggle navigation:
	$('body > header > nav > ul > li > a')
		.addClass('toggle')
		
		.live('mousedown', function() {
			$(this)
				.toggleClass('hidden')
				.siblings('ul')
				.animate({
					height: 'toggle',
				});
			
			return false;
		});
});