jQuery(document).ready(function() {
	jQuery('.field-upload').each(function() {
		var field = jQuery(this);
		var file = field.find('.file');
		var upload = field.find('.upload');
		var hidden = upload.find('input[type = "hidden"]');
		
		if (file.length) {
			var clear = jQuery('<a />')
				.text('Change')
				.prependTo(field.find('.label'));
			var keep = jQuery('<a />')
				.text('Keep')
				.prependTo(field.find('.label'))
				.hide();
			
			clear.bind('click', function() {
				var file = jQuery('<input type="file" />');
				
				file.attr('name', hidden.attr('name'));
				
				clear.hide();
				keep.show();
				upload.show();
				hidden.remove();
				file.appendTo(upload);
			});
			
			keep.bind('click', function() {
				var file = upload.find('input[type = "file"]');
				
				clear.show();
				keep.hide();
				upload.hide();
				file.remove();
				hidden.appendTo(upload);
			});
			
			upload.hide();
		}
	});
});