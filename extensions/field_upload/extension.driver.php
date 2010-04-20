<?php

	class Extension_Field_Upload extends Extension {
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

		public function addHeaders($page) {
			if (!$this->addedHeaders) {

				Symphony::Parent()->Page->insertNodeIntoHead(Symphony::Parent()->Page->createScriptElement(URL . '/extensions/field_upload/assets/publish.css'));
				Symphony::Parent()->Page->insertNodeIntoHead(Symphony::Parent()->Page->createStylesheetElement(URL . '/extensions/field_upload/assets/publish.js'));

				$this->addedHeaders = true;
			}
		}
	}
