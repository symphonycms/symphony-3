<?php
	
	require_once 'lib/class.datasource.php';

	class Extension_DS_DynamicXML implements iExtension {
		public function about() {
			return array(
				'name'			=> 'Dynamic XML',
				'version'		=> '1.0.0',
				'release-date'	=> '2010-02-26',
				'type'			=> array(
					'Data Source', 'Core'
				),
				'author'		=> array(
					'name'			=> 'Symphony Team',
					'website'		=> 'http://symphony-cms.com/',
					'email'			=> 'team@symphony-cms.com'
				),
				'provides'		=> array(
					'datasource_template'
				),
				'description'	=> 'Create data sources from XML fetched over HTTP or FTP.'
			);
		}

		public function prepareDatasource(DataSource $datasource = null, array $data = null) {
			if (is_null($datasource)) {
				$datasource = new DynamicXMLDataSource;
			}
			
			$datasource->prepare($data);
			
			return $datasource;
		}

		public function viewDatasource(DataSource $datasource, SymphonyDOMElement $wrapper, MessageStack $errors) {
			$datasource->view($wrapper, $errors);
		}
	}
	
	return 'Extension_DS_DynamicXML';
