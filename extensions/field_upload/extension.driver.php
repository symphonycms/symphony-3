<?php

	class Extension_Field_Upload implements iExtension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function about() {
			return array(
				'name'			=> 'Upload',
				'version'		=> '2.0.0',
				'release-date'	=> '2010-04-20',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://rowanlewis.com/',
					'email'			=> 'me@rowanlewis.com'
				),
				'description'	=> 'An upload field that allows features to be plugged in.',
				'type'			=> array(
					'Field', 'Core'
				),
			);
		}
		
	/*-------------------------------------------------------------------------
		Utilites:
	-------------------------------------------------------------------------*/
		
		protected $addedHeaders = false;
		
		public function addHeaders() {
			$page = Symphony::Parent()->Page;
			
			if (!$this->addedHeaders) {
				$page->insertNodeIntoHead($page->createStylesheetElement(URL . '/extensions/field_upload/assets/publish.css'));
				$page->insertNodeIntoHead($page->createScriptElement(URL . '/extensions/field_upload/assets/publish.js'));
				
				$this->addedHeaders = true;
			}
		}
	}
	
	return 'Extension_Field_Upload';