/*---------------------------------------------------------------------------*/
	
	jQuery.fn.symphonyLayout = function(custom_settings) {
		var objects = this;
		var settings = {
		};
		
	/*-----------------------------------------------------------------------*/
		
		objects = objects.map(function() {
			var object = this;
			
			if (object instanceof jQuery === false) {
				object = jQuery(object);
			}
			
			var get_columns = function(filter) {
				var data = object.find('> .columns > *');
				
				if (filter) return data.filter(filter);
				
				return data;
			};
			var get_layout_id = function(element) {
				var layout_id = '';
				
				element.children().each(function(index) {
					var column = jQuery(this);
					
					if (index) layout_id += '|';
					
					if (column.is('.size-small')) {
						layout_id += 'small';
					}
					
					else if (column.is('.size-medium')) {
						layout_id += 'medium';
					}
					
					else if (column.is('.size-large')) {
						layout_id += 'large';
					}
				});
				
				return layout_id;
			};
			var get_layouts = function(filter) {
				var data = object.find('> .layouts > *');
				
				if (filter) return data.filter(filter);
				
				return data;
			};
			var get_fieldsets = function(filter) {
				var data = object.find('> .columns > * > fieldset');
				
				if (filter) return data.filter(filter);
				
				return data;
			};
			var get_fields = function(filter) {
				var data = object.find('> .columns > * > fieldset > .fields > li');
				
				if (filter) return data.filter(filter);
				
				return data;
			};
			var get_templates = function(filter) {
				var data = object.find('> .templates > li');
				
				if (filter) return data.filter(filter);
				
				return data;
			};
			
			var controls = object.find('> .controls');
			var columns = object.find('> .columns');
			var layouts = object.find('> .layouts');
			var active_layout_id = get_layout_id(columns);
			
		/*-------------------------------------------------------------------*/
			
			object.find('*').live('layout-initialize', function() {
				var layout = jQuery(this);
				
				if (!layout.data('layout-id')) {
					layout.data('layout-id', get_layout_id(layout.children()));
				}
				
				if (layout.data('layout-id') == active_layout_id) {
					layout.addClass('active');
				}
				
				else {
					layout.removeClass('active');
				}
				
				layout.bind('mousedown', function() {
					return false;
				});
			});
			
			object.find('*').live('layouts-hide', function() {
				object.find('> .layouts').hide();
			})
			
			object.find('*').live('layouts-show', function() {
				object.find('> .layouts').show();
			})
			
			object.find('*').live('layout-select', function() {
				var layout = jQuery(this);
				var old_columns = get_columns();
				var new_columns = layout.find('> div > *').clone();
				var parent = old_columns.parents('.columns');
				
				// Remove old columns:
				parent.empty();
				
				// Remove placeholder text:
				new_columns.find('span').remove();
				
				// Insert new columns:
				new_columns.each(function(index) {
					jQuery('<li />')
						.addClass(this.className)
						.appendTo(parent);
				});
				
				new_columns = get_columns();
				
				// Insert fieldsets:
				old_columns.filter(':lt(' + new_columns.length + ')').each(function(index) {
					jQuery(this).children(':not(.unchanged)').appendTo(new_columns[index]);
				});
				
				old_columns.filter(':gt(' + (new_columns.length - 1) + ')').each(function() {
					jQuery(this).children(':not(.unchanged)').each(function(index) {
						jQuery(this).appendTo(new_columns[index % new_columns.length]);
					});
				});
				
				active_layout_id = layout.data('layout-id');
			})
			
		/*-------------------------------------------------------------------*/
			
			object.find('*').live('column-initialize', function() {
				var column = jQuery(this);
				
				// Insert default fieldset:
				if (column.children().length == 0) {
					jQuery('<fieldset />').appendTo(column);
				}
				
				column.find('> fieldset').trigger('fieldset-initialize');
			});
			
		/*-------------------------------------------------------------------*/
			
			object.find('*').live('fieldset-initialize', function() {
				var fieldset = jQuery(this);
				
				// Fill with default content:
				if (fieldset.is(':empty')) {
					fieldset.addClass('unchanged');
					
					// Add header:
					jQuery('<input />')
						.attr('name', 'name')
						.attr('value', 'Untitled')
						.appendTo(
							jQuery('<h3 />').appendTo(fieldset)
						);
						
					// Keep track of name changes
					fieldset.find('> h3 > input').bind('change', function() {
						fieldset.trigger('fieldset-change');
					});
				}
				
				// Fields are missing?
				if (fieldset.find('> .fields').length == 0) {
					jQuery('<ol />')
						.addClass('fields')
						.appendTo(fieldset);
				}
				
				// Controls are missing?
				if (fieldset.find('> .controls').length == 0) {
					var controls = jQuery('<ol />')
						.addClass('controls')
						.appendTo(fieldset);
					
					jQuery('<li />')
						.addClass('menu')
						.html('<span>Add Item ▼</span>')
						.appendTo(controls);
					
					jQuery('<li />')
						.addClass('remove')
						.html('<span>×</span>')
						.appendTo(controls);
					
					controls.children().bind('mousedown', function() {
						return false;
					});
				}
				
				fieldset.find('.fields').sortable({
					connectWith:	'.fields',
					containment:	columns,
					cursorAt:		{ top: 15, left: 30 },
					distance:		10,
					items:			'li',
					placeholder:	'ui-sortable-highlight',
					tolerance:		'pointer',
					zindex:			1000,
					
					sort:			function() {
						jQuery('.ui-sortable-helper').css('height', 'auto');
					}
				});
				
				fieldset.find('> .fields > .field')
					.trigger('field-initialize');
				
				fieldset.trigger('fieldset-refresh');
			});
			
			object.find('*').live('fieldset-refresh', function() {
				var fieldset = jQuery(this);
				var remove = fieldset.find('> .controls > .remove');
				
				if (fieldset.siblings('fieldset').length) {
					remove.removeClass('disabled');
				}
				
				else {
					remove.addClass('disabled');
				}
			});
			
			object.find('*').live('fieldset-remove', function() {
				var fieldset = jQuery(this);
				var siblings = fieldset.siblings('fieldset');
				
				if (siblings.length) {
					fieldset.remove();
					siblings.trigger('fieldset-refresh');
				}
			});
			
			object.find('*').live('fieldset-change', function() {
				var fieldset = jQuery(this);
				
				fieldset.removeClass('unchanged');
			});
			
			object.find('*').live('fieldset-menu-show', function() {
				var button = jQuery(this);
				var fieldset = button.parents('fieldset:first');
				var position = button.position();
				var offset = button.offsetParent().position();
				var menu = jQuery('<div />')
					.addClass('menu');
				var fields = jQuery('<ol />');
				var other = jQuery('<ol />');
				var templates = get_templates().remove();
				var remove = function() {
					fieldset.trigger('fieldset-menu-hide');
					jQuery(window).unbind('mousedown', remove);
				};
				var add_fieldset = function() {
					jQuery('<fieldset />')
						.insertAfter(fieldset)
						.trigger('fieldset-initialize');
					
					fieldset.siblings().add(fieldset)
						.trigger('fieldset-refresh');
				};
				var add_field = function() {
					jQuery(this).appendTo(fieldset.find('.fields'))
						.unbind('mousedown', add_field)
						.trigger('field-initialize');
					
					fieldset.trigger('fieldset-change');
				};
				
				// Append fields:
				if (templates.length) {
					var items = templates.get();
					
					items.sort(function(a, b) {
						var text_a = Symphony.Language.createHandle(jQuery(a).text());
						var text_b = Symphony.Language.createHandle(jQuery(b).text());
						
						return (text_a < text_b) ? -1 : (text_a > text_b) ? 1 : 0;
					});
					
					items = jQuery(items)
						.bind('mousedown', add_field);
					
					fields
						.append(items)
						.appendTo(menu);
				}
				
				jQuery('<li />')
					.text('Fieldset')
					.bind('mousedown', add_fieldset)
					.appendTo(other);
				
				other.appendTo(menu);
				menu
					.css('left', (position.left + offset.left) + 'px')
					.css('top', (position.top + offset.top + 10) + 'px')
					.prependTo(object);
				
				jQuery(window).bind('mousedown', remove);
			});
			
			object.find('*').live('fieldset-menu-hide', function() {
				var fieldset = jQuery(this);
				var menu = object.find('> .menu');
				var templates = menu.find('ol:first > li');
				
				templates.appendTo(object.find('> .templates'));
				
				menu.remove();
			});
			
		/*-------------------------------------------------------------------*/
			
			object.find('*').live('field-initialize', function() {
				var field = jQuery(this).addClass('field');
				
				// Controls are missing?
				if (field.find('> .remove').length == 0) {
					jQuery('<a />')
						.addClass('remove')
						.text('×')
						.appendTo(field);
				}
				
				// Ignore mouse clicks:
				field.bind('mousedown', function() {
				//	return false;
				});
			});
			
			object.find('*').live('field-remove', function() {
				var field = jQuery(this);
				var fieldsets = field.parents('.column')
					.find('fieldset');
				var templates = object.find('> .templates');
				
				// Remove controls:
				field.find('.remove').remove();
				
				templates.prepend(field);
				fieldsets.trigger('fieldset-refresh');
			});
			
		/*-------------------------------------------------------------------*/
			
			// Add controls:
			jQuery('<div />')
				.addClass('controls')
				.append('<a class="choose">Choose Layout</a>')
				.bind('mousedown', function() { return false; })
				.prependTo(object);
			
			// Initialize fieldsets:
			get_layouts().trigger('layout-initialize');
			get_columns().trigger('column-initialize');
			
			// Change layout:
			get_layouts().bind('click', function() {
				jQuery(this).trigger('layout-select');
				get_layouts().trigger('layout-initialize');
				get_columns().trigger('column-initialize');
			});
			
			// Toggle layout selector:
			layouts.trigger('layouts-hide');
			
			object.find('.controls .choose').bind('click', function() {
				if (layouts.is(':visible')) {
					layouts.trigger('layouts-hide');
					jQuery(this).removeClass('visible');
				}
				
				else {
					layouts.trigger('layouts-show');
					jQuery(this).addClass('visible');
				}
			});
			
			// Show fieldset menu:
			object.find('.columns .column fieldset .controls .menu').live('click', function() {
				if (object.find('> .menu').length != 0) {
					jQuery(this).trigger('fieldset-menu-hide');
				}
				
				jQuery(this).trigger('fieldset-menu-show');
			});
			
			// Remove fieldsets and fields:
			object.find('.columns .column fieldset .remove').live('click', function() {
				var self = jQuery(this);
				
				// Remove field:
				if (self.parents('.field').length != 0) {
					self.parents('.field').trigger('field-remove');
				}
				
				else {
					self.parents('fieldset').trigger('fieldset-remove');
				}
				
				return false;
			});
		});
	};
	
	jQuery(document).ready(function() {
		jQuery('.layout-widget').symphonyLayout();
	});
	
