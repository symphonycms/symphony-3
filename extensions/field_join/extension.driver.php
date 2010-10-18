<?php

	class Extension_Field_Join implements iExtension {
		public function about() {
			return (object)array(
				'name'			=> 'Join',
				'version'		=> '1.0.0',
				'release-date'	=> '2010-10-18',
				'author'		=> (object)array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://rowanlewis.com',
					'email'			=> 'me@rowanlewis.com'
				),
				'type'			=> array(
					'Field', 'Core'
				)
			);
		}
	}
	
	return 'Extension_Field_Join';
	
?>