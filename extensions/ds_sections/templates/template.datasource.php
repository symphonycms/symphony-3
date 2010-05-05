<?php
	
	require_once EXTENSIONS . '/ds_sections/lib/class.datasource.php';
	
	Final Class DataSource%1$s extends SectionsDataSource {

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
				'root-element' => %8$s,
				'limit' => %9$s,
				'page' => %10$s,
				'section' => %11$s,
				'conditions' => %12$s,
				'filters' => %13$s,
				'redirect-404-on-empty' => %14$s,
				'append-pagination' => %15$s,
				'append-sorting' => %16$s,
				'sort-field' => %17$s,
				'sort-order' => %18$s,
				'included-elements' => %19$s,
				'parameter-output' => %20$s,
			);
		}

		public function allowEditorToParse() {
			return true;
		}
	}

	return 'DataSource%1$s';