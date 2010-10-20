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

	/*-------------------------------------------------------------------------
		Utilites:
	-------------------------------------------------------------------------*/

		protected static $addedPublishHeaders = false;
		protected static $addedSettingsHeaders = false;
		protected static $addedFilteringHeaders = false;

		public function addPublishHeaders($page) {
			if ($page and !self::$addedPublishHeaders) {
				$page->insertNodeIntoHead($page->createStylesheetElement(URL . '/extensions/field_join/assets/publish.css'));
				$page->insertNodeIntoHead($page->createScriptElement(URL . '/extensions/field_join/assets/publish.js'));

				self::$addedPublishHeaders = true;
			}
		}

		public function addSettingsHeaders($page) {
			if ($page and !self::$addedSettingsHeaders) {
				$page->insertNodeIntoHead($page->createStylesheetElement(URL . '/extensions/field_join/assets/settings.css'));

				self::$addedSettingsHeaders = true;
			}
		}

		public function addFilteringHeaders($page) {
			if ($page and !self::$addedFilteringHeaders) {
				$page->insertNodeIntoHead($page->createStylesheetElement(URL . '/extensions/field_join/assets/filtering.css'));
				$page->insertNodeIntoHead($page->createScriptElement(URL . '/extensions/field_join/assets/interface.js'));
				$page->insertNodeIntoHead($page->createScriptElement(URL . '/extensions/field_join/assets/filtering.js'));

				self::$addedFilteringHeaders = true;
			}
		}
	}
	
	return 'Extension_Field_Join';
	
?>