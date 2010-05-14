<?php

	require_once 'lib/class.datasource.php';

	Class Extension_DS_Gravatar implements iExtension {
		public function about() {
			return (object)array(
				'name'			=> 'Gravatar DataSource',
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
				'description'	=> 'Fetch avatars from Gravatar.'
			);
		}
		
	/*-------------------------------------------------------------------------
		DataSources and Events:
	-------------------------------------------------------------------------*/
		
		public function getDataSourceTypes() {
			return array(
				(object)array(
					'class'		=> 'GravatarDataSource',
					'name'		=> __('Gravatar')
				)
			);
		}
	}

	return 'Extension_DS_Gravatar';