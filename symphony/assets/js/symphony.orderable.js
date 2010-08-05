/*-----------------------------------------------------------------------------
	Orderable plugin
-----------------------------------------------------------------------------*/
	
	jQuery(document).ready(function() {
		var $ = jQuery;
		var state = null;
		var start = function() {
			state = {
				item:		$(this).closest('.orderable-item'),
				object:		$(this).closest('.orderable-widget'),
				min:		null,
				max:		null,
				delta:		0
			};
			
			$(document).bind('mouseup', stop);
			
			return false;
		};
		var move = function(event) {
			if (state == null) return true;
			
			var item = state.item;
			var object = state.object;
			var top = event.pageY;
			var a = item.height();
			var b = item.offset().top;
			
			var items = object.find('.orderable-item');
			var before = items.slice(0, items.index(item));
			var after = items.slice(items.index(item) + 1, items.length);
			
			var prev = before.last();
			var next = after.first();
			var current, target;
			
			state.min = Math.min(b, a + (prev.size() > 0 ? prev.offset().top : -Infinity));
			state.max = Math.max(a + b, b + (next.height() || Infinity));
			
			if (!object.is('.ordering')) {
				object.addClass('ordering');
				item.addClass('ordering')
					.trigger('orderable-started');
			}
			
			if (top < state.min) {
				target = prev;
				index = items.index(target);
				
				while (true) {
					current = items.eq(--index);
					state.delta--;
					
					if (current.length === 0 || top >= (state.min -= current.height())) {
						item.insertBefore(target); break;
					}
					
					target = current;
				}
			}
			
			else if (top > state.max) {
				target = next;
				index = items.index(target);
				
				while (true) {
					current = items.eq(++index);
					state.delta++;
					
					if (current.length === 0 || top <= (state.max += current.height())) {
						item.insertAfter(target); break;
					}
					
					target = current;
				}
			}
			
			item.trigger('orderable-changed');
			
			return false;
		};
		var stop = function() {
			if (state == null) return true;
			
			$(document).unbind('mouseup', stop);
			
			state.object.removeClass('ordering');
			state.item.removeClass('ordering')
				.trigger('orderable-ordered');
			state = null;
			
			return false;
		};
		
		$('.orderable-widget .orderable-handle')
			.live('orderable-start', start)
			.live('orderable-move', move)
			.live('orderable-stop', stop)
			
			// Click actions:
			.live('mousedown', function(event) {
				$(this).trigger('orderable-start');
			})
			.live('mousemove', function(event) {
				var new_event = jQuery.Event('orderable-move');
				
				new_event.pageY = event.pageY;
				
				$(this).trigger(new_event);
			})
			.live('mouseup', function(event) {
				$(this).trigger('orderable-stop');
			});
	});
	
/*---------------------------------------------------------------------------*/
