<?php
	
	if(!defined('PHP_VERSION_ID')){
		$version = PHP_VERSION;
		define('PHP_VERSION_ID', ($version{0} * 10000 + $version{2} * 100 + $version{4}));
	}
	
	error_reporting(E_ALL & ~E_NOTICE);
	
	// Bad Magic Quotes! You're not wanted here!
	if (PHP_VERSION_ID < 50300 and get_magic_quotes_gpc() === true) {
		set_magic_quotes_runtime(false);
		General::cleanArray($_SERVER);
		General::cleanArray($_COOKIE);
		General::cleanArray($_GET);
		General::cleanArray($_POST);
	}
	
	require_once(DOCROOT . '/symphony/lib/include.utilities.php');	
	require_once(DOCROOT . '/symphony/defines.php');
	
	if(!file_exists(CONFIG . '/db.xml')){
		if (file_exists(DOCROOT . '/install.php')){
			header(sprintf('Location: %s/install.php', URL));
			exit();
		}
		
		die('<h2>Error</h2><p>Could not locate Symphony configuration files. Please check they exist in <code>manifest/conf/</code>.</p>');
	}