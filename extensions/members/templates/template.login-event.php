<?php
	
	require_once EXTENSIONS . '/members/lib/class.login-event.php';
	
	class Event_%1$s extends Member_Login_Event {
		public function __construct() {
			parent::__construct();
			
			$this->_about = (object)array(
				'name'			=> %2$s,
				'author'		=> (object)array(
					'name'			=> %3$s,
					'website'		=> %4$s,
					'email'			=> %5$s
				),
				'version'		=> %6$s,
				'release-date'	=> %7$s
			);
			
			$this->_parameters = (object)array(
				'root-element'	=> %8$s,
				'section'		=> %9$s,
				'create-cookie'	=> %10$s,
				'overrides'		=> %11$s,
				'defaults'		=> %12$s
			);
		}
		
		public function allowEditorToParse() {
			return true;
		}
	}
	
	return 'Event_%1$s';
