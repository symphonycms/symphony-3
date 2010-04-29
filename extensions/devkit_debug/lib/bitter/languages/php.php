<?php
/*------------------------------------------------------------------------------
	Strings
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('php-string-single'),
		Bitter::start("'"),
		Bitter::stop("'"),
		Bitter::tag('string single'),
		
		Bitter::rule(
			Bitter::id('php-string-single-escape'),
			Bitter::capture('\\\[\']'),
			Bitter::tag('escape')
		)
	);
	Bitter::rule(
		Bitter::id('php-string-double'),
		Bitter::start('"'),
		Bitter::stop('"'),
		Bitter::tag('string double'),
		
		Bitter::rule(
			Bitter::id('php-string-double-escape'),
			Bitter::capture('\\\([nrtvf$"\\\]|x[a-fA-F0-9]{1,2}|[0-7]{1,3})'),
			Bitter::tag('escape')
		),
		
		Bitter::id('php-variable-function'),
		Bitter::id('php-variable-normal')
	);
	
/*------------------------------------------------------------------------------
	Numbers
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('php-number-float'),
		Bitter::capture('([+-]|\b)(([0-9]+|([0-9]*[\.][0-9]+)|([0-9]+[\.][0-9]*))[eE][+-]?[0-9]+)\b', 's'),
		Bitter::tag('number float')
	);
	Bitter::rule(
		Bitter::id('php-number-integer-decimal'),
		Bitter::capture("([+-]|\b)([1-9][0-9]*|0)\b"),
		Bitter::tag('number integer decimal')
	);
	Bitter::rule(
		Bitter::id('php-number-integer-octal'),
		Bitter::capture("([+-]|\b)0[0-7]+\b"),
		Bitter::tag('number integer octal')
	);
	Bitter::rule(
		Bitter::id('php-number-integer-hexadecimal'),
		Bitter::capture("([+-]|\b)0[xX][0-9a-fA-F]+\b"),
		Bitter::tag('number integer hexadecimal')
	);
	
/*------------------------------------------------------------------------------
	Comments
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('php-comment-block'),
		Bitter::capture('/\*.*?\*/', 's'),
		
		Bitter::rule(
			Bitter::id('php-comment-block-doc'),
			Bitter::capture('/\*\*.*%', 's'),
			Bitter::tag('comment block doc'),
			
			Bitter::rule(
				Bitter::id('php-comment-docword'),
				Bitter::capture('@[a-z][a-z_\-]+', 'i'),
				Bitter::tag('docword')
			)
		),
		
		Bitter::rule(
			Bitter::id('php-comment-block-normal'),
			Bitter::capture('.*', 's'),
			Bitter::tag('comment block')
		)
	);
	Bitter::rule(
		Bitter::id('php-comment-line'),
		Bitter::capture('(#|//).*', 'm'),
		Bitter::tag('comment line')
	);
	
