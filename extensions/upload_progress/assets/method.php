<?php
	
	header("Pramga: no-cache");
	header("Cache-Control: no-cache, must-revalidate");
	header("Expires: Thur, 25 May 1988 14:00:00 GMT");
	
	$method = false;
	$value = base_convert(microtime(true), 10, 36);
	
	// APC upload progress is enabled:
	if ((boolean)ini_get('apc.rfc1867')) {
		$method = array(
			'input_name'	=> ini_get('apc.rfc1867_name'),
			'input_value'	=> $value
		);
	}
	
	// Upload Progress extension is enabled:
	else if (function_exists('uploadprogress_get_info')) {
		$method = array(
			'input_name'	=> 'UPLOAD_IDENTIFIER',
			'input_value'	=> $value
		);
	}
	
	if (!headers_sent()) {
		header('content-type: text/json');
		echo json_encode($method);
	}
	
?>