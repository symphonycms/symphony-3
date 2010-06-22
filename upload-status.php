<?php
	
	header('content-type: text/json');
	
	echo json_encode(uploadprogress_get_info($_GET['for']));
	
?>