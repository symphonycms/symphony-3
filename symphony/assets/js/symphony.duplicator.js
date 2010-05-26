/*-----------------------------------------------------------------------------
	Duplicator plugin
-----------------------------------------------------------------------------*/
	
	jQuery(document).ready(function() {
		var $ = jQuery;
		var select = {
			instances:			'> .content > .instances > *',
			instance_parent:	'> .content > .instances',
			tabs:				'> .content > .tabs > *',
			tab_parent:			'> .content > .tabs',
			templates:			'> .templates > *',
			template_parent:	'> .templates',
			controls_add:		'> .controls > .add',
			controls_parent:	'> .controls'
		};
		var block = function() {
			return false;
		};
		
	/*-------------------------------------------------------------------------
		Tabs
	-------------------------------------------------------------------------*/
		
		$('.duplicator-widget' + select.tabs)
			// Initialize:
			.live('tab-initialize', function() {
				var tab = $(this);
				var object = tab.closest('.duplicator-widget');
				var index = tab.prevAll().length;
				var name = tab.find('.name');
				var instance = object.find(select.instances + ':eq(' + index + ')');
				var name = tab.find('.name');
				
				// Store data:
				tab.data('instance', instance);
				tab.data('name', name);
				
				tab.addClass('orderable-item orderable-handle')
					.trigger('tab-refresh');
			})
			
			// Refresh:
			.live('tab-refresh', function() {
				var tab = $(this);
				var index = tab.prevAll().length;
				var name = tab.data('name');
				
				if (!name.text()) {
					name.text(Symphony.Language.get('Untitled'));
				}
				
				tab.data('index', index);
			})
			
			// Remove:
			.live('tab-remove', function() {
				var tab = $(this);
				var instance = tab.data('instance');
				
				tab.remove(); instance.remove();
			})
			
			// Reorder:
			.live('tab-reorder', function() {
				var tab = jQuery(this);
				var object = tab.closest('.duplicator-widget');
				var new_index = tab.prevAll().length;
				var old_index = tab.data('index');
				
				// Nothing to do:
				if (new_index == old_index) return;

				var items = object.find(select.instances);
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
			})
			
			// Select/deselect:
			.live('tab-select', function() {
				var tab = $(this);
				var instance = tab.data('instance');
				
				tab.addClass('active');
				instance.addClass('active');
			})
			.live('tab-deselect', function() {
				var tab = $(this);
				var instance = tab.data('instance');
				
				tab.removeClass('active');
				instance.removeClass('active');
			})
			
			// Reorder actions:
			.live('orderable-started', function() {
				$(this)
					.trigger('tab-select')
					.siblings('.active')
					.trigger('tab-deselect');
			})
			.live('orderable-completed', function() {
				var tab = $(this);
				var object = tab.closest('.duplicator-widget');
				
				tab.trigger('tab-reorder');
				object.find(select.tabs)
					.trigger('tab-refresh');
			})
			
			// Click actions:
			.live('click', function(event) {
				var tab = $(this);
				var target = $(event.target);
				
				// Remove:
				if (target.is('.remove')) {
					// Select another tab first:
					if (!tab.is('.active') || !tab.siblings('.active').length > 0) {
						if (tab.next().length) tab.next().trigger('tab-select');
						else if (tab.prev().length) tab.prev().trigger('tab-select');
					}
					
					tab.trigger('tab-remove');
				}
				
				// Select:
				else {
					if (event.shiftKey == true) {
						if (tab.is('.active') && tab.siblings('.active').length > 0) {
							tab.trigger('tab-deselect');
						}
						
						else {
							tab.trigger('tab-select');
						}
					}
					
					// Deselect everything else:
					else {
						tab
							.trigger('tab-select')
							.siblings('.active')
							.trigger('tab-deselect');
					}
				}
			})
			
			// Ignore mouse clicks:
			.live('mousedown', block)
			
			// Initialize:
			.trigger('tab-initialize');
		
	/*-------------------------------------------------------------------------
		Templates
	-------------------------------------------------------------------------*/
		
		// Toggle template pallet:
		$('.duplicator-widget' + select.controls_add)
			.live('click', function() {
				var button = $(this);
				var object = button.closest('.duplicator-widget');
				var pallet = object.find(select.template_parent);
				
				if (pallet.is(':visible')) {
					pallet.hide();
					button.removeClass('visible');
				}
				
				else {
					pallet.show();
					button.addClass('visible');
				}
			})
			
			// Ignore mouse clicks:
			.live('mousedown', block);
		
		$('.duplicator-widget' + select.templates)
			// Insert template:
			.live('template-insert', function() {
				var template = $(this);
				var object = template.closest('.duplicator-widget');
				var instance = $('<li />')
					.append(template.find('> :not(.name)').clone(true))
					.appendTo(object.find(select.instance_parent));
				var tab = $('<li />')
					.append(
						$('<span />')
							.addClass('name')
							.html(template.find('> .name').html())
					)
					.append(
						$('<a />')
							.addClass('remove')
							.text('×')
					)
					.appendTo(object.find(select.tab_parent))
					.trigger('tab-initialize')
					.trigger('tab-select')
					.siblings('.active')
					.trigger('tab-deselect');
			})
			
			// Click actions:
			.live('click', function() {
				$(this)
					.trigger('template-insert');
			})
			
			// Ignore mouse clicks:
			.live('mousedown', block);
	});
	
/*-----------------------------------------------------------------------------
	Fields Duplicator
-----------------------------------------------------------------------------*/
	
	/*
	jQuery.fn.symphonyFieldsDuplicator = function(custom_settings) {
		var duplicator = jQuery(this);

		duplicator.find('*')
			// Keep track of field name changes:
			.live('duplicator-tab-initialize', function() {
				var tab = jQuery(this);
				var instance = tab.data('instance');
				var name = tab.data('name');
				var type = name.find('em');
				var input = instance.find('input:first');
				var rename = function() {
					name.text(input.val());
					tab.trigger('duplicator-tab-refresh');
					name.append(type);
				};

				if (type.length == 0) {
					type = jQuery('<em />')
						.text(name.text())
						.appendTo(name);
				}

				input
					.bind('change', rename)
					.bind('keyup', rename);

				rename();
			})
			// When a tab is selected, select its first input:
			.live('duplicator-tab-select-only', function() {
				var tab = jQuery(this);
				var instance = tab.data('instance');

				instance.find('input:first').focus();
			});

		// Initialize duplicator:
		duplicator.symphonyDuplicator(custom_settings);

		return duplicator;
	};
	*/

/*---------------------------------------------------------------------------*/