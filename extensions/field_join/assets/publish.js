jQuery(document).ready(function() {
	var $ = jQuery;
	
	// Initialise:
	$('.field-join .label')
		.removeClass('label')
		.addClass('field-join-box')
		.insertAfter('form > .columns');
	
	$('.field-join-box')
		.prepend(
			$('<h2 />')
				.append($('.field-join-box > span'))
		);
	
	$('.field-join').remove();
	
	$('.field-join-box .context-switch')
		.appendTo($('.field-join-box > h2'));
	
	// Toggle contexts:
	$('.field-join-box .context-switch')
		.live('change', function() {
			console.log('wtf');
			$(this)
				.closest('.field-join-box')
				.find('.context')
				.hide()
				.filter('.context-' + $(this).val())
				.show();
		})
		
		.trigger('change');
	
	// Disable switching on change:
	$('.field-join-box .context')
		.live('change', function() {
			$(this)
				.siblings('.context-switch')
				.attr('disabled', 'disabled');
		});
		
});