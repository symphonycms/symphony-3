<?php
/*----------------------------------------------------------------------------*/
	
	require_once BITTER_LANGUAGE_PATH . '/xml.php';
	
/*------------------------------------------------------------------------------
	Attributes
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('xsl-attribute'),
		Bitter::capture('(match|select|test)(\s*=\s*)?(".*?"|\'.*?\')?', 'is'),
		
		Bitter::id('xml-attribute-key'),
		Bitter::rule(
			Bitter::id('xsl-attribute-value-single'),
			Bitter::tag('value xpath'),
			Bitter::capture("'.*?'$", 's'),
			
			Bitter::id('xml-entity')
		),
		Bitter::rule(
			Bitter::id('xsl-attribute-value-double'),
			Bitter::tag('value xpath'),
			Bitter::capture('".*?"$', 's'),
			
			Bitter::id('xml-entity')
		),
		Bitter::id('xml-attribute-error')
	);
	
	Bitter::rule(
		Bitter::id('xsl-xpath'),
		Bitter::tag('xpath'),
		Bitter::capture('\{.*?\}|\$[a-z][a-z0-9_\-]*')
	);
	
	Bitter::rule(
		Bitter::id('xml-attribute-value-single'),
		Bitter::tag('value'),
		Bitter::capture("'.*?'$"),
		
		Bitter::id('xml-entity'),
		Bitter::id('xsl-xpath')
	);
	
	Bitter::rule(
		Bitter::id('xml-attribute-value-double'),
		Bitter::tag('value'),
		Bitter::capture('".*?"$'),
		
		Bitter::id('xml-entity'),
		Bitter::id('xsl-xpath')
	);
	
/*------------------------------------------------------------------------------
	Tags
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('xsl-tag-open'),
		Bitter::capture('<[a-z][a-z0-9_\-\:]*(([^>]+)?)>', 'i'),
		Bitter::tag('tag open'),
		
		Bitter::rule(
			Bitter::id('xsl-tag-open-begin'),
			Bitter::capture('^<[a-z][a-z0-9_\-\:]*', 'i'),
			Bitter::tag('begin')
		),
		Bitter::rule(
			Bitter::id('xsl-tag-open-end'),
			Bitter::capture('/>|>$'),
			Bitter::tag('end')
		),
		
		Bitter::id('xsl-attribute'),
		Bitter::id('xml-attribute')
	);
	
/*------------------------------------------------------------------------------
	Main
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('xsl-include'),
		Bitter::tag('context-markup xml xsl'),
		Bitter::capture('.+', 's'),
		
		Bitter::id('xml-text'),
		Bitter::id('xml-entity'),
		Bitter::id('xml-doctype'),
		Bitter::id('xml-comment'),
		Bitter::id('xml-cdata'),
		Bitter::id('xml-declaration'),
		Bitter::id('xml-tag-close'),
		Bitter::id('xsl-tag-open')
	);
	
	Bitter::rule(
		Bitter::id('xsl'),
		
		Bitter::id('xsl-include')
	);
	
/*----------------------------------------------------------------------------*/
?>