<?php
	
	class Extension_Field_User extends Extension {
		public function about() {
			return array(
				'name'			=> 'User',
				'version'		=> '2.0.0',
				'release-date'	=> '2010-02-16',
				'author'		=> array(
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
