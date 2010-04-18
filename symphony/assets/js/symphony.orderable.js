/*-----------------------------------------------------------------------------
	Orderable plugin
-----------------------------------------------------------------------------*/
	
	jQuery.fn.symphonyOrderable = function(custom_settings) {
		var objects = this;
		var settings = {
			items:				'li',
			handles:			'> *'
		};
		
		jQuery.extend(settings, custom_settings);
		
	/*-----------------------------------------------------------------------*/
		
		objects = objects.map(function() {
			var object = jQuery(this).addClass('orderable-widget');
			
			var find = function(selector) {
				return object.find(selector);
			};
			var block = function() {
				return false;
			};
			
		/*-------------------------------------------------------------------*/
			
			find('*').live('orderable-item-initialize', function() {
				var item = jQuery(this);
				var handle = item.find(settings.handles);
				
				if (handle.length == 0) handle = item;
				
				var handle = jQuery(this);
				var index = handle.prevAll().length;
				var item = find(settings.items + ':eq(' + index + ')');
				var state = null;
				
				var action_start = function(event) {
					state = {
						min:		null,
						max:		null,
						delta:		0
					};
					
					jQuery(document)
						.bind('mousemove', action_change)
						.bind('mouseup', action_stop);
					
					return false;
				};
				
				var action_change = function(event) {
					var target, next, top = event.pageY;
					var a = item.height();
					var b = item.offset().top;
					var prev = item.prev();
					
					state.min = Math.min(b, a + (prev.size() > 0 ? prev.offset().top : -Infinity));
					state.max = Math.max(a + b, b + (item.next().height() ||  Infinity));
					
					if (!object.is('.ordering')) {
						object.addClass('ordering');
						item.addClass('ordering')
							.trigger('orderable-started');
					}
					
					if (top < state.min) {
						target = item.prev();
						
						while (true) {
							state.delta--;
							next = target.prev();
							
							if (next.length === 0 || top >= (state.min -= next.height())) {
								item.insertBefore(target); break;
							}
							
							target = next;
						}
					}
					
					else if (top > state.max) {
						target = item.next();
						
						while (true) {
							state.delta++;
							next = target.next();
							
							if (next.length === 0 || top <= (state.max += next.height())) {
								item.insertAfter(target); break;
							}
							
							target = next;
						}
					}
					
					item.trigger('orderable-moved');
					
					return false;
				};
				
				var action_stop = function(event) {
					jQuery(document)
						.unbind('mousemove', action_change)
						.unbind('mouseup', action_stop);
					
					if (state != null) {
						object.removeClass('ordering');
						item.removeClass('ordering')
							.trigger('orderable-completed', [item]);
						state = null;
					}
					
					return false;
				};
				
				handle.data('index', index);
				handle.data('item', item);
				
				// Unbind any accidents:
				jQuery(document).unbind('mousemove', action_change);
				jQuery(document).unbind('mouseup', action_stop);
				
				handle.bind('mousedown', action_start);
			});
			
		/*-------------------------------------------------------------------*/
			
			find(settings.items)
				.trigger('orderable-item-initialize');
		});
		
		return objects;
	};
	
/*---------------------------------------------------------------------------*/
