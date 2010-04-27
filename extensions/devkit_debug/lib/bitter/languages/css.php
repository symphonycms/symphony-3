<?php
/*------------------------------------------------------------------------------
	Selectors
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('css-selector'),
		Bitter::tag('selector'),
		Bitter::capture('(#|\.|:)?[a-z][a-z0-9_\-]*|[*~>]')
	);
	
/*------------------------------------------------------------------------------
	Comments
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('css-comment'),
		Bitter::tag('comment block'),
		Bitter::start('/\*'),
		Bitter::stop('\*/')
	);
	
/*------------------------------------------------------------------------------
	Properties
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('css-block'),
		Bitter::start('\{'),
		Bitter::stop('\}'),
		
		Bitter::rule(
			Bitter::id('css-property-proprietry'),
			Bitter::tag('property proprietry'),
			Bitter::capture('-(moz|ms|webkit|khtml|o)-[a-z\-]+(?=:)', 's')
		),
		Bitter::rule(
			Bitter::id('css-property'),
			Bitter::tag('property'),
			Bitter::capture('[a-z\-]+(?=:)', 's')
		),
		Bitter::rule(
			Bitter::id('css-value'),
			Bitter::tag('value'),
			Bitter::capture('(?<=:)[^;]+', 's'),
			
			Bitter::id('css-string-single'),
			Bitter::id('css-string-double'),
			Bitter::id('css-number'),
			Bitter::id('css-color'),
			Bitter::id('css-keyword'),
			Bitter::id('css-keyword-proprietry')
		),
		
		Bitter::id('css-comment')
	);
	
/*------------------------------------------------------------------------------
	Keywords
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('css-keyword'),
		Bitter::tag('keyword'),
		Bitter::capture('(!important|\b(xx-small|xx-large|xor|x-small|x-large|wider|wait|w-resize|visual|visible|url|uppercase|upper-roman|upper-latin|upper-alpha|underline|ultra-expanded|ultra-condensed|transparent|top|thin|thick|textfield|textarea|text-top|text-bottom|text|table-row-group|table-row|table-header-group|table-footer-group|table-column-group|table-column|table-cell|table-caption|table|sw-resize|super|sub|status-bar|static|square-button|square|space|source-over|source-out|source-in|source-atop|solid|smaller|small-caption|small-caps|small|sliderthumb-vertical|sliderthumb-horizontal|slider-vertical|slider-horizontal|show|serif|separate|semi-expanded|semi-condensed|searchfield-results-decoration|searchfield-results-button|searchfield-decoration|searchfield-cancel-button|searchfield|se-resize|scrollbartrack-vertical|scrollbartrack-horizontal|scrollbarthumb-vertical|scrollbarthumb-horizontal|scrollbargripper-vertical|scrollbargripper-horizontal|scrollbarbutton-up|scrollbarbutton-right|scrollbarbutton-left|scrollbarbutton-down|scroll|sans-serif|s-resize|run-in|rtl|right|ridge|rgba|rgb|repeat-y|repeat-x|repeat|relative|rect|read-write-plaintext-only|read-write|read-only|radio|push-button|progress|pre|pointer|plus-lighter|plus-darker|overline|outside|outset|open-quote|oblique|nw-resize|nowrap|normal|none|no-repeat|no-open-quote|no-close-quote|ne-resize|narrower|n-resize|move|middle|message-box|menulist-textfield|menulist-text|menulist-button|menulist|menu|medium|ltr|lowercase|lower-roman|lower-latin|lower-greek|lower-alpha|logical|listitem|listbox|list-item|lines|line-through|lighter|left|larger|large|justify|italic|invert|inside|inset|inline-table|inline-block|inline|inherit|ignore|icon|hsla|hsl|highlight|hide|hidden|help|groove|georgian|fixed|extra-expanded|extra-condensed|expanded|embed|element|e-resize|double|dotted|discard|disc|destination-over|destination-out|destination-in|destination-atop|default|decimal-leading-zero|decimal|dashed|crosshair|counters|counter|copy|condensed|collapse|close-quote|clear|circle|checkbox|center|caret|caption|capitalize|button-bevel|button|bottom|both|bolder|bold|block|blink|bidi-override|baseline|auto|attr|armenian|after-white-space|absolute))\b')
	);
	
	Bitter::rule(
		Bitter::id('css-keyword-proprietry'),
		Bitter::tag('keyword proprietry'),
		Bitter::capture('(-(moz|ms|webkit|khtml|o)-[a-z\-]+)\b')
	);
	
/*------------------------------------------------------------------------------
	Strings
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('css-string-escape'),
		Bitter::tag('escape'),
		Bitter::capture('\\\(\'|"|[a-f0-9]{1,6})')
	);
	Bitter::rule(
		Bitter::id('css-string-single'),
		Bitter::tag('string single'),
		Bitter::start("'"),
		Bitter::stop("'"),
		
		Bitter::id('css-string-escape')
	);
	Bitter::rule(
		Bitter::id('css-string-double'),
		Bitter::tag('string double'),
		Bitter::start('"'),
		Bitter::stop('"'),
		
		Bitter::id('css-string-escape')
	);
	
/*------------------------------------------------------------------------------
	Numbers
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('css-number'),
		Bitter::tag('number'),
		Bitter::capture('(-|\b)([0-9]*\.[0-9]+|[0-9]+)(%|(px|pt|em|en|ex|in)\b)?', 'i')
	);
	
/*------------------------------------------------------------------------------
	Colours
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('css-color'),
		Bitter::tag('color'),
		Bitter::capture('(#([a-f0-9]{3}|[a-f0-9]{6})|\b(yellowgreen|yellow|whitesmoke|white|wheat|violet|turquoise|tomato|thistle|teal|tan|steelblue|springgreen|snow|slategrey|slategray|slateblue|skyblue|silver|sienna|seashell|seagreen|sandybrown|salmon|saddlebrown|royalblue|rosybrown|red|purple|powderblue|plum|pink|peru|peachpuff|papayawhip|palevioletred|paleturquoise|palegreen|palegoldenrod|orchid|orangered|orange|olivedrab|olive|oldlace|navy|navajowhite|moccasin|mistyrose|mintcream|midnightblue|mediumvioletred|mediumturquoise|mediumspringgreen|mediumslateblue|mediumseagreen|mediumpurple|mediumorchid|mediumblue|mediumaquamarine|maroon|magenta|linen|limegreen|lime|lightyellow|lightsteelblue|lightslategrey|lightslategray|lightskyblue|lightseagreen|lightsalmon|lightpink|lightgrey|lightgreen|lightgray|lightgoldenrodyellow|lightcyan|lightcoral|lightblue|lemonchiffon|lawngreen|lavenderblush|lavender|khaki|ivory|indigo|indianred|hotpink|honeydew|grey|greenyellow|green|gray|goldenrod|gold|ghostwhite|gainsboro|fuchsia|forestgreen|floralwhite|firebrick|dodgerblue|dimgrey|dimgray|deepskyblue|deeppink|darkviolet|darkturquoise|darkslategrey|darkslategray|darkslateblue|darkseagreen|darksalmon|darkred|darkorchid|darkorange|darkolivegreen|darkmagenta|darkkhaki|darkgrey|darkgreen|darkgray|darkgoldenrod|darkcyan|darkblue|cyan|crimson|cornsilk|cornflowerblue|coral|chocolate|chartreuse|cadetblue|burlywood|brown|blueviolet|blue|blanchedalmond|black|bisque|beige|azure|aquamarine|aqua|antiquewhite|aliceblue))\b', 'i')
	);
	
/*------------------------------------------------------------------------------
	Main
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('css-include'),
		Bitter::tag('context-source css'),
		Bitter::capture('.+', 's'),
		
		Bitter::id('css-comment'),
		Bitter::id('css-selector'),
		Bitter::id('css-block')
	);
	
	Bitter::rule(
		Bitter::id('css'),
		
		Bitter::id('css-include')
	);
	
/*----------------------------------------------------------------------------*/
?>