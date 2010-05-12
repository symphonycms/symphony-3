<?php
	
	class Extension_Field_User implements iExtension {
		public function about() {
			return (object)array(
				'name'			=> 'User',
				'version'		=> '2.0.0',
				'release-date'	=> '2010-04-20',
				'author'		=> (object)array(
					'name'			=> 'Symphony Team',
					'website'		=> 'http://symphony-cms.com/',
					'email'			=> 'team@symphony-cms.com'
				),
				'type' => array(
					'Field', 'Core'
				),
			);
		}
	}
	
	return 'Extension_Field_User';