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

			object.find(settings.items).live('click', function(e) {
				var self 	= jQuery(this),
					row 	= self.toggleClass('selected'),
					table 	= object.find('tr');

				row.find('td input').each(function() {
					this.checked = !this.checked
				});

				if(e.shiftKey && row.hasClass('selected')) {
					var rows = row.prevAll('.selected'),
						from = table.index(rows) + 1,
						to = table.index(row);

					table.slice(from, to)
						.addClass('selected')
						.find('input').each(function() {
							this.checked = !this.checked
						});

					// de-select text caused by holding shift
					if (window.getSelection) window.getSelection().removeAllRanges();
				}
			});

			return object;
		});

		return objects;
	};

/*---------------------------------------------------------------------------*/