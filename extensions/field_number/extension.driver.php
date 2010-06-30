<?php

	Class Extension_Field_Number implements iExtension{

		public function about(){
			return (object)array(
				'name' 			=> 'Number',
				'version' 		=> '2.0.0',
				'release-date' 	=> '2010-06-18',
				'author' 		=> (object)array(
					'name'			=> 'Symphony Team',
					'website'		=> 'http://symphony-cms.com/',
					'email'			=> 'team@symphony-cms.com'
				),
				'type'			=> array(
					'Field'
				)
		 	);
		}
	}

	return 'Extension_Field_Number';