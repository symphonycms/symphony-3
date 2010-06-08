// Update form when the Name field is changed
jQuery(document).ready(function() {
	var name_field = jQuery('input[name = "fields[about][name]"]');
	var param_field = jQuery('select[name = "fields[parameter-output][]"]');
	
	param_field.find('option').each(function() {
		var option = jQuery(this);
		var template = option.text();
		
		name_field.bind('change', function() {
			var name = Symphony.Language.createHandle(name_field.val());
			
			if (!name) option.text(template);
			else option.text(template.split('?').join(name));
		});
	});
	
	name_field.trigger('change');
});

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
		jQuery('.filtering-duplicator.context > .content > .instances > li').each(function(index) {
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