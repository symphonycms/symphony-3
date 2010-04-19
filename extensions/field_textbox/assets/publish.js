/*-----------------------------------------------------------------------------
	Text Box Interface
-----------------------------------------------------------------------------*/
	
	jQuery(document).ready(function() {
		jQuery('.field-textbox').each(function() {
			var self = jQuery(this);
			var input = self.find('input, textarea');
			
			if (input.attr('length') < 1) return;
			
			var optional = self.find('i');
			var message = optional.text();
			
			var update = function() {
				var length = input.val().length;
				var limit = input.attr('length');
				var remaining = limit - length;
				
				optional
					.text(message.replace('$1', remaining).replace('$2', limit))
					.removeClass('invalid');
				
				if (remaining < 0) {
					optional.addClass('invalid');
				}
			};
			
			input.bind('blur', update);
			input.bind('change', update);
			input.bind('focus', update);
			input.bind('keypress', update);
			input.bind('keyup', update);
			
			update();
		});
	});
	
/*---------------------------------------------------------------------------*/