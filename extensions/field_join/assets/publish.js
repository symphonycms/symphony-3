jQuery(document).ready(function() {
	var $ = jQuery;
	
	$('.field-join .label')
		.removeClass('label')
		.addClass('field-join-box')
		.insertAfter('form > .columns');
	
	$('.field-join').remove();
	
	$('.field-join-box .context-switch')
		.live('change', function() {
			$(this)
				.siblings('.context')
				.hide()
				.filter('.context-' + $(this).val())
				.show();
		})
		
		.trigger('change');
	
	$('.field-join-box .context')
		.live('change', function() {
			$(this)
				.siblings('.context-switch')
				.attr('disabled', 'disabled');
		});
});