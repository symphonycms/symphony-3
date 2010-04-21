/*---------------------------------------------------------------------------*/
	
	jQuery.fn.symphonyLayout = function(custom_settings) {
		var objects = this;
		var settings = {
		};
		
		Symphony.Language.add({
			'Add Item': false,
			'Choose Layout': false,
			'Fieldset': false,
			'Untitled': false
		});
		
		jQuery.extend(settings, custom_settings);
		
	/*-----------------------------------------------------------------------*/
		
		objects = objects.map(function() {
			var object = jQuery(this).addClass('layout-widget');
			
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
				object.trigger('change');
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
						.attr('value', Symphony.Language.get('Untitled'))
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
						.append(
							jQuery('<span />')
								.text(Symphony.Language.get('Add Item') + ' ▼')
						)
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
					cursorAt:		{ top: 15, left: 30 },
					distance:		10,
					items:			'li',
					placeholder:	'ui-sortable-highlight',
					tolerance:		'pointer',
					zindex:			1000,
					
					sort:			function() {
						jQuery('.ui-sortable-helper').css('height', 'auto');
					},
					
					stop:			function() {
						object.trigger('change');
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
				
				// Move fields back to templates:
				fieldset.find('ol.fields > li')
					.appendTo(object.find('> .templates'));
				
				if (siblings.length) {
					fieldset.remove();
					siblings.trigger('fieldset-refresh');
				}
				
				object.trigger('change');
			});
			
			object.find('*').live('fieldset-change', function() {
				var fieldset = jQuery(this);
				
				fieldset.removeClass('unchanged');
				object.trigger('change');
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
					
					object.trigger('change');
				};
				var add_field = function() {
					jQuery(this).appendTo(fieldset.find('.fields'))
						.unbind('mousedown', add_field)
						.trigger('field-initialize');
					
					fieldset.trigger('fieldset-change');
					object.trigger('change');
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
					.text(Symphony.Language.get('Fieldset'))
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
				
				if (menu.find('ol').length == 2) {
					menu.find('ol:first > li')
						.appendTo(object.find('> .templates'));
				}
				
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
				object.trigger('change');
			});
			
		/*-------------------------------------------------------------------*/
			
			// Add controls:
			jQuery('<div />')
				.addClass('controls')
				.append(
					jQuery('<a />')
						.addClass('choose')
						.text(Symphony.Language.get('Choose Layout'))
				)
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
	
/*---------------------------------------------------------------------------*/