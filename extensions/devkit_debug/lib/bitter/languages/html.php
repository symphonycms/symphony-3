<?php
/*----------------------------------------------------------------------------*/
	
	require_once BITTER_LANGUAGE_PATH . '/xml.php';
	require_once BITTER_LANGUAGE_PATH . '/js.php';
	require_once BITTER_LANGUAGE_PATH . '/php.php';
	
/*------------------------------------------------------------------------------
	Main
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('html-include'),
		Bitter::tag('context-markup html'),
		Bitter::capture('.+', 's'),
		
		Bitter::rule(
			Bitter::id('html-js'),
			Bitter::capture('<script[^<]*(?=</script>)</script>', 'si'),
			
			Bitter::rule(
				Bitter::id('html-js-start'),
				Bitter::capture('^<script[^>]+>', 'is'),
				
				Bitter::id('xml-tag-open')
			),
			Bitter::rule(
				Bitter::id('html-js-end'),
				Bitter::capture('</script>$', 'i'),
				
				Bitter::id('xml-tag-close')
			),
			Bitter::rule(
				Bitter::id('html-js-content'),
				Bitter::capture('.+(?=</script>)', 's'),
				
				Bitter::id('js-include')
			)
		),
		
		Bitter::id('xml-entity'),
		Bitter::id('xml-doctype'),
		Bitter::id('xml-comment'),
		Bitter::id('xml-cdata'),
		Bitter::id('xml-declaration'),
		Bitter::id('xml-tag-close'),
		Bitter::id('xml-tag-open'),
		Bitter::id('php-include')
	);
	
	Bitter::rule(
		Bitter::id('html'),
		
		Bitter::id('html-include')
	);
	
/*----------------------------------------------------------------------------*/
?>