jQuery.fn.bitter = function(custom_options) {
	return this.map(function() {
		var self = jQuery(this);
		var options = jQuery.extend(
			{}, jQuery.fn.bitter.defaults,
			custom_options
		);
		
		jQuery.ajax({
			type:		'POST',
			url:		options.handler,
			dataType:	'html',
			data:		{
				language:	options.language,
				format:		options.format,
				debug:		options.debug,
				source:		self.text()
			},
			success:	function(data) {
				self.html(data);
			},
			error:		function(data) {
				if (data.responseText.length) {
					self.html(data.responseText);
				}
			}
		});
	});
};

jQuery.fn.bitter.defaults = {
	language:	null,
	format:		'default',
	debug:		false,
	handler:	'./jquery.bitter.php'
};