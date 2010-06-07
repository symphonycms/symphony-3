<?php

	define('DOCROOT', rtrim(dirname(__FILE__), '\\/'));
	define('DOMAIN', rtrim(rtrim($_SERVER['HTTP_HOST'], '\\/') . dirname($_SERVER['PHP_SELF']), '\\/'));

	require(DOCROOT . '/symphony/bundle.php');

	function renderer($handle){
		$path = NULL;
		
		if(file_exists(realpath($handle))){
			$path = realpath($handle);
			
			// Ensure the renderer is child of DOCROOT. Closes potential
			// security hole
			if(substr($path, 0, strlen(DOCROOT)) != DOCROOT){
				$path = NULL;
			}
		}
		
		elseif(file_exists(LIB . "/class.{$handle}.php")){
			$path = LIB . "/class.{$handle}.php";
		}
		
		if(is_null($path)){
			throw new Exception('Invalid Symphony renderer handle specified.');
		}

		$classname = require_once($path);
		return call_user_func("{$classname}::instance");
	}

	$handle = (isset($_GET['symphony-renderer'])
		? $_GET['symphony-renderer']
		: 'frontend');
	
	$output = renderer($handle)->display(getCurrentPage());

	header(sprintf('Content-Length: %d', strlen($output)));
	echo $output;

	exit();
