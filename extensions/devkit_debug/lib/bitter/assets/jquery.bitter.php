<?php
//------------------------------------------------------------------------------
	
	chdir('../');
	require_once './bitter.php';
	
//------------------------------------------------------------------------------
	
	header('content-type: text/plain; charset=utf8', true, 500);
	
	try {
		$bitter = new Bitter();
		$bitter->loadLanguage($_REQUEST['language']);
		$bitter->loadFormat($_REQUEST['format']);
		
		$source = $bitter->process(
			stripslashes($_REQUEST['source'])
		);
		
		header('content-type: text/plain; charset=utf8', true, 200);
		
		echo $source;
	}
	
	catch (Exception $error) {
		if (isset($_REQUEST['debug']) and $_REQUEST['debug'] == 'true') {
			$message = $error->getMessage() . "\n\n" . $error->getTraceAsString();
			
			echo Bitter::encode($message);
		}
	}
	
//------------------------------------------------------------------------------
?>