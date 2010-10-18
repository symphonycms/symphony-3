var Symphony;

(function($) {
	Symphony = {
		WEBSITE: $('script[src]:last').get(0).src.match('(.*)/symphony')[1],
		Cookie: {
			set: function(name, value, seconds) {
				var expires = "";

				if (seconds) {
					var date = new Date();
					date.setTime(date.getTime() + seconds);
					expires = "; expires=" + date.toGMTString();
				}

				document.cookie = name + "=" + value + expires + "; path=/";
			},
			get: function(name) {
				var nameEQ = name + "=";
				var ca = document.cookie.split(';');

				for (var i=0;i < ca.length;i++) {
					var c = ca[i];
					while (c.charAt(0)==' ') c = c.substring(1,c.length);
					if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
				}

				return null;
			}
		},
	};

/*-----------------------------------------------------------------------------
	Symphony languages
-----------------------------------------------------------------------------*/

	Symphony.Language = {
		NAME: $('html').attr('lang'),
		DICTIONARY: {},

		// TODO: Load regular expressions from lang.php.
		createHandle: function(value) {
			var exp = /[^\w]+/;

			value = value.split(exp).join('-');
			value = value.replace(/^-/, '');
			value = value.replace(/-$/, '');

			return value.toLowerCase();
		},
		get: function(string, tokens) {
			// Get translated string
			translatedString = Symphony.Language.DICTIONARY[string];

			// Return string if it cannot be found in the dictionary
			if(translatedString !== undefined) string = translatedString;

			// Insert tokens
			if(tokens !== undefined) string = Symphony.Language.insert(string, tokens);

			// Return translated string
			return string;
		},
		insert: function(string, tokens) {
			// Replace tokens
			$.each(tokens, function(index, value) {
				string = string.replace('{$' + index + '}', value);
			});
			return string;
		},
		add: function(strings) {
			// Set key as value
			$.each(strings, function(key, value) {
				strings[key] = key;
			});
			// Save English strings
			if(Symphony.Language.NAME == 'en') {
				Symphony.Language.DICTIONARY = $.extend(Symphony.Language.DICTIONARY, strings);
			}
			// Translate strings
			else {
				Symphony.Language.translate(strings);
			}
		},
		translate: function(strings) {
			/* Load translations synchronous
			$.ajax({
				async: false,
				type: 'GET',
				url: Symphony.WEBSITE + '/symphony/ajax/translate',
				data: strings,
				dataType: 'json',
				success: function(result) {
					Symphony.Language.DICTIONARY = $.extend(Symphony.Language.DICTIONARY, result);
				}
			});
			*/
		}
	};

	// Add language strings
	Symphony.Language.add({
		'Add item': false,
		'Remove selected items': false,
		'Are you sure you want to {$action} {$name}?': false,
		'Are you sure you want to {$action} {$count} items?': false,
		'Are you sure you want to {$action}?': false,
		'Reordering was unsuccessful.': false,
		'Password': false,
		'Change Password': false,
		'Remove File': false,
		'at': false,
		'just now': false,
		'a minute ago': false,
		'{$minutes} minutes ago': false,
		'about 1 hour ago': false,
		'about {$hours} hours ago': false
	});

/*-----------------------------------------------------------------------------
	Symphony Alerts
-----------------------------------------------------------------------------*/

	Symphony.Alert = {
		seconds: 		0,
		notices:		[],
		faded:			false,
		visible:		false,
		min_opacity:	0.8,
		max_opacity:	1,
		post: function(message, type, replace) {
			if ($('#alerts').length == 0) {
				$('<ol />')
					.attr('id', 'alerts')
					.insertAfter('form');
			}
			
			var self = Symphony.Alert;
			var block = function() {
				return false;
			};
			var notice = $('<li />')
				.hide()
				.attr('class', type)
				.html(message)
				.prependTo('#alerts');
			var dismiss = $('<a />')
				.insertAfter(notice.find('.message'))
				.attr('href', '#')
				.attr('class', 'dismiss')
				.text('Dismiss 1 of ' + (self.notices.length + 1))
				.bind('mousedown', block)
				.bind('click', function() {
					self.hide();

					return false;
				});
			var more = notice.find('.message a.more')
				.bind('mousedown', block)
				.bind('click', function() {
					notice.find('> .info')
						.slideToggle('fast');
				});

			// Add to queue:
			self.notices.push({
				notice:				notice,
				dismiss:			dismiss,
				more:				more,
				seconds_existed:	0,
				seconds_viewed:		0,
				visible:			false
			});

			self.show();
		},
		show: function() {
			var self = Symphony.Alert;
			var next = self.notices[0];

			if (!self.visible) {
				self.visible = true;
				next.visible = true;
				next.notice.stop().css({
					'margin-top':		'-30px',
					'opacity':			'0'
				}).show().animate(
					{
						'margin-top':	'0',
						'opacity':		Symphony.Alert.max_opacity
					},
					'fast', 'linear'
				);
			}

			else {
				next.visible = true;
				next.notice.show();

				if (self.faded) {
					var current = self.notices.slice(-1)[0];
					var pulses = 0;
					var pulse = function() {
						next.notice.animate(
							{
								'opacity':	Symphony.Alert.max_opacity
							},
							'slow', 'linear',
							function() {
								next.notice.animate(
									{
										'opacity':	Symphony.Alert.min_opacity
									},
									'slow', 'linear',
									function() {
										if ((pulses += 1) < 2) pulse();
									}
								);
							}
						);
					};

					next.notice.stop();
					pulse();
				}
			}

			self.update();
		},
		hide: function() {
			var self = Symphony.Alert;
			var current = self.notices.shift();

			if (self.notices.length) {
				self.show();
				current.notice.remove();
			}

			else {
				current.notice
					.unbind('mouseout')
					.unbind('mouseover')
					.stop().animate(
					{
						'margin-top':	'-30px',
						'opacity':		'0'
					},
					'fast', 'linear',
					function() {
						self.visible = false;
						current.notice.remove();
					}
				);
			}
		},
		ticker: function() {
			var self = Symphony.Alert;

			self.notices.forEach(function(current) {
				current.seconds_existed += 1;

				if (current.visible) {
					current.seconds_viewed += 1;
				}

				if (current.seconds_viewed != 10) return;

				current.notice
					.hover(
						function() {
							if (current.notice.find('.info:visible').length == 1) return;

							current.notice.stop().animate(
								{
									'opacity':	Symphony.Alert.max_opacity
								},
								'fast', 'linear',
								function() {
									self.faded = false;
								}
							);
						},
						function() {
							if (current.notice.find('.info:visible').length == 1) return;

							current.notice.stop().animate(
								{
									'opacity':	Symphony.Alert.min_opacity
								},
								'fast', 'linear',
								function() {
									self.faded = true;
								}
							);
						}
					);

				if (current.notice.find('.info:visible').length == 0) {
					current.notice.stop().animate(
						{
							'opacity':	Symphony.Alert.min_opacity
						},
						'slow', 'linear',
						function() {
							self.faded = true;
						}
					);
				}
			});

			if (self.notices.length) self.update();
		},
		update: function() {
			var self = Symphony.Alert;

			self.notices.forEach(function(current) {
				var label = current.notice.find('.timeago');
				var time = Math.floor(current.seconds_existed * 30);
				var text = label.text();

				current.dismiss.text('Dismiss 1 of ' + (self.notices.length));

				if (time < 1) text = Symphony.Language.get('just now');
				else if (time < 2) text = Symphony.Language.get('a minute ago');
				else if (time < 45) text = Symphony.Language.get(
					'{$minutes} minutes ago',
					{'minutes': time}
				);
				else if (time < 90) text = Symphony.Language.get('about 1 hour ago');
				else text = Symphony.Language.get(
					'about {$hours} hours ago',
					{'hours': Math.floor(time / 60)}
				);
			});
		}
	};
	
	// Start timers:
	window.setInterval(Symphony.Alert.ticker, 1000);
	
	// Initialize notices:
	$(document).ready(function() {
		$('#alerts > li').each(function() {
			var notice = $(this).remove();

			// Add notice:
			if (notice.is('.error')) {
				Symphony.Alert.post(notice.html(), 'error');
			}

			else if (notice.is('.success')) {
				Symphony.Alert.post(notice.html(), 'success');
			}

			else {
				Symphony.Alert.post(notice.html(), 'info');
			}
		});
	});

/*-----------------------------------------------------------------------------
	Common
-----------------------------------------------------------------------------*/

	$(document).ready(function() {
		$('.tags').symphonyTags();

		$('table:has(input)').symphonySelectable();
	});

/*-----------------------------------------------------------------------------
	Tabs
-----------------------------------------------------------------------------*/
	
	$(document).ready(function() {
		var tabs = $('#tab');
		
		// No tabs:
		if (tabs.length == 0) return;
		
		var form = $('form');
		var changed = false;
		
		Symphony.Language.add({
			'You have unsaved changes, please save first.': false
		});
		
		// Form has errors:
		changed = form.find('.invalid:first').length == 1;
		
		// Listen for changes:
		form.bind('change', function() {
			changed = true;
		});
		
		// Save before changing tabs:
		tabs.find('a').bind('click', function() {
			if (changed == false) return true;
			
			Symphony.Alert.post(
				'<div class="message">'
				+ Symphony.Language.get('You have unsaved changes, please save first.')
				+ '</div>',
				'error'
			);
			
			return false;
		});
	});
	
/*-----------------------------------------------------------------------------
	Master Switch
-----------------------------------------------------------------------------*/
	
	$(document).ready(function() {
		$('h2 select').bind('change', function() {
			window.location.search = '?type=' + $(this).val();
		});
	});
	
/*-----------------------------------------------------------------------------
	Sections Page
-----------------------------------------------------------------------------*/

	jQuery(document).ready(function() {
		var duplicator = jQuery('#section-duplicator');
		var layout = jQuery('#section-layout');
		
		// Not on the section editor:
		if (duplicator.length == 0 && layout.length == 0) return;

		var form = $('form');
		
		if (duplicator.length) {
			if (duplicator.find('.instances > li .invalid').length) {
				duplicator.find('.tabs > li:first')
					.trigger('tab-deselect');
			}

			// Show errors:
			duplicator.find('.instances > li').each(function(index) {
				var instance = $(this);

				if (instance.find('.invalid').length == 0) return;

				duplicator.find('.tabs > li:eq(' + index + ')')
					.trigger('tab-select');
			});

			// Update input names before submit:
			form.bind('submit', function() {
				var expression = /^fields\[[0-9]+\]\[(.*)]$/;

				duplicator.find('> .content > .instances > li').each(function(index) {
					var instance = $(this);

					instance.find('[name]').each(function() {
						var input = $(this);
						var name = input.attr('name');
						var match = null;

						// Extract name:
						if (match = name.match(expression)) name = match[1];

						input.attr(
							'name',
							'fields['
							+ index
							+ ']['
							+ name
							+ ']'
						);
						// TODO: Doesnt work for names that end with [ ]
					});
				});
			});
		}

		if (layout.length) {
			layout.symphonyLayout();

			// Update input names before submit:
			form.bind('submit', function() {
				var expression = /^layout\[[0-9]+\]\[fieldsets\]\[[0-9]+\]\[fields\]\[(.*)]$/;

				layout.find('> .columns > .column').each(function(column) {
					var input = $('<input />')
						.attr('name', 'size')
						.attr('type', 'hidden');

					input.val(this.className.match(/column ([a-z]+)/)[1]);

					input.attr(
						'name',
						'layout['
						+ column
						+ '][size]'
					);

					$(this).find('> input').remove();
					$(this).append(input);

					$(this).find('> fieldset').each(function(fieldset) {
						var input = $(this).find('> h3 > input');

						input.attr(
							'name',
							'layout['
							+ column
							+ '][fieldsets]['
							+ fieldset
							+ '][name]'
						);

						$(this).find('> .fields > .field input').each(function(field) {
							var input = $(this);

							input.attr(
								'name',
								'layout['
								+ column
								+ '][fieldsets]['
								+ fieldset
								+ '][fields]['
								+ field
								+ ']'
							);
						});
					});
				});
			});
		}
	});

/*-----------------------------------------------------------------------------
	Views List
-----------------------------------------------------------------------------*/

	$(document).ready(function() {
		var table = $('#views-list');
		var rows = table.find('tbody tr');
		var depth = 0;
		
		// Insert toggle controls:
		rows.each(function() {
			var row = $(this);
			var cell = row
				.find('td:first')
				.addClass('toggle');
			
			// Children:
			if (this.className) {
				var classes = this.className.split(' ');
				var depth = classes.length - 1;
				var parents = $('.' + classes.join(', .'));
				
				row.addClass('child');
				row.data().depth = depth;
				row.data().parents = parents;
				
				$('<span />')
					.html('&#x21b5;')
					.css('margin-left', (depth * 20) + 'px')
					.prependTo(cell);
			}
			
			// Parents:
			else {
				var children = $('.' + row.attr('id'));
				
				row.addClass('parent');
				row.data().children = children;
				
				if (children.length) {
					$('<a />')
						.text('▼')
						.addClass('hide')
						.prependTo(cell);
				}
			}
			
			cell.wrapInner('<div />');
		});
		
		$('#views-list td.toggle a, #views-list td.toggle + td span').live('mousedown', function() {
			return false;
		});
		
		$('#views-list td.toggle a').live('click', function() {
			var link = $(this);
			var row = link.parents('tr');
			var children = row.data().children;
			
			if (link.is('.hide')) {
				link.text('▼').removeClass('hide').addClass('show');
				children
					.remove()
					.removeClass('selected');
			}
			
			else if (link.is('.show')) {
				link.text('▼').removeClass('show').addClass('hide');
				children
					.removeClass('selected')
					.insertAfter(row);
				
				if (row.is('.selected')) {
					children.addClass('selected');
				}
			}
		});
		
		$('#views-list td.toggle + td span').live('click', function() {
			$(this).parent().click();
			
			return false;
		});
		
		// Collapse by default on long pages:
		if (table.find('tbody tr').length > 17) {
			$('#views-list tr[id] td.toggle a').click();
		}
	});
	
/*-----------------------------------------------------------------------------
	rel[external]
-----------------------------------------------------------------------------*/

	$(window).ready(function() {
		$('a[rel=external]').live("click", function() {
			window.open($(this).attr('href'));
			return false;
		});
	});
})(jQuery.noConflict());