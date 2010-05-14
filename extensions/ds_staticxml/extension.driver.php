<?php

	require_once 'lib/class.datasource.php';
		
	class Extension_DS_StaticXML implements iExtension {
		public function about() {
			return (object)array(
				'name'			=> 'Static XML DataSource',
				'version'		=> '1.0.0',
				'release-date'	=> '2010-02-26',
				'type'			=> array(
					'Data Source', 'Core'
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
		DataSources:
	-------------------------------------------------------------------------*/
		
		public function getDataSourceTypes() {
			return array(
				(object)array(
					'class'		=> 'StaticXMLDataSource',
					'name'		=> __('Static XML')
				)
			);
		}
	}
	
	return 'Extension_DS_StaticXML';
