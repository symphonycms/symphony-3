<?php
	require_once(LIB . '/class.administrationpage.php');

	Class contentSystemLog extends AdministrationPage{

		public function build(){

			if(!is_file(ACTIVITY_LOG)) throw new AdministrationPageNotFoundException;

			header('Content-Type: text/plain');
			readfile(ACTIVITY_LOG);
			exit();
		}

	}
