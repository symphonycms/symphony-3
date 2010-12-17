jQuery(document).ready(function($) {

	var button = $('#drawer-action');
	var text = $('#drawer-action').text();
	var docs = $('#drawer');

	// Show documentation
	button.click(function(event) {

		event.preventDefault();
		var target = $(event.target);

		// Close documentation
		if(target.hasClass('active')) {
			docs.children().hide();
			docs.animate({
				width: '0'
				}, 'fast');
			$(this).text(text).attr('title','View Drawer');
		}

		// Open documentation
		else {
			docs.animate({
				width: '300px'
				}, 'fast');
			docs.children().show();
			$(this).text('Close').attr('title', 'Hide Drawer');
		}

		// Save current state
		target.toggleClass('active');

	});

	// When another JS event resizes the page, adjust docs height
	$('form').resize(function(){
		var height = $(this).height();
		docs.css('height',height);
	});

	// Navigation stuff

	// View options

	$('#filter-action').click(function(event) {
		$('.tools').animate({
			height: 'toggle',
		});
	});

	$('#nav .toggle').click(function(event) {
		event.preventDefault();

		if ($(this).siblings('ul').is(':visible')){
			$(this).html('&#9666;');
		} else {
			$(this).html('&#9662;');
		}
		$(this).siblings('ul').animate({
			height: 'toggle',
		});
	});

});