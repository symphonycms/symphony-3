/*-----------------------------------------------------------------------------
	Duplicator plugin
-----------------------------------------------------------------------------*/

	jQuery.fn.symphonyDuplicator = function(custom_settings) {
		var objects = this;
		var select = {
			instances:			'.content > .instances > *',
			instance_parent:	'.content > .instances',
			tabs:				'.content > .tabs > *',
			tab_parent:			'.content > .tabs',
			templates:			'.templates > *',
			template_parent:	'.templates'
		}

		var settings = {
			multiselect:		false,
			orderable:			false
		}

		Symphony.Language.add({
			'Add Items': false,
			'Untitled': false
		});

		jQuery.extend(settings, custom_settings);

	/*-----------------------------------------------------------------------*/

		objects = objects.map(function() {
			var object = jQuery(this).addClass('duplicator-widget'),
				form = object.closest('form'),
				container = object.find('*');

			var find = function(selector) {
				return object.find(selector);
			};
			var block = function() {
				return false;
			};

		/*-------------------------------------------------------------------*/

			container.live('duplicator-templates-hide', function() {
				find(select.template_parent).hide();
			})

			container.live('duplicator-templates-show', function() {
				find(select.template_parent).show();
			})

			container.live('duplicator-template-initialize', function() {
				var template = jQuery(this);

				template.bind('mousedown', block);
				template.bind('click', function() {
					template
						.trigger('duplicator-template-insert');
				});
			});

			container.live('duplicator-template-insert', function() {
				var template = jQuery(this);
				var instance = jQuery('<li />')
					.append(template.find('> :not(.name)').clone(true))
					.appendTo(object.find('> .content > .instances'));
				var tab = jQuery('<li />')
					.append(
						jQuery('<span />')
							.addClass('name')
							.html(template.find('> .name').html())
					)
					.appendTo(object.find('> .content > .tabs'))
					.trigger('duplicator-tab-initialize')
					.trigger('duplicator-tab-select-only');
			});

		/*-------------------------------------------------------------------*/

			container.live('duplicator-tab-initialize', function() {
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

			container.live('duplicator-tab-refresh', function() {
				var tab = jQuery(this);
				var index = tab.prevAll().length;
				var name = tab.data('name');

				if (!name.text()) {
					name.text(Symphony.Language.get('Untitled'));
				}

				tab.data('index', index);
			});

			container.live('duplicator-tab-select-only', function() {
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

			container.live('duplicator-tab-select', function() {
				var tab = jQuery(this);
				var index = tab.data('index');

				find(select.tabs)
					.filter(':eq(' + index + ')')
					.addClass('active');

				find(select.instances)
					.filter(':eq(' + index + ')')
					.addClass('active');
			});

			container.live('duplicator-tab-deselect', function() {
				var tab = jQuery(this);
				var index = tab.data('index');

				find(select.tabs)
					.filter(':eq(' + index + ')')
					.removeClass('active');

				find(select.instances)
					.filter(':eq(' + index + ')')
					.removeClass('active');
			});

			container.live('duplicator-tab-reorder', function() {
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

			container.live('duplicator-tab-remove', function() {
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
								.html(jQuery(this).find('> .name').remove().html())
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

				// Reorder tabs:
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
				.append(
					jQuery('<a />')
						.addClass('add')
						.text(Symphony.Language.get('Add Items'))
				)
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

			// Hide templates pallet when not empty:
			if (find(select.instances).length > 0) {
				find(select.template_parent)
					.trigger('duplicator-templates-hide');
			}

			// Remove templates on form submit
			form.bind('submit', function() {
				find(select.template_parent)
					.trigger('duplicator-templates-hide')
					.remove();
			});
		});

		return objects;
	};

/*-----------------------------------------------------------------------------
	Fields Duplicator
-----------------------------------------------------------------------------*/

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

/*---------------------------------------------------------------------------*/