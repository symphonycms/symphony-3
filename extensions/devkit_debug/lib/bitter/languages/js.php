<?php
/*------------------------------------------------------------------------------
	Strings
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('js-string-escape'),
		Bitter::tag('escape'),
		Bitter::capture('\\\([bfnOrtv\'\"\\\]|x[a-fA-F0-9]{2}|h[a-fA-F0-9]{4}|[0-7]{1,3})')
	);
	Bitter::rule(
		Bitter::id('js-string-single'),
		Bitter::tag('string single'),
		Bitter::start("'"),
		Bitter::stop("'"),
		
		Bitter::id('js-string-escape')
	);
	Bitter::rule(
		Bitter::id('js-string-double'),
		Bitter::tag('string double'),
		Bitter::start('"'),
		Bitter::stop('"'),
		
		Bitter::id('js-string-escape')
	);
	
/*------------------------------------------------------------------------------
	Regexp
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('js-regexp-escape'),
		Bitter::tag('escape'),
		Bitter::capture('\\\([bBdDfnrsStvwW0/]|c[A-Z]|x[a-fA-F0-9]{2}|h[a-fA-F0-9]{4}|[0-7]{1,3})')
	);
	Bitter::rule(
		Bitter::id('js-regexp'),
		Bitter::tag('regexp'),
		Bitter::start("/"),
		Bitter::stop("/[gim]*"),
		
		Bitter::id('js-regexp-escape')
	);
	
/*------------------------------------------------------------------------------
	Numbers
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('js-number-float'),
		Bitter::capture("\b[0-9]*\.[0-9]+\b"),
		Bitter::tag('number float')
	);
	Bitter::rule(
		Bitter::id('js-number-integer'),
		Bitter::capture("\b[0-9]+\b"),
		Bitter::tag('number integer')
	);
	
/*------------------------------------------------------------------------------
	Comments
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('js-comment-block'),
		Bitter::tag('comment block'),
		Bitter::start('/\*'),
		Bitter::stop('\*/')
	);
	Bitter::rule(
		Bitter::id('js-comment-line'),
		Bitter::tag('comment line'),
		Bitter::capture("//.*", 'm')
	);
	
/*------------------------------------------------------------------------------
	Keywords
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('js-keyword'),
		Bitter::tag('keyword'),
		Bitter::capture('\b(with|while|volatile|void|var|typeof|try|true|transient|throws|throw|this|synchronized|switch|super|static|short|return|public|protected|private|package|null|new|native|long|interface|int|instanceof|in|import|implements|if|goto|function|for|float|finally|final|false|extends|export|enum|else|double|do|delete|default|debugger|continue|const|class|char|catch|case|byte|break|boolean|abstract)\b', 'i')
	);
	
/*------------------------------------------------------------------------------
	Variables
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('js-variable-normal'),
		Bitter::tag('variable'),
		Bitter::capture('[$a-z_][$a-z0-0_]*(\.[$a-z_][$a-z0-0_]*)*', 'i')
	);
	Bitter::rule(
		Bitter::id('js-variable-function'),
		Bitter::tag('variable'),
		Bitter::capture('[$a-z_][$a-z0-0_]*(\.[$a-z_][$a-z0-0_]*)*(?=[$a-z0-9_]*\s*\()', 'i'),
		
		Bitter::rule(
			Bitter::id('js-variable-function-call'),
			Bitter::tag('function'),
			Bitter::capture('(\.)?[$a-z_][$a-z0-0_]*$', 'i')
		)
	);
	
/*------------------------------------------------------------------------------
	Objects
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('js-object-key'),
		Bitter::tag('object-key'),
		Bitter::capture('[$a-z_][$a-z0-0_]*(\.[$a-z_][$a-z0-0_]*)*(?=:)', 'i')
	);
	
/*------------------------------------------------------------------------------
	Main
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('js-include'),
		Bitter::tag('context-source js'),
		Bitter::capture('.+', 's'),
		
		Bitter::id('js-comment-block'),
		Bitter::id('js-comment-line'),
		Bitter::id('js-object-key'),
		Bitter::id('js-regexp'),
		Bitter::id('js-string-single'),
		Bitter::id('js-string-double'),
		Bitter::id('js-number-float'),
		Bitter::id('js-number-integer'),
		Bitter::id('js-keyword'),
		Bitter::id('js-variable-function'),
		Bitter::id('js-variable-normal')
	);
	
	Bitter::rule(
		Bitter::id('js'),
		
		Bitter::id('js-include')
	);
	
/*----------------------------------------------------------------------------*/
?>