<!DOCTYPE title PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<title>Bitter Examples</title>
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<link rel="stylesheet" type="text/css" href="../assets/theme.css" />
<script type="text/javascript" src="../assets/jquery.js"></script>
<script type="text/javascript" src="../assets/jquery.bitter.js"></script>
<script type="text/javascript">
	
	$(document).ready(function() {
		// Point it at the HTTP handler:
		$.fn.bitter.defaults.handler = '../assets/jquery.bitter.php';
		
		// Use the 'tabsize-4' format:
		$.fn.bitter.defaults.format = 'tabsize-4';
		
		// Enable debug mode globally:
		$.fn.bitter.defaults.debug = true;
		
		$('pre.language-css').bitter({
			language:	'css'
		});
		$('pre.language-js').bitter({
			language:	'js'
		});
		$('pre.language-html').bitter({
			language:	'html'
		});
		$('pre.language-php').bitter({
			language:	'php'
		});
		$('pre.language-xml').bitter({
			language:	'xml'
		});
		$('pre.language-xsl').bitter({
			language:	'xsl'
		});
	});
	
</script>
<?php
	
	function display($file) {
		if (isset($_REQUEST['file']) and $_REQUEST['file'] != $file) return;
		
		$language = array_pop(explode('.', $file));
		$data = file_get_contents('./files/' . $file);
		$data = htmlentities($data);
		
		echo '<p><a href="?file=', $file, '">', $file, '</a></p><pre class="language-', $language,'">', $data, '</pre>';
	}
	
	display('example.css');
	display('example.js');
	display('example.php');
	display('example.xsl');
	
?>