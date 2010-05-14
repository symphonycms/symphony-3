<?php
	
	require_once EXTENSIONS . '/ds_gravatar/lib/class.datasource.php';
	
	final class DataSource%1$s extends GravatarDataSource {
		public function __construct(){
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
				'addresses'		=> %9$s,
				'default'		=> %10$s,
				'size'			=> %11$s,
				'rating'		=> %12$s
			);
		}
		
		public function allowEditorToParse(){
			return true;
		}
	}
	
	return 'DataSource%1$s';