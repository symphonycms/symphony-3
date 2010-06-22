jQuery(document).ready(function() {
	var unique_id = Date.now();
	var input = jQuery('<input />')
		.attr('type', 'hidden')
		.attr('name', 'UPLOAD_IDENTIFIER')
		.attr('value', unique_id)
		.prependTo(jQuery('form'));
	var indicator = jQuery('<div />')
		.addClass('progress')
		.append('<div />')
		.insertAfter(jQuery('form > .actions button:first'))
		.hide();
	var timer = null;
	
	// Start upload:
	jQuery('form').bind('submit', function() {
		timer = setInterval(
			function() {
				jQuery.ajax({
					async:		false,
					url:		'/symphony/3.0/upload-status.php?for=' + unique_id,
					success:	function(data) {
						if (data == null) {
							clearInterval(timer);
							
							indicator.hide();
							indicator.children().css('width', '0');
							
							return false;
						}
						
						indicator.show();
						indicator.children().css('width', (100 * data.bytes_uploaded / data.bytes_total) + '%');
					}
				});
			},
			2000
		);
	});
});