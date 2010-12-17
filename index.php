<?php

	define('DOCROOT', rtrim(dirname(__FILE__), '\\/'));
	define('DOMAIN', rtrim(rtrim($_SERVER['HTTP_HOST'], '\\/') . dirname($_SERVER['PHP_SELF']), '\\/'));

	require(DOCROOT . '/symphony/bundle.php');
	require(LIB . '/class.controller.php');

	$output = Controller::instance()->renderView();

	header(sprintf('Content-Length: %d', strlen($output)));
	echo $output;

	exit();