/*-----------------------------------------------------------------------------
	Text Box Interface
-----------------------------------------------------------------------------*/
	
	jQuery(document).ready(function() {
		jQuery('.field-textbox').filterTextBox();
		
		jQuery('.filters-duplicator .controls .constructor').click(function() {
			jQuery('.field-textbox:not(.initialised)').filterTextBox();
		});
	});
	
/*---------------------------------------------------------------------------*/