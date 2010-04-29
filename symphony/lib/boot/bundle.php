<?php

	if(!defined('PHP_VERSION_ID')){
    	$version = PHP_VERSION;
    	define('PHP_VERSION_ID', ($version{0} * 10000 + $version{2} * 100 + $version{4}));
	}

	if (PHP_VERSION_ID >= 50300){
	    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
	} 
	else{
	    //error_reporting(E_ALL & ~E_NOTICE);
	    // TODO: Revert this?
	    error_reporting(E_ALL);
	
		// Bad Magic Quotes! You're not wanted here!
		if(get_magic_quotes_gpc() === true) {
			set_magic_quotes_runtime(false);
			General::cleanArray($_SERVER);
			General::cleanArray($_COOKIE);
			General::cleanArray($_GET);
			General::cleanArray($_POST);
		}
		
	}

	header('Expires: Mon, 12 Dec 1982 06:14:00 GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-cache, must-revalidate, max-age=0');
	header('Pragma: no-cache');
		
	require_once(DOCROOT . '/symphony/lib/boot/func.utilities.php');	
	require_once(DOCROOT . '/symphony/lib/boot/defines.php');

	if (!file_exists(CONFIG . '/db.xml')) {
		
		if (file_exists(DOCROOT . '/install.php')) {
			header(sprintf('Location: %s/install.php', URL));
			exit();
		}
		
		die('<h2>Error</h2><p>Could not locate Symphony configuration files. Please check they exist in <code>manifest/conf/</code>.</p>');
	}
