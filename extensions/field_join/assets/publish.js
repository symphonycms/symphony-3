jQuery(document).ready(function() {
	var $ = jQuery;
	
	// Initialise:
	$('.field-join')
		.each(function() {
			var field = $(this).remove();
			var box = $('<div />')
				.addClass('section-join-box')
				.insertAfter('form > .columns');
			var contexts = field.children('.context');
			
			// Load data attributes:
			field.data().label = field.attr('data-label');
			field.data().optional = field.attr('data-optional');
			
			// Create box header:
			var header = $('<h2 />')
				.text(field.data().label)
				.appendTo(box);
			var select = $('<select />')
				.appendTo(header);
			
			// Add optional select item:
			if (field.data().optional) {
				// TODO...
			}
			
			// Create context selector:
			contexts.each(function() {
				var context = $(this)
					.hide()
					.appendTo(box);
				var handle = context.attr('data-handle');
				var name = context.attr('data-name');
				
				$('<option />')
					.text(name)
					.val(handle)
					.appendTo(select);
			});
		});
	
	// Toggle contexts:
	$('.section-join-box > h2 select')
		.live('change', function() {
			$(this)
				.closest('.section-join-box')
				.find('.context')
				.hide()
				.filter('[data-handle = ' + $(this).val() + ']')
				.show();
		})
		
		.trigger('change');
	
	// Disable switching on change:
	$('.section-join-box .context')
		.live('change', function() {
			$(this)
				.siblings('.context-switch')
				.attr('disabled', 'disabled');
		});
	
	// Remove unused section:
	$('form')
		.live('submit', function() {
			$('.section-join-box .context:not(:visible)').remove();
		});
});