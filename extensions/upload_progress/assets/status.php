<?php
	
	header("Pramga: no-cache");
	header("Cache-Control: no-cache, must-revalidate");
	header("Expires: Thur, 25 May 1988 14:00:00 GMT");
	
	$status = false;
	
	// APC upload progress is enabled:
	if ((boolean)ini_get('apc.rfc1867')) {
		$status = apc_fetch(ini_get('apc.rfc1867_prefix') . $_GET['for']);
		$status = array(
			'bytes_uploaded'	=> (integer)$status['current'],
			'bytes_total'		=> (integer)$status['total']
		);
	}
	
	// Upload Progress extension is enabled:
	else if (function_exists('uploadprogress_get_info')) {
		$status = uploadprogress_get_info($_GET['for']);
		$status = array(
			'bytes_uploaded'	=> (integer)$status['bytes_uploaded'],
			'bytes_total'		=> (integer)$status['bytes_total']
		);
	}
	
	$status['bytes_uploaded'] = max($status['bytes_uploaded'], 0);
	$status['bytes_total'] = max($status['bytes_total'], 1);
	
	if (!headers_sent()) {
		header('content-type: text/json');
		echo json_encode($status);
	}
	
?>