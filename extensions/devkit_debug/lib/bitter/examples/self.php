<!DOCTYPE title PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<title>Bitter Examples</title>
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<link rel="stylesheet" type="text/css" href="../assets/theme.css" />
<?php
	
	function display($file, $language = null) {
		global $bitter;
		
		if (isset($_REQUEST['file']) and $_REQUEST['file'] != $file) return;
		
		if (!$language) {
			$language = array_pop(explode('.', $file));
		}
		
		$data = file_get_contents($file);
		
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
	
	display('bitter.php');
	display('assets/jquery.bitter.php');
	display('assets/jquery.bitter.js');
	display('assets/jquery.js');
	display('assets/theme.css');
	display('examples/ajax.php', 'html');
	display('examples/direct.php', 'html');
	display('examples/files/example.css');
	display('examples/files/example.js');
	display('examples/files/example.php');
	display('examples/files/example.xsl');
	display('examples/self.php', 'html');
	display('formats/default.php');
	display('formats/tabsize-2.php');
	display('formats/tabsize-4.php');
	display('languages/css.php');
	display('languages/html.php');
	display('languages/js.php');
	display('languages/php.php');
	display('languages/xml.php');
	display('languages/xsl.php');
	
?>