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
			});
			
			object.bind('selectable-refresh', function() {
				var from = Math.min(state.from, state.to);
				var to = Math.max(state.from, state.to) + 1;
				var slice = items.slice(from, to);
				
				items.removeClass('changing');
				slice.addClass('changing');
			});
			
			// Start:
			items.live('mousedown', function() {
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
			
			/*
			var items = object.find(settings.items);
			var last = null;
			
			object.bind('selectable-refresh', function() {
				items.each(function() {
					var item = jQuery(this);
					var input = item.find('input:first');
					
					input.get(0).checked = item.is('.selected');
				});
			});
			
			// Prevent shift clicking from selecting text:
			items.live('mousedown', function(e) {
				return !e.shiftKey;
			});
			
			items.live('click', function(e) {
				var self = jQuery(this);
				
				// Ignore link clicks:
				if (e.target instanceof HTMLAnchorElement) {
					return true;
				}
				
				if (e.shiftKey) {
					var state = self.is('.selected');
					var from = items.index(last);
					var to = items.index(self);
					var slice = items.slice(
						Math.min(from, to),
						Math.max(from, to)
					).add(self);
					
					if (last) {
						state = last.is('.selected');
						last = null;
					}
					
					if (state) {
						slice.addClass('selected');
					}
					
					else {
						slice.removeClass('selected');
					}
				}
				
				else {
					last = self.toggleClass('selected');
				}
				
				object.trigger('selectable-refresh');
			});
			*/

			return object;
		});

		return objects;
	};

/*---------------------------------------------------------------------------*/