/*------------------------------------------------------------------------------
	Keywords
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('php-keyword'),
		Bitter::capture('\b(__NAMESPACE__|__METHOD__|__FUNCTION__|__FILE__|__DIR__|__CLASS__|xor|while|var|use|unset|try|true|throw|switch|string|str|stdClass|static|self|return|require_once|require|public|protected|private|print|php_user_filter|parent|or|old_function|object|null|new|namespace|list|isset|interface|integer|int|instanceof|include_once|include|implements|if|goto|global|function|foreach|for|float|final|false|extends|exit|Exception|eval|endwhile|endswitch|endif|endforeach|endfor|enddeclare|empty|elseif|else|echo|do|Directory|die|default|declare|continue|const|Closure|clone|class|cfunction|catch|case|break|boolean|bool|as|array|and|abstract)\b', 'i'),
		Bitter::tag('keyword')
	);
	Bitter::rule(
		Bitter::id('php-keyword-class'),
		Bitter::capture('\b(class|new|extends|implements|instanceof)\s+[a-z_][a-z0-9_]+', 'i'),
		
		Bitter::rule(
			Bitter::id('php-define-class-name'),
			Bitter::capture('[a-z_][a-z0-9_]+$', 'i'),
			Bitter::tag('class defined')
		),
		
		Bitter::id('php-keyword')
	);
	Bitter::rule(
		Bitter::id('php-keyword-function'),
		Bitter::capture('\b(function)\s+[a-z_][a-z0-9_]+', 'i'),
		
		Bitter::rule(
			Bitter::id('php-keyword-function-name'),
			Bitter::capture('[a-z_][a-z0-9_]+$', 'i'),
			Bitter::tag('function defined')
		),
		
		Bitter::id('php-keyword')
	);
	Bitter::rule(
		Bitter::id('php-keyword-class-access'),
		Bitter::capture('[a-z_][a-z0-9_]+::\$*[a-z_][a-z0-9_]*\(?', 'i'),
		
		Bitter::rule(
			Bitter::id('php-keyword-class-access-name'),
			Bitter::capture('^.+?(?=::)', 'i'),
			Bitter::tag('keyword class')
		),
		Bitter::rule(
			Bitter::id('php-keyword-class-access-variable'),
			Bitter::capture('::\$+[a-z_][a-z0-9_]*', 'i'),
			Bitter::tag('variable')
		),
		Bitter::rule(
			Bitter::id('php-keyword-class-access-function'),
			Bitter::capture('::[a-z_][a-z0-9_]*\(', 'i'),
			
			Bitter::rule(
				Bitter::id('php-keyword-class-access-function-call'),
				Bitter::capture('[^\(]+', 'i'),
				Bitter::tag('function')
			)
		),
		Bitter::rule(
			Bitter::id('php-keyword-class-access-constant'),
			Bitter::capture('::[a-z_][a-z0-9_]*\b', 'i'),
			Bitter::tag('constant')
		)
	);
	
/*------------------------------------------------------------------------------
	Variables
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('php-variable-normal'),
		Bitter::capture('(->\$*|(::)?\$+)[a-z_][a-z0-9_]*(->\$*[a-z_][a-z0-9_]*)*', 'i'),
		Bitter::tag('variable')
	);
	Bitter::rule(
		Bitter::id('php-variable-function'),
		Bitter::capture('(->\$*|(::)?\$+)[a-z_][a-z0-9_]*(->\$*[a-z_][a-z0-9_]*)*(?=[a-z0-9_]*\s*\()', 'i'),
		Bitter::tag('variable'),
		
		Bitter::rule(
			Bitter::id('php-variable-function-call'),
			Bitter::capture('(\$+|->\$*)[a-z_][a-z0-9_]*$', 'i'),
			Bitter::tag('function called')
		)
	);
	
/*------------------------------------------------------------------------------
	Functions
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('php-function'),
		Bitter::capture('\b[a-z_][a-z0-9_]*\(', 'i'),
		
		Bitter::rule(
			Bitter::id('php-function-call'),
			Bitter::capture('[^\(]+', 'i'),
			Bitter::tag('function called')
		)
	);
	
/*------------------------------------------------------------------------------
	Constants
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('php-constant'),
		Bitter::capture('\b[a-z_][a-z0-9_]*\b', 'i'),
		Bitter::tag('constant')
	);
	
/*------------------------------------------------------------------------------
	Main
------------------------------------------------------------------------------*/
	
	Bitter::rule(
		Bitter::id('php-include'),
		Bitter::tag('context-source php'),
		Bitter::start('<\?(php|=)?'),
		Bitter::stop('\?>'),
		
		Bitter::id('php-string-single'),
		Bitter::id('php-string-double'),
		Bitter::id('php-number-float'),
		Bitter::id('php-number-integer-decimal'),
		Bitter::id('php-number-integer-octal'),
		Bitter::id('php-number-integer-hexadecimal'),
		Bitter::id('php-comment-block'),
		Bitter::id('php-comment-line'),
		Bitter::id('php-variable-function'),
		Bitter::id('php-variable-normal'),
		Bitter::id('php-keyword-class'),
		Bitter::id('php-keyword-class-access'),
		Bitter::id('php-keyword-function'),
		Bitter::id('php-keyword'),
		Bitter::id('php-function'),
		Bitter::id('php-constant')
	);
	
	Bitter::rule(
		Bitter::id('php'),
		
		Bitter::id('php-include')
	);
	
/*----------------------------------------------------------------------------*/
?>