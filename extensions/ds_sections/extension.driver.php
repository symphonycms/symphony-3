<?php

	require_once 'lib/class.datasource.php';
	require_once 'lib/class.event.php';

	Class Extension_DS_Sections implements iExtension {
		public function about() {
			return (object)array(
				'name'			=> 'Sections DataSource and Event',
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
		
	/*-------------------------------------------------------------------------
		DataSources and Events:
	-------------------------------------------------------------------------*/
		
		public function getDataSourceTypes() {
			return array(
				(object)array(
					'class'		=> 'SectionsDataSource',
					'name'		=> __('Sections')
				)
			);
		}
		
		public function getEventTypes() {
			return array(
				(object)array(
					'class'		=> 'SectionsEvent',
					'name'		=> __('Sections')
				)
			);
		}
	}

	return 'Extension_DS_Sections';