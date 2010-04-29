<!DOCTYPE title PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<title>Bitter Examples</title>
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<link rel="stylesheet" type="text/css" href="../assets/theme.css" />
<?php
	
	function display($file) {
		global $bitter;
		
		if (isset($_REQUEST['file']) and $_REQUEST['file'] != $file) return;
		
		$language = array_pop(explode('.', $file));
		$data = file_get_contents('./examples/files/' . $file);
		
		if (!$bitter) {
			$bitter = new Bitter();
			$bitter->loadFormat('tabsize-4');
		}
		
		$bitter->loadLanguage($language);
		
		echo '<p><a href="?file=', $file, '">', $file, '</a></p>';
		
		try {
			$source = $bitter->process($data);
			
			echo '<pre class="language-', $language,'">', $source, '</pre>';
			
		} catch (Exception $error) {
			$message = $error->getMessage() . "\n\n" . $error->getTraceAsString();
			
			echo '<pre>', Bitter::encode($message), '</pre>';
		}
	}
	
	chdir('..');
	require_once './bitter.php';
	
	display('example.css');
	display('example.js');
	display('example.php');
	display('example.xsl');
	
?>