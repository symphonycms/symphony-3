<?php

	define('DOCROOT', rtrim(dirname(__FILE__), '\\/'));
	define('DOMAIN', rtrim(rtrim($_SERVER['HTTP_HOST'], '\\/') . dirname($_SERVER['PHP_SELF']), '\\/'));

	require(DOCROOT . '/symphony/bundle.php');

	function renderer($handle){
		
		if(file_exists(realpath($handle))){
			$path = realpath($handle);
		}
		
		elseif(file_exists(LIB . "/class.{$handle}.php")){
			$path = LIB . "/class.{$handle}.php";
		}
		
		else{
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
