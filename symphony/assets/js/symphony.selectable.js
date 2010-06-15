/*-----------------------------------------------------------------------------
	Selectable plugin
-----------------------------------------------------------------------------*/

	jQuery.fn.symphonySelectable = function(custom_settings) {
		var objects = this;
		var settings = {
			items:				'tr:has(input)'
		};
		
		jQuery.extend(settings, custom_settings);
		
	/*-----------------------------------------------------------------------*/
		
		objects = objects.map(function() {
			var object = jQuery(this);
			var items = object.find(settings.items);
			var state = null;
			
			object.bind('selectable-change', function() {
				var from = Math.min(state.from, state.to);
				var to = Math.max(state.from, state.to) + 1;
				var slice = items.slice(from, to);
				
				items.removeClass('changing');
				
				if (state.action == 'selecting') {
					slice.addClass('selected');
				}
				
				else {
					slice.removeClass('selected');
				}
				
				items.each(function() {
					var item = jQuery(this);
					var input = item.find('input:first');
					
					input.get(0).checked = item.is('.selected');
				});
			});
			
			object.bind('selectable-refresh', function() {
				var from = Math.min(state.from, state.to);
				var to = Math.max(state.from, state.to) + 1;
				var slice = items.slice(from, to);
				
				items.removeClass('changing');
				slice.addClass('changing');
			});
			
			// Ignore text selection:
			items.live('selectstart', function() {
				return false;
			});
			
			// Start:
			items.live('mousedown', function(event) {
				if (event.button != (jQuery.browser.msie != undefined) || event.target.tagName.toLowerCase() == 'a') {
					items.removeClass('changing');
					state = null;
					return;
				}
				
				var item = jQuery(this);
				
				state = {
					action: 'selecting',
					from:	items.index(item),
					to:		items.index(item)
				};
				
				if (item.hasClass('selected')) {
					state.action = 'deselecting';
				}
				
				return false;
			});
			
			// Dragging:
			items.live('mouseover', function() {
				if (state == null) return;
				
				var item = jQuery(this);
				
				state.to = items.index(item);
				
				object.trigger('selectable-refresh');
				
				return false;
			});
			
			// Stop:
			jQuery(document).bind('mouseup', function() {
				if (state == null) return;
				
				object.trigger('selectable-change');
				
				state = null;
				
				return false;
			});

			return object;
		});

		return objects;
	};

/*---------------------------------------------------------------------------*/