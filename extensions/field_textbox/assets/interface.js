/*-----------------------------------------------------------------------------
	Text Box Interface
-----------------------------------------------------------------------------*/
	
	jQuery.fn.filterTextBox = function(custom_settings) {
	/*-------------------------------------------------------------------------
		Initialise
	-------------------------------------------------------------------------*/
		
		var objects = jQuery(this);
		var settings = {
			input:			'> label > input, > .content > label > input',
			help:			'> p, > .content > p',
			filters:		'> ul > li, > .content > ul > li'
		};
		
		jQuery.extend(settings, custom_settings);
		
	/*-------------------------------------------------------------------------
		Objects
	-------------------------------------------------------------------------*/
		
		objects = objects.map(function() {
			var object = jQuery(this);
			var message = '';
			var widgets = {
				container:	 	null,
				input:	 		null,
				help:	 		null,
				filters:		null
			};
			var methods = {
				filters:		function() {
					var filter = jQuery(this);
					var input = widgets.input.get(0);
					var value = widgets.input.val();
					var match = new RegExp('^' + filter.attr('title') + '\\s*');
					
					var selection_start = input.selectionStart;
					var selection_diff = input.selectionEnd - selection_start;
					var selection_track = true;
					
					if (match.test(value)) {
						var matched = match.exec(value)[0];
						
						value = value.replace(match, '');
						
						if (selection_start < matched.length) {
							selection_track = false;
						}
						
						else {
							selection_start = selection_start - matched.length;
						}
					}
					
					else {
						widgets.filters.each(function() {
							var current_filter = jQuery(this);
							var current_match = new RegExp('^' + current_filter.attr('title') + '\\s*');
							
							if (filter != current_filter && current_match.test(value)) {
								var matched = current_match.exec(value)[0];
								
								value = value.replace(current_match, '');
								current_filter.removeClass('selected');
								
								if (selection_start < matched.length) {
									selection_track = false;
								}
								
								else {
									selection_start = selection_start - matched.length;
								}
							}
						});
						
						value = filter.attr('title') + ' ' + value;
						
						if (selection_track) {
							selection_start = selection_start + filter.attr('title').length + 1;
						}
					}
					
					widgets.input.val(value).focus();
					
					if (selection_track) {
						input.selectionStart = selection_start;
						input.selectionEnd = selection_start + selection_diff;
					}
					
					else {
						input.selectionStart = 0;
						input.selectionEnd = 0;
					}
					
					methods.refresh();
					
					return false;
				},
				
				refresh:		function() {
					var value = widgets.input.val();
					var matched = false;
					
					widgets.filters.each(function() {
						var filter = jQuery(this);
						var match = new RegExp('^' + filter.attr('title') + '\\s*');
						
						if (match.test(value)) {
							matched = true;
							
							filter.addClass('selected');
							widgets.help.html(filter.attr('alt'));
						}
						
						else {
							filter.removeClass('selected');
						}
					});
					
					if (!matched) widgets.help.html(message);
				},
				
				silence:		function() { return false; }
			};
			
			// Initialize objects:
			widgets.container = object.addClass('initialised');
			widgets.input = object.find(settings.input);
			widgets.input.keyup(methods.refresh);
			
			// Has help?
			widgets.help = object.find(settings.help);
			message = widgets.help.html();
			
			// Has filters?
			widgets.filters = object.find(settings.filters);
			widgets.filters.mousedown(methods.silence);
			widgets.filters.click(methods.filters);
			
			methods.refresh();
			
			return object;
		});
		
		return objects;
	};
	
/*---------------------------------------------------------------------------*/