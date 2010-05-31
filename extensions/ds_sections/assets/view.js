jQuery(document).ready(function() {
	var update = function() {
		jQuery('.context').hide()
			.filter('.context-' + this.value)
			.show();
	};
	
	jQuery('#context')
		.bind('change', update)
		.bind('keyup', update).change();
	
	jQuery(document).bind('submit', function() {
		var expression = /^fields\[filters\]\[[0-9]+\]\[(.*)]$/;
		
		// Cleanup old contexts:
		jQuery('.context:not(:visible)').remove();
		
		// Set filter names:
		jQuery('.duplicator-widget.context > .content > .instances > li').each(function(index) {
			var instance = jQuery(this);
			
			instance.find('[name]').each(function() {
				var input = jQuery(this);
				var name = input.attr('name');
				var match = null;
				
				// Extract name:
				if (match = name.match(expression)) name = match[1];
				
				input.attr(
					'name',
					'fields[filters]['
					+ index
					+ ']['
					+ name
					+ ']'
				);
			});
		});
	});
});