/*---------------------------------------------------------------------------*/
/**
 * Get the current coordinates of the first element in the set of matched
 * elements, relative to the closest positioned ancestor element that
 * matches the selector.
 * @param {Object} selector
 */
 /*
jQuery.fn.positionAncestor = function(selector) {
    var left = 0;
    var top = 0;
    this.each(function(index, element) {
        // check if current element has an ancestor matching a selector
        // and that ancestor is positioned
        var $ancestor = $(this).closest(selector);
        if ($ancestor.length && $ancestor.css("position") !== "static") {
            var $child = $(this);
            var childMarginEdgeLeft = $child.offset().left - parseInt($child.css("marginLeft"), 10);
            var childMarginEdgeTop = $child.offset().top - parseInt($child.css("marginTop"), 10);
            var ancestorPaddingEdgeLeft = $ancestor.offset().left + parseInt($ancestor.css("borderLeftWidth"), 10);
            var ancestorPaddingEdgeTop = $ancestor.offset().top + parseInt($ancestor.css("borderTopWidth"), 10);
            left = childMarginEdgeLeft - ancestorPaddingEdgeLeft;
            top = childMarginEdgeTop - ancestorPaddingEdgeTop;
            // we have found the ancestor and computed the position
            // stop iterating
            return false;
        }
    });
    return {
        left:    left,
        top:    top
    }
};
	
	jQuery(document).ready(function() {
		var $ = jQuery;
		var layout = $('.layout');
		var templates = layout
			.children('.templates')
			.remove().children();
		
	/*----------------------------------------------------------------------------
		Fieldset events
	----------------------------------------------------------------------------*/
		/*
		// Initialize fieldset:
		layout.find('*').live('fieldset-initialize', function() {
			var fieldset = $(this).addClass('fieldset');
			var lines = fieldset.children('ol');
			
			fieldset.sortable({
				connectWith:	'.fieldset',
				cursorAt:		{ top: 15, left: 10 },
				distance:		10,
				items:			'.line',
				placeholder:	'ui-sortable-highlight',
				tolerance:		'pointer',
				zindex:			1000,
				
				start:			function() {
					fieldset
						.find('.ui-sortable-highlight')
						.addClass('line');
				},
				
				stop:			function() {
					if (fieldset.find('> .line').length == 0) {
						fieldset.trigger('fieldset-remove');
					}
				}
			});
			
			if (lines.length == 0) {
				$('<ol />').appendTo(fieldset);
				lines = fieldset.children('ol');
			}
			
			lines.trigger('line-initialize')
				.children('li:not(.control)')
				.trigger('field-initialize');
			
			layout.find('> .content > .fieldset > .line').trigger('line-refresh');
			
			return false;
		});
		
		// Remove fieldset:
		layout.find('.fieldset').live('fieldset-remove', function() {
			var fieldset = $(this);
			
			if (fieldset.parent().find('.fieldset').length == 1) return;
			
			fieldset.remove();
			
			// Reselect field:
			if (fieldset.find('.field.selected').length) {
				layout.find('> .settings').remove();
				layout.find('> content > .fieldset .field:first')
					.trigger('field-edit-start');
			}
			
			layout.find('> .content > .fieldset > .line')
				.trigger('line-refresh');
			
			return false;
		});
		*/
	/*----------------------------------------------------------------------------
		Line events
	----------------------------------------------------------------------------*/
		/*
		// Initialize lines:
		layout.find('*').live('line-initialize', function() {
			var line = $(this).addClass('line');
			
			line.prepend('<li class="control remove"><span class="title">×</span></li>');
			line.prepend('<li class="control dropdown"><span class="title">+</span></li>');
			
			line.sortable({
				connectWith:	'.fieldset .line',
				cursorAt:		{ top: 15, left: 10 },
				distance:		10,
				items:			'li:not(.control)',
				placeholder:	'ui-sortable-highlight',
				tolerance:		'pointer',
				zindex:			1000,
				
				sort:			function() {
					var helper = $('.ui-sortable-helper');
					var highlight = $('.layout .ui-sortable-highlight');
					
					highlight.css({
						'-moz-box-flex':	helper.css('-moz-box-flex'),
						'-webkit-box-flex':	helper.css('-webkit-box-flex')
					});
					helper.height(highlight.height());
					helper.width(highlight.width());
					
					layout.find('> .content > .fieldset > .line')
						.trigger('line-refresh');
				},
				
				stop:			function() {
					layout.find('> .content > .fieldset > .line')
						.trigger('line-refresh');
				}
			});
			
			layout.find('> .content > .fieldset > .line')
				.trigger('line-refresh');
			
			return false;
		});
		
		// Refresh lines:
		layout.find('.line').live('line-refresh', function() {
			var line = $(this), hide = false;
			
			if (layout.find('> .content > .fieldset > .line').length == 1) {
				hide = layout.find('> .content > .fieldset > .line > :not(.control)').length > 0;
				
				if (layout.find('> .content > .fieldset').length) hide = true;
			}
			
			else if (line.children(':not(.control)').length) {
				hide = true;
			}
			
			if (hide) line.children('.control.remove').hide();
			else line.children('.control.remove').show();
			*/
		/*---------------------------------------------------------------------------
			
			TODO: Change input names just like the duplicator does,
			so the inputs in the first field would have names like:
			
				field[1][label]
			
			And fields in the second:
			
				field[2][label]
			
			Remember that the .selected field is a special case.
			
		---------------------------------------------------------------------------*/
			/*
			return false;
		});
		
		// Remove line:
		layout.find('.line').live('line-remove', function() {
			var line = $(this);
			
			if (line.parent().children('.line').length > 1) {
				line.remove();
				
				// Reselect field:
				if (line.find('.field.selected').length) {
					$('.layout > .settings').remove();
					$('.fieldset .field:first')
						.trigger('field-edit-start');
				}
				
				layout.find('> .content > .fieldset > .line')
					.trigger('line-refresh');
			}
			
			else {
				line.parent().trigger('fieldset-remove');
			}
			
			return false;
		});
		
		// Line menu:
		layout.find('.line').live('line-menu-start', function() {
			var line = $(this);
			var position = line.position();
			var fieldset = line.parents('.fieldset');
			
			var menu = $('<div />').addClass('menu');
			var fields = $('<ol />').appendTo(menu);
			var actions = $('<ol />').appendTo(menu);
			
			// Build fields menu:
			$(templates).each(function() {
				var template = $(this);
				var name = template.find('h3:first').text();
				var wrap = $('<div />').addClass('settings');
				var after = line.children('.control:last');
				
				// Insert after the selected element:
				if (line.children('.selected').length) {
					after = line.children('.selected');
				}
				
				$('<li />')
					.text(name)
					.appendTo(fields)
					.bind('click', function() {
						$('.fieldset .field.selected')
							.trigger('field-edit-stop');
						
						template.clone()
							.wrapInner(wrap)
							.insertAfter(after)
							.trigger('field-initialize')
							.trigger('field-edit-start');
						
						menu.remove();
					});
			});
			
			// Insert line after:
			$('<li />')
				.text('Row')
				.appendTo(actions)
				.bind('click', function() {
					$('<ol />').insertAfter(line)
						.trigger('line-initialize');
					
					menu.remove();
				});
			
			// Insert fieldset after:
			$('<li />')
				.text('Fieldset')
				.appendTo(actions)
				.bind('click', function() {
					$('<div />')
						.append($('<h3 />').append($('<input />').val('Unknown')))
						.append($('<ol />'))
						.insertAfter(fieldset)
						.trigger('fieldset-initialize');
					
					menu.remove();
				});
			
			
			// Mozilla stuffs up positions:
			if ($.browser.mozilla = true) {
				// TODO: Find out where this gap is coming from and what versions of Firefox it applies to.
				position.top -= 15;
			}
			
			// Show menu:
			menu.appendTo('.layout')
				.show()
				.css({
					'top':	position.top + 'px',
					'left':	position.left + 'px'
				});
			
			return false;
		});
		*/
	/*----------------------------------------------------------------------------
		Field events
	----------------------------------------------------------------------------*/
		/*
		// Initialize field:
		layout.find('*').live('field-initialize', function() {
			var field = $(this).addClass('field');
			var settings = field.find('.settings');
			var title = $('<span />')
				.addClass('title')
				.prependTo(field);
			var change = function() {
				var label = settings.find('.field-label input');
				var flex = 2;
				
				switch (settings.find('.field-flex select').val()) {
					case '1': flex = 1; break;
					case '2': flex = 2; break;
					case '3': flex = 4; break;
				}
				
				title.text(label.val() || 'Unknown');
				field.css({
					'-moz-box-flex':		'' + flex,
					'-webkit-box-flex':		'' + flex
				});
			};
			
			settings
				.bind('change', change)
				.bind('keyup', change);
			
			$('<span />')
				.addClass('remove-field')
				.text('×')
				.appendTo(field);
			
			change();
			
			layout.find('> .content > .fieldset > .line')
				.trigger('line-refresh');
			
			return false;
		});
		
		// Remove field:
		layout.find('.field').live('field-remove', function() {
			var self = $(this);
			var line = self.parent();
			var fields = layout.find('.field');
			var next, prev;
			
			fields.each(function(index) {
				if (this == self.get(0)) {
					next = $(fields.get(index + 1));
					prev = $(fields.get(index - 1));
				}
			});
			
			self.remove();
			
			if (self.is('.selected')) {
				var select = $('.fieldset .field:first');
				
				// Select the next field:
				if (next.length > 0) select = next;
				
				// Select the previous field:
				else if (prev.length > 0) select = prev;
				
				$('.layout > .settings').remove();
				select.trigger('field-edit-start');
			}
			
			layout.find('> .content > .fieldset > .line')
				.trigger('line-refresh');
			
			return false;
		});
		
		// Show the settings editor:
		layout.find('.field').live('field-edit-start', function() {
			var self = $(this).addClass('selected');
			var settings = self.find('.settings');
			
			layout.append(settings);
			settings
				.find('input:first')
				.focus();
			
			return false;
		});
		
		// Hide the settings editor:
		layout.find('.field').live('field-edit-stop', function() {
			var self = $(this).removeClass('selected');
			var settings = $('.layout > .settings');
			
			self.append(settings);
			
			return false;
		});
		*/
	/*----------------------------------------------------------------------------
		Triggers
	----------------------------------------------------------------------------*/
		/*
		// Ignore mouse clicks:
		layout.find('> .content > .fieldset .line').live('mousedown', function() {
			return false;
		});
		
		// Show menu:
		layout.find('> .content > .fieldset > .line > .control.dropdown').live('click', function() {
			layout.find('.menu:not(.control):visible').remove();
			
			$(this).parents('.line').trigger('line-menu-start');
			
			return false;
		});
		
		// Hide menu:
		$('html').live('click', function() {
			if (layout.find('.menu:not(.control):visible').remove().length) return false;
		});
		
		// Remove current line or fieldset:
		layout.find('> .content > .fieldset > .line > .control.remove').live('click', function() {
			$(this).parents('.line').trigger('line-remove');
			
			return false;
		});
		
		// Remove current field:
		layout.find('> .content > .fieldset > .line > .field .remove-field').live('click', function() {
			$(this).parent().trigger('field-remove');
			
			return false;
		});
		
		// Edit field:
		layout.find('> .content > .fieldset > .line > .field:not(.selected)').live('click', function() {
			$('.fieldset .field.selected').trigger('field-edit-stop');
			$(this).trigger('field-edit-start');
			
			return false;
		});
		*/
	/*----------------------------------------------------------------------------
		Initialize
	----------------------------------------------------------------------------*/
		/*
		layout.find('> .content > *')
			.trigger('fieldset-initialize');
		layout.find('> .content > .fieldset > .line > .field:first')
			.trigger('field-edit-start');
		*/
	/*----------------------------------------------------------------------------
		Listen to form event
	----------------------------------------------------------------------------*/
		/*
		layout.live('prepare-submit', function() {
			var expression = /^fieldset\[[0-9]+\]\[rows\]\[[0-9]+\]\[fields\]\[[0-9]+\]\[(.*)]$/;
			
			layout.find('> .content > .fieldset').each(function(fieldset_index) {
				var fieldset = $(this);
				var input = fieldset.find('input:visible:first');
				
				input.attr('name', 'fieldset[' + fieldset_index + '][label]');
				
				fieldset.find('> .line').each(function(line_index) {
					$(this).find('> .field').each(function(field_index) {
						var field = $(this)
						var settings = $(this).children('.settings');
						
						if (!settings.length) {
							settings = layout.find('> .settings');
						}
						
						if (!settings.length) return;
						
						settings.find('[name]').each(function() {
							var input = $(this);
							var name = input.attr('name');
							var match = null;
							
							// Extract name:
							if (match = name.match(expression)) name = match[1];
							
							input.attr(
								'name',
								'fieldset['
								+ fieldset_index
								+ '][rows]['
								+ line_index
								+ '][fields]['
								+ field_index
								+ ']['
								+ name
								+ ']'
							);
						});
					});
				});
			});
		});
	});
	
	jQuery(document).ready(function() {
		var $ = jQuery;
		
		$('form').submit(function() {
			var passed = true;
			var fields = {};
			
			$('.layout').trigger('prepare-submit');
			
			// Force removal of duplicate field labels:
			$('.layout .field-label > input').each(function() {
				var input = $(this);
				var field = input.parents('.field');
				var handle = Symphony.Language.createHandle(input.val());
				
				if (field.length == 0) {
					field = $('.layout .field.selected');
				}
				
				if (fields[handle] != undefined) {
					$('.layout .field.selected').trigger('field-edit-stop');
					field.trigger('field-edit-start');
					
					passed = false;
					return false;
				}
				
				fields[handle] = field;
			});
			
			// Force field labels to be set:
			if (passed) $('.layout .field-label > input').each(function() {
				var input = $(this);
				var field = input.parents('.field');
				var empty = input.val() == '';
				
				if (empty) {
					if (field.length) {
						$('.layout .field.selected').trigger('field-edit-stop');
						field.trigger('field-edit-start');
					}
					
					// TODO: Create error notice:
					
					passed = false;
					return false;
				}
			});
			
			return passed;
		});
	});
	*/
/*---------------------------------------------------------------------------*/