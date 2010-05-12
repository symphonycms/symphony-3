<?php

	require_once 'lib/class.datasource.php';
	require_once 'lib/class.event.php';

	Class Extension_DS_Sections implements iExtension {
		public function about() {
			return (object)array(
				'name'			=> 'Sections',
				'version'		=> '1.0.0',
				'release-date'	=> '2010-03-02',
				'type'			=> array(
					'Data Source', 'Event', 'Core'
				),
				'author'		=> (object)array(
					'name'			=> 'Symphony Team',
					'website'		=> 'http://symphony-cms.com/',
					'email'			=> 'team@symphony-cms.com'
				),
				'provides'		=> array(
					'datasource_template'
				),
				'description'	=> 'Create data sources from an XML string.'
			);
		}
		
		public function prepareDatasource(DataSource $datasource = null, array $data = null) {
			if (is_null($datasource)) {
				$datasource = new SectionsDataSource;
			}
			
			$datasource->prepare($data);
			
			return $datasource;
		}

		public function viewDatasource(DataSource $datasource, SymphonyDOMElement $wrapper, MessageStack $errors) {
			$datasource->view($wrapper, $errors);
		}
		
		public function prepareEvent(Event $event = null, array $data = null) {
			if (is_null($event)) {
				$event = new SectionsEvent;
			}
			
			$event->prepare($data);
			
			return $event;
		}

		public function viewEvent(Event $event, SymphonyDOMElement $wrapper, MessageStack $errors) {
			$event->view($wrapper, $errors);
		}
	}

	return 'Extension_DS_Sections';