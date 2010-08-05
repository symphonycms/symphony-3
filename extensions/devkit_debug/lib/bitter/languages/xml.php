<?php
/*------------------------------------------------------------------------------
	Entities
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('xml-entity'),
		Bitter::capture('&(#|#x)?([a-z0-9]+)?;?'),
		
		Bitter::rule(
			Bitter::id('xml-entity-valid'),
			Bitter::tag('entity'),
			Bitter::capture('^&([a-z]+|#[0-9]+|#x[0-9a-f]+);', 'i')
		),
		Bitter::rule(
			Bitter::id('xml-entity-error'),
			Bitter::tag('error'),
			Bitter::capture('.+')
		)
	);
	
/*------------------------------------------------------------------------------
	Attributes
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('xml-attribute'),
		Bitter::capture('([a-z][a-z0-9_\-\:]*)(\s*=\s*)?(".*?"|\'.*?\')?', 'is'),
		
		Bitter::rule(
			Bitter::id('xml-attribute-key'),
			Bitter::tag('attribute'),
			Bitter::capture('^[^="\']+=\s*(?=["\'])')
		),
		Bitter::rule(
			Bitter::id('xml-attribute-value-single'),
			Bitter::tag('value'),
			Bitter::capture("'.*?'$", 's'),
			
			Bitter::id('xml-entity')
		),
		Bitter::rule(
			Bitter::id('xml-attribute-value-double'),
			Bitter::tag('value'),
			Bitter::capture('".*?"$', 's'),
			
			Bitter::id('xml-entity')
		),
		Bitter::rule(
			Bitter::id('xml-attribute-error'),
			Bitter::tag('error'),
			Bitter::capture('.+')
		)
	);
	
/*------------------------------------------------------------------------------
	Comments
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('xml-doctype'),
		Bitter::tag('doctype'),
		Bitter::capture('<!DOCTYPE(.*?)>')
	);
	Bitter::rule(
		Bitter::id('xml-comment'),
		Bitter::tag('comment'),
		Bitter::start('<!--'),
		Bitter::stop('-->?'),
		
		Bitter::rule(
			Bitter::id('xml-comment-error'),
			Bitter::tag('error'),
			Bitter::capture('--')
		)
	);
	Bitter::rule(
		Bitter::id('xml-cdata'),
		Bitter::tag('cdata'),
		Bitter::start('<!\[CDATA\['),
		Bitter::stop('\]\]>')
	);
	
/*------------------------------------------------------------------------------
	Declarations
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('xml-declaration'),
		Bitter::tag('declaration open close'),
		Bitter::capture('<\?xml.*?(\??)>', 'i'),
		
		Bitter::rule(
			Bitter::id('xml-declaration-begin'),
			Bitter::tag('begin'),
			Bitter::capture('^<\?xml', 'i')
		),
		Bitter::rule(
			Bitter::id('xml-declaration-end'),
			Bitter::tag('end'),
			Bitter::capture('[\?]>$')
		),
		
		Bitter::id('xml-attribute')
	);
	
/*------------------------------------------------------------------------------
	Tags
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('xml-tag-open'),
		Bitter::capture('<[a-z][a-z0-9_\-\:\.]*(([^>]+)?)>', 'i'),
		Bitter::tag('tag open'),
		
		Bitter::rule(
			Bitter::id('xml-tag-open-begin'),
			Bitter::capture('^<[a-z][a-z0-9_\-\:\.]*', 'i'),
			Bitter::tag('begin')
		),
		Bitter::rule(
			Bitter::id('xml-tag-open-end'),
			Bitter::capture('/>|>$'),
			Bitter::tag('end')
		),
		
		Bitter::id('xml-attribute')
	);
	Bitter::rule(
		Bitter::id('xml-tag-close'),
		Bitter::capture('</[^>]+>?'),
		
		Bitter::rule(
			Bitter::id('xml-tag-close-valid'),
			Bitter::tag('tag close'),
			Bitter::capture('</[a-z][a-z0-9_\-\:\.]*>', 'i')
		),
		Bitter::rule(
			Bitter::id('xml-declaration-error'),
			Bitter::tag('error'),
			Bitter::capture('.+')
		),
		
		Bitter::id('xml-attribute')
	);
	
/*------------------------------------------------------------------------------
	Main
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('xml-text'),
		Bitter::tag('text'),
		Bitter::capture('[^<>]{1,}'),
		
		Bitter::id('xml-entity')
	);
	
	Bitter::rule(
		Bitter::id('xml-include'),
		Bitter::tag('context-markup xml'),
		Bitter::capture('.+', 's'),
		
		Bitter::id('xml-text'),
		Bitter::id('xml-doctype'),
		Bitter::id('xml-comment'),
		Bitter::id('xml-cdata'),
		Bitter::id('xml-declaration'),
		Bitter::id('xml-tag-close'),
		Bitter::id('xml-tag-open')
	);
	
	Bitter::rule(
		Bitter::id('xml'),
		
		Bitter::id('xml-include')
	);
	
/*----------------------------------------------------------------------------*/
?>