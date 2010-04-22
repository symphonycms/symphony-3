<?php
	
	require_once TOOLKIT . '/class.event.php';
	
	Final Class Event%1$s extends Event {

		public function __construct(){

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
				'source' => %8$s,
				'overrides' => %9$s,
				'defaults' => %10$s
			);
		}

		public function allowEditorToParse() {
			return true;
		}
		
		public function trigger(){
			
		}
	}

	return 'Event%1$s';