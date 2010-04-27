/*---------------------------------------------------------------------------*/
	
	jQuery.fn.symphonyLayout = function(custom_settings) {
		var objects = this;
		var settings = {
		};
		
		Symphony.Language.add({
			'Add Fieldset': false,
			'Remove Fieldset': false,
			'Choose Layout': false,
			'Untitled': false
		});
		
		jQuery.extend(settings, custom_settings);
		
	/*-----------------------------------------------------------------------*/
		
		objects = objects.map(function() {
			var object = jQuery(this).addClass('layout-widget');
			var children = object.find('*');
			
			var get_columns = function(filter) {
				var data = object.find('> .columns > *');
				
				if (filter) return data.filter(filter);
				
				return data;
			};
			var get_layout_id = function(element) {
				var layout_id = 'columns type-';
				
				element.children().each(function(index) {
					var column = jQuery(this);
					
					if (column.is('.small')) {
						layout_id += 's';
					}
					
					else if (column.is('.medium')) {
						layout_id += 'm';
					}
					
					else if (column.is('.large')) {
						layout_id += 'l';
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
			
			children.live('layout-initialize', function() {
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
			
			children.live('layouts-hide', function() {
				object.find('> .layouts').hide();
			})
			
			children.live('layouts-show', function() {
				object.find('> .layouts').show();
			})
			
			children.live('layout-select', function() {
				var layout = jQuery(this);
				var old_columns = get_columns();
				var new_columns = layout.find('> div > *').clone();
				var parent = old_columns.first().closest('.columns');
				
				// Remove old columns:
				parent.empty();
				
				// Remove placeholder text:
				new_columns.text('');
				
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
				
				parent.removeClass()
					.addClass(layout.data('layout-id'));
				
				active_layout_id = layout.data('layout-id');
				object.trigger('change');
			})
			
		/*-------------------------------------------------------------------*/
			
			children.live('column-initialize', function() {
				var column = jQuery(this);
				
				// Insert default fieldset:
				if (column.children().length == 0) {
					jQuery('<fieldset />').appendTo(column);
				}
				
				column.find('> fieldset').trigger('fieldset-initialize');
			});
			
		/*-------------------------------------------------------------------*/
			
			children.live('fieldset-initialize', function() {
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
						.addClass('add')
						.append(
							jQuery('<span />')
								.text(Symphony.Language.get('Add Fieldset'))
						)
						.appendTo(controls);
					
					jQuery('<li />')
						.addClass('remove')
						.append(
							jQuery('<span />')
								.text(Symphony.Language.get('Remove Fieldset'))
						)
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
						jQuery('.ui-sortable-helper')
							.css('height', 'auto');
					},
					
					stop:			function(event, data) {
						jQuery(data.item)
							.closest('fieldset')
							.trigger('fieldset-change')
							.trigger('fieldset-refresh');
						fieldset
							.trigger('fieldset-refresh');
					}
				});
				
				fieldset.find('> .fields > .field')
					.trigger('field-initialize');
				
				fieldset.trigger('fieldset-refresh');
			});
			
			children.live('fieldset-refresh', function() {
				var fieldset = jQuery(this);
				var remove = fieldset.find('> .controls > .remove');
				var fields = fieldset.find('> .fields > .field');
				
				if (fieldset.siblings('fieldset').length && fields.length == 0) {
					remove.removeClass('disabled');
				}
				
				else {
					remove.addClass('disabled');
				}
			});
			
			children.live('fieldset-remove', function() {
				var fieldset = jQuery(this);
				var siblings = fieldset.siblings('fieldset');
				
				if (siblings.length) {
					fieldset.remove();
					siblings.trigger('fieldset-refresh');
				}
				
				object.trigger('change');
			});
			
			children.live('fieldset-change', function() {
				var fieldset = jQuery(this);
				
				fieldset.removeClass('unchanged');
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
			object.find('.columns .column fieldset .controls .add').live('click', function() {
				var fieldset = jQuery(this).closest('fieldset');
				
				jQuery('<fieldset />')
					.insertAfter(fieldset)
					.trigger('fieldset-initialize');
			});
			
			// Remove fieldsets and fields:
			object.find('.columns .column fieldset .controls .remove').live('click', function() {
				jQuery(this)
					.closest('fieldset')
					.trigger('fieldset-remove');
			});
		});
	};
	
/*---------------------------------------------------------------------------*/