<?php

	define('DOCROOT', rtrim(dirname(__FILE__), '\\/'));
	define('DOMAIN', rtrim(rtrim($_SERVER['HTTP_HOST'], '\\/') . dirname($_SERVER['PHP_SELF']), '\\/'));

	require(DOCROOT . '/symphony/bundle.php');

	function renderer($handle){
		if(!file_exists(LIB . "/class.{$handle}.php")){
			throw new Exception('Invalid Symphony renderer handle specified.');
		}

		$classname = require_once(LIB . "/class.{$handle}.php");
		return call_user_func("{$classname}::instance");
	}

	$handle = (isset($_GET['symphony-renderer'])
		? $_GET['symphony-renderer']
		: 'frontend');
	
	header('Expires: Mon, 12 Dec 1982 06:14:00 GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-cache, must-revalidate, max-age=0');
	header('Pragma: no-cache');
	
	$output = renderer($handle)->display(getCurrentPage());

	header(sprintf('Content-Length: %d', strlen($output)));
	echo $output;

	exit();
