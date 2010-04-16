/*-----------------------------------------------------------------------------
	Duplicator plugin
-----------------------------------------------------------------------------*/
	
	jQuery.fn.symphonyDuplicatorNew = function(custom_settings) {
		var objects = this;
		var select = {
			instances:			'.content > .instances > *',
			instance_parent:	'.content > .instances',
			tabs:				'.content > .tabs > *',
			tab_parent:			'.content > .tabs',
			templates:			'.templates > *',
			template_parent:	'.templates'
		};
		var settings = {
			multiselect:		false,
			orderable:			false
		}
		
		jQuery.extend(settings, custom_settings);
		
	/*-----------------------------------------------------------------------*/
		
		objects = objects.map(function() {
			var object = this;
			
			if (object instanceof jQuery === false) {
				object = jQuery(object);
			}
			
			var find = function(selector) {
				return object.find(selector);
			};
			var block = function() {
				return false;
			};
			
		/*-------------------------------------------------------------------*/
			
			object.find('*').live('duplicator-templates-hide', function() {
				find(select.template_parent).hide();
			})
			
			object.find('*').live('duplicator-templates-show', function() {
				find(select.template_parent).show();
			})
			
			object.find('*').live('duplicator-template-initialize', function() {
				var template = jQuery(this);
				
				template.bind('mousedown', block);
				template.bind('click', function() {
					template
						.trigger('duplicator-template-insert');
				});
			});
			
			object.find('*').live('duplicator-template-insert', function() {
				var template = jQuery(this);
				var intance = jQuery('<li />')
					.append(template.find('fieldset > *').clone())
					.appendTo(object.find('> .content > .instances'));
				var tab = jQuery('<li />')
					.append(
						jQuery('<span />')
							.addClass('name')
							.text(template.find('input:first').val())
					)
					.appendTo(object.find('> .content > .tabs'))
					.trigger('duplicator-tab-initialize')
					.trigger('duplicator-tab-select-only');
			});
			
		/*-------------------------------------------------------------------*/
			
			object.find('*').live('duplicator-tab-initialize', function() {
				var tab = jQuery(this);
				var index = tab.prevAll().length;
				var instance = find(select.instances + ':eq(' + index + ')');
				var name = tab.find('.name');
				
				var action_remove = function() {
					jQuery(this).parent()
						.trigger('duplicator-tab-remove');
					find(select.tabs)
						.trigger('duplicator-tab-refresh')
						.filter(':first')
						.trigger('duplicator-tab-select-only');
					
					return false;
				};
				
				jQuery('<a />')
					.addClass('remove')
					.text('×')
					.bind('click', action_remove)
					.appendTo(tab);
				
				tab.bind('mousedown', block);
				
				// Store data:
				tab.data('instance', instance);
				tab.data('name', name);
				
				tab.trigger('duplicator-tab-refresh')
					.trigger('orderable-item-initialize');
			});
			
			object.find('*').live('duplicator-tab-refresh', function() {
				var tab = jQuery(this);
				var index = tab.prevAll().length;
				var name = tab.data('name');
				
				if (!name.text()) {
					name.text('Untitled');
				}
				
				tab.data('index', index);
			});
			
			object.find('*').live('duplicator-tab-select-only', function() {
				var tab = jQuery(this);
				var index = tab.data('index');
				
				find(select.tabs)
					.removeClass('active')
					.filter(':eq(' + index + ')')
					.addClass('active');
				
				find(select.instances)
					.removeClass('active')
					.filter(':eq(' + index + ')')
					.addClass('active');
			});
			
			object.find('*').live('duplicator-tab-select', function() {
				var tab = jQuery(this);
				var index = tab.data('index');
				
				find(select.tabs)
					.filter(':eq(' + index + ')')
					.addClass('active');
				
				find(select.instances)
					.filter(':eq(' + index + ')')
					.addClass('active');
			});
			
			object.find('*').live('duplicator-tab-deselect', function() {
				var tab = jQuery(this);
				var index = tab.data('index');
				
				find(select.tabs)
					.filter(':eq(' + index + ')')
					.removeClass('active');
				
				find(select.instances)
					.filter(':eq(' + index + ')')
					.removeClass('active');
			});
			
			object.find('*').live('duplicator-tab-reorder', function() {
				var tab = jQuery(this);
				var new_index = tab.prevAll().length;
				var old_index = tab.data('index');
				
				// Nothing to do:
				if (new_index == old_index) return;
				
				var items = find(select.instances);
				var parent = items.parent();
				var places = [];
				
				items.not(items[old_index]).each(function(index) {
					if (index == new_index) {
						places.push(null);
					}
					
					places.push(this);
				});
				
				places[new_index] = items[old_index];
				
				parent.empty().append(places);
			});
			
			object.find('*').live('duplicator-tab-remove', function() {
				var tab = jQuery(this);
				var index = tab.data('index');
				var instance = tab.data('instance');
				
				tab.remove(); instance.remove();
			});
			
		/*-------------------------------------------------------------------*/
			
			find(select.templates)
				.trigger('duplicator-template-initialize');
			
			// Wrap contents:
			object.find('> :not(.templates)')
				.wrapAll('<div class="content" />');
			
			// Add tabs:
			jQuery('<ol />')
				.addClass('tabs')
				.prependTo(object.find('> .content'));
			
			find(select.instances)
				.each(function() {
					jQuery('<li />')
						.append(
							jQuery('<span />')
								.addClass('name')
								.text(jQuery(this).find('input:first').val())
						)
						.appendTo(object.find('> .content > .tabs'))
						.trigger('duplicator-tab-initialize');
				});
			
			// Make tabs orderable:
			if (settings.orderable) {
				find(select.tab_parent)
					.symphonyOrderable({
						items:		'> *',
						handles:	''
					});
				
				// Reoder tabs:
				find(select.tabs)
					.live('orderable-started', function(event, target) {
						find(select.tabs + '.ordering')
							.trigger('duplicator-tab-select-only');
					})
					.live('orderable-completed', function(event, target) {
						target.trigger('duplicator-tab-reorder');
						find(select.tabs).trigger('duplicator-tab-refresh');
					})
			}
			
			// Select tabs:
			find(select.tabs)
				.live('click', function(event) {
					var tab = jQuery(this);
					
					if (settings.multiselect && event.shiftKey == true) {
						if (tab.is('.active') && find(select.tabs + '.active').length > 1) {
							tab.trigger('duplicator-tab-deselect');
						}
						
						else {
							tab.trigger('duplicator-tab-select');
						}
					}
					
					else {
						tab.trigger('duplicator-tab-select-only');
					}
				})
				.filter(':first')
				.trigger('duplicator-tab-select-only');
			
			// Add controls:
			jQuery('<div />')
				.addClass('controls')
				.append('<a class="add">Add Items</a>')
				.bind('mousedown', function() { return false; })
				.prependTo(object)
				.find('.add')
				.bind('click', function() {
					var pallet = find(select.template_parent);
					
					if (pallet.is(':visible')) {
						pallet.trigger('duplicator-templates-hide');
						jQuery(this).removeClass('visible');
					}
					
					else {
						pallet.trigger('duplicator-templates-show');
						jQuery(this).addClass('visible');
					}
				});
			
			// Hide templates pallet:
			find(select.template_parent)
				.trigger('duplicator-templates-hide');
		});
		
		return objects;
	};
	
	// Sections duplicator:
	jQuery(document).ready(function() {
		var duplicator = jQuery('#section-duplicator');
		
		// Keep track of field name changes:
		duplicator.find('*').live('duplicator-tab-initialize', function() {
			var tab = jQuery(this);
			var instance = tab.data('instance');
			var name = tab.data('name');
			var rename = function() {
				name.text(jQuery(this).val());
				tab.trigger('duplicator-tab-refresh');
			};
			
			instance.find('input:first')
				.bind('change', rename)
				.bind('keyup', rename);
		});
		
		// When a tab is selected, select its first input:
		duplicator.find('*').live('duplicator-tab-select-only', function() {
			var tab = jQuery(this);
			var instance = tab.data('instance');
			
			instance.find('input:first').focus();
		});
		
		// Initialize duplicator:
		duplicator.symphonyDuplicatorNew({
			multiselect:	true,
			orderable:		true
		});
	});
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	jQuery.fn.symphonyDuplicator = function(custom_settings) {
		var objects = this;
		var settings = {
			instances:			'> li:not(.template)',	// What children do we use as instances?
			templates:			'> li.template',		// What children do we use as templates?
			headers:			'> :first-child',		// What part of an instance is the header?
			orderable:			false,					// Can instances be ordered?
			collapsible:		false,					// Can instances be collapsed?
			constructable:		true,					// Allow construction of new instances?
			destructable:		true,					// Allow destruction of instances?
			minimum:			0,						// Do not allow instances to be removed below this limit.
			maximum:			1000,					// Do not allow instances to be added above this limit.
			speed:				'fast',					// Control the speed of any animations
			delay_initialize:	false
		};
		
		jQuery.extend(settings, custom_settings);
		
	/*-------------------------------------------------------------------------
		Language strings
	-------------------------------------------------------------------------*/
		
		Symphony.Language.add({
			'Add item':		false,
			'Remove item':	false,
			'Collapse all':	false,
			'Expand all':	false
		});
		
	/*-------------------------------------------------------------------------
		Collapsible
	-------------------------------------------------------------------------*/
		
		if (settings.collapsible) objects = objects.symphonyCollapsible({
			items:			'.instance',
			handles:		'.header:first'
		});
		
	/*-------------------------------------------------------------------------
		Orderable
	-------------------------------------------------------------------------*/
		
		if (settings.orderable) objects = objects.symphonyOrderable({
			items:			'.instance',
			handles:		'.header:first'
		});
		
	/*-------------------------------------------------------------------------
		Duplicator
	-------------------------------------------------------------------------*/
		
		objects = objects.map(function() {
			var object = this;
			var templates = [];
			var widgets = {
				controls:		null,
				selector:		null,
				constructor:	null
			};
			var silence = function() {
				return false;
			};
			
			// Construct a new instance:
			var construct = function(source) {
				var template = jQuery(source).clone();
				var instance = prepare(template);
				
				widgets.controls.before(instance);
				object.trigger('construct', [instance]);
				refresh(true);
				
				return instance;
			};
			
			var destruct = function(source) {
				var instance = jQuery(source).remove();
				
				object.trigger('destruct', [instance]);
				refresh();
				
				return instance;
			};
			
			// Prepare an instance:
			var prepare = function(source) {
				var instance = jQuery(source)
					.addClass('instance expanded');
				var header = instance.find(settings.headers)
					.addClass('header')
					.wrapInner('<span />');
				var destructor = header
					.append('<a class="destructor" />')
					.find('a.destructor:first')
					.text(Symphony.Language.get('Remove item'));
				
				header.nextAll().wrapAll('<div class="content" />');
				
				destructor.click(function() {
					if (jQuery(this).hasClass('disabled')) return;
					
					destruct(source);
				});
				
				header.bind('selectstart', silence);
				header.mousedown(silence);
				
				return instance;
			};
			
			// Refresh disabled states:
			var refresh = function(input_focus) {
				var constructor = settings.constructable;
				var selector = settings.constructable;
				var destructor = settings.destructable;
				var instances = object.children('.instance');
				var empty = false;
				
				// Update field names:
				instances.each(function(position) {
					jQuery(this).find('*[name]').each(function() {
						var exp = /\[\-?[0-9]+\]/;
						var name = jQuery(this).attr('name');
						
						if (exp.test(name)) {
							jQuery(this).attr('name', name.replace(exp, '[' + position + ']'));
						}
					});
				});

				// Give focus to the first input in the first instance
				if (input_focus) instances.filter(':last').find('input[type!=hidden]:first').focus();
				
				// No templates to add:
				if (templates.length < 1) {
					constructor = false;
				}
				
				// Only one template:
				if (templates.length <= 1) {
					selector = false;
				}
				
				// Maximum reached?
				if (settings.maximum <= instances.length) {
					constructor = false;
					selector = false;
				}
				
				// Minimum reached?
				if (settings.minimum >= instances.length) {
					destructor = false;
				}
				
				if (constructor) widgets.constructor.removeClass('disabled');
				else widgets.constructor.addClass('disabled');
				
				if (selector) widgets.selector.removeClass('disabled');
				else widgets.selector.addClass('disabled');
				
				if (destructor) instances.find(settings.headers).find('.destructor').removeClass('disabled');
				else instances.find(settings.headers).find('.destructor').addClass('disabled');
				
				if (!empty) object.removeClass('empty');
				else object.addClass('empty');
				
				if (settings.collapsible) object.collapsible.initialize();
				if (settings.orderable) object.orderable.initialize();
			};
			
		/*-------------------------------------------------------------------*/
			
			if (object instanceof jQuery === false) {
				object = jQuery(object);
			}
			
			object.duplicator = {
				settings: settings,
				
				refresh: function() {
					refresh();
				},
				
				initialize: function() {
					object.addClass('duplicator');
					
					// Prevent collapsing when ordering stops:
					object.bind('orderstart', function() {
						if (settings.collapsible) {
							object.collapsible.cancel();
						}
					});
					
					// Refresh on reorder:
					object.bind('orderstop', function() {
						refresh();
					});
					
					// Slide up on collapse:
					object.bind('collapsestop', function(event, item) {
						item.find('> .content').show().slideUp(settings.speed);
					});
					
					// Slide down on expand:
					object.bind('expandstop', function(event, item) {
						item.find('> .content').hide().slideDown(settings.speed);
					});
					
					widgets.controls = object
						.append('<div class="controls" />')
						.find('> .controls:last');
					widgets.selector = widgets.controls
						.prepend('<select />')
						.find('> select:first');
					widgets.constructor = widgets.controls
						.append('<a class="constructor" />')
						.find('> a.constructor:first')
						.text(Symphony.Language.get('Add item'));
					
					// Prepare instances:
					object.find(settings.instances).each(function() {
						var instance = prepare(this);
						
						object.trigger('construct', [instance]);
					});
					
					// Store templates:
					object.find(settings.templates).each(function(position) {
						var template = jQuery(this).remove();
						var header = template.find(settings.headers).addClass('header');
						var option = widgets.selector.append('<option />')
							.find('option:last');
						var header_children = header.children();
						
						if (header_children.length) {
							header_text = header.get(0).childNodes[0].nodeValue
							+ ' (' + header_children.filter(':eq(0)').text() + ')';
						}
						
						else {
							header_text = header.text();
						}
						
						option.text(header_text).val(position);
						
						templates.push(template.removeClass('template'));
					});
					
					// Construct new template:
					widgets.constructor.bind('selectstart', silence);
					widgets.constructor.bind('mousedown', silence);
					widgets.constructor.bind('click', function() {
						if (jQuery(this).hasClass('disabled')) return;
						
						var position = widgets.selector.val();
						
						if (position >= 0) construct(templates[position]);
					});
					
					refresh();
				}
			};
			
			if (settings.delay_initialize !== true) {
				object.duplicator.initialize();
			}
			
			return object;
		});
		
		objects.duplicator = {
			settings: settings
		};
		
		return objects;
	};
	
