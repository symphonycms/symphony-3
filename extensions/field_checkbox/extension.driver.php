<?php
	
	Class Extension_Field_Checkbox implements iExtension {
		public function about() {
			return array(
				'name'			=> 'Checkbox',
				'version'		=> '2.0.0',
				'release-date'	=> '2010-04-20',
				'author'		=> array(
					'name'			=> 'Symphony Team',
					'website'		=> 'http://symphony-cms.com/',
					'email'			=> 'team@symphony-cms.com'
				),
				'type'			=> array(
					'Field', 'Core'
				),
			);
		}
	}
	
	return 'Extension_Field_Checkbox';