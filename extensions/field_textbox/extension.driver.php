<?php
	
	class Extension_Field_TextBox extends Extension {
		public function about() {
			return array(
				'name'			=> 'Text Box',
				'version'		=> '2.0.17',
				'release-date'	=> '2010-03-22',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://rowanlewis.com/',
					'email'			=> 'me@rowanlewis.com'
				),
				'description' => 'An enhanced text input field.',
				'type'			=> array(
					'Field', 'Core'
				),
			);
		}
		
	/*-------------------------------------------------------------------------
		Utilites:
	-------------------------------------------------------------------------*/
		
		protected $addedPublishHeaders = false;
		protected $addedSettingsHeaders = false;
		protected $addedFilteringHeaders = false;
		
		public function addPublishHeaders($page) {
			if ($page and !$this->addedPublishHeaders) {
				$page->addStylesheetToHead(URL . '/extensions/textboxfield/assets/publish.css', 'screen', 10251840);
				$page->addScriptToHead(URL . '/extensions/textboxfield/assets/publish.js', 10251840);
				
				$this->addedPublishHeaders = true;
			}
		}
		
		public function addSettingsHeaders($page) {
			if ($page and !$this->addedSettingsHeaders) {
				$page->addStylesheetToHead(URL . '/extensions/textboxfield/assets/settings.css', 'screen', 10251840);
				
				$this->addedSettingsHeaders = true;
			}
		}
		
		public function addFilteringHeaders($page) {
			if ($page and !$this->addedFilteringHeaders) {
				$page->addScriptToHead(URL . '/extensions/textboxfield/assets/interface.js', 10251840);
				$page->addScriptToHead(URL . '/extensions/textboxfield/assets/filtering.js', 10251841);
				$page->addStylesheetToHead(URL . '/extensions/textboxfield/assets/filtering.css', 'screen', 10251840);
				
				$this->addedFilteringHeaders = true;
			}
		}
	}
	
?>
