<?php

	define('DOCROOT', rtrim(dirname(__FILE__), '\\/'));
	define('DOMAIN', rtrim(rtrim($_SERVER['HTTP_HOST'], '\\/') . dirname($_SERVER['PHP_SELF']), '\\/'));

	require(DOCROOT . '/symphony/lib/boot/bundle.php');

	function renderer($handle){
		if(!file_exists(CORE . "/class.{$handle}.php")){
			throw new Exception('Invalid Symphony Renderer mode specified.');
		}

		$classname = require_once(CORE . "/class.{$handle}.php");
		return call_user_func("{$classname}::instance");
	}

	$handle = (isset($_GET['symphony-renderer'])
		? $_GET['symphony-renderer']
		: 'frontend');
	
	$output = renderer($handle)->display(getCurrentPage());

	header(sprintf('Content-Length: %d', strlen($output)));
	echo $output;

	exit();