/*-----------------------------------------------------------------------------
	Duplicator With Name plugin
-----------------------------------------------------------------------------*/
	
	jQuery.fn.symphonyDuplicatorWithName = function(custom_settings) {
		var objects = jQuery(this).symphonyDuplicator(jQuery.extend(
			custom_settings, {
				delay_initialize:		true
			}
		));
		
		objects = objects.map(function() {
			var object = this;
			
			object.bind('construct', function(event, instance) {
				var input = instance.find('input:visible:first');
				var header = instance.find('.header:first > span:first');
				var fallback = header.text();
				var refresh = function() {
					var value = input.val();
					
					header.text(value ? value : fallback);
				};
				
				input.bind('change', refresh).bind('keyup', refresh);
				
				refresh();
			});
			
			object.duplicator.initialize();
		});
		
		return objects;
	};
	
/*-----------------------------------------------------------------------------
	Collapsed duplicator
-----------------------------------------------------------------------------*/
	
	jQuery.fn.symphonyCollapsedDuplicator = function(custom_settings) {
		var objects = jQuery(this).symphonyDuplicator(jQuery.extend(
			custom_settings, {
				collapsible:		true,
				delay_initialize:	true
			}
		));
		var settings = objects.duplicator.settings;
		
		objects = objects.map(function() {
			var object = this;
			var collapse_all = null, expand_all = null;
			var cookie_id = '', open = [];
			var construct = function(event, instance, x) {
				// Don't collapse on error:
				if (instance.find('#error').length) return;
				
				// Remember open states:
				if (open && open.indexOf(instance.index().toString()) >= 0) return;
				
				instance.removeClass('expanded').addClass('collapsed');
			};
			var refresh = function() {
				var open = [];
				
				object.find(settings.instances).each(function(index) {
					if (jQuery(this).is('.expanded')) open.push(index);
				});
				
				Symphony.Cookie.set(cookie_id, open.join(','));
				
				// Toggle expand/collape all buttons:
				if (open.length) {
					collapse_all.show();
					expand_all.hide();
				}
				
				else {
					collapse_all.hide();
					expand_all.show();
				}
			};
			
			// Make sure it has an id:
			if (!object.attr('id')) return object;
			
			cookie_id = 'symphony-collapsed-duplicator-' + object.attr('id');
			
			// Read cookie:
			if (Symphony.Cookie.get(cookie_id)) {
				open = Symphony.Cookie.get(cookie_id).split(',');
			}
			
			// Collapse items as they are constructed:
			object.bind('construct', construct);
			object.duplicator.initialize();
			object.unbind('construct', construct);
			
			// Listen for changes:
			object.bind('collapsestop', refresh);
			object.bind('expandstop', refresh);
			object.bind('orderstop', refresh);
			
			// Add collapse/expand all toggle:
			collapse_all = jQuery('<a />')
				.addClass('collapse-all')
				.text('Collapse All')
				.appendTo(object.children('.controls:last'))
				.bind('click', object.collapsible.collapseAll);
			
			expand_all = jQuery('<a />')
				.addClass('collapse-all')
				.text('Expand All')
				.appendTo(object.children('.controls:last'))
				.bind('click', object.collapsible.expandAll);
			
			refresh();
		});
		
		return objects;
	};
	
/*---------------------------------------------------------------------------*/