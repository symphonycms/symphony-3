<?php

	Class Extension_Field_Link implements iExtension{

		public function about(){
			return (object)array('name' => 'Link',
						 'version' => '2.0.0',
						 'release-date' => '2010-02-02',
						 'author' => (object)array('name' => 'Symphony Team',
										   'website' => 'http://www.symphony-cms.com',
										   'email' => 'team@symphony-cms.com'),
				'type' => array(
					'Field', 'Core'
				)
			);
		}
	}
	
	return 'Extension_Field_Link';