<?php

	class Extension_Field_TextBox extends Extension {
		public function about() {
			return array(
				'name'			=> 'Text Box',
				'version'		=> '2.0.17',
				'release-date'	=> '2010-04-21',
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
				$this->insertNodeIntoHead($this->createStylesheetElement(URL . '/extensions/textboxfield/assets/publish.css'));
				$this->insertNodeIntoHead($this->createScriptElement(URL . '/extensions/textboxfield/assets/publish.js'));

				$this->addedPublishHeaders = true;
			}
		}

		public function addSettingsHeaders($page) {
			if ($page and !$this->addedSettingsHeaders) {
				$this->insertNodeIntoHead($this->createStylesheetElement(URL . '/extensions/textboxfield/assets/settings.css'));

				$this->addedSettingsHeaders = true;
			}
		}

		public function addFilteringHeaders($page) {
			if ($page and !$this->addedFilteringHeaders) {
				$this->insertNodeIntoHead($this->createStylesheetElement(URL . '/extensions/textboxfield/assets/filtering.css'));
				$this->insertNodeIntoHead($this->createScriptElement(URL . '/extensions/textboxfield/assets/interface.js'));
				$this->insertNodeIntoHead($this->createScriptElement(URL . '/extensions/textboxfield/assets/filtering.js'));

				$this->addedFilteringHeaders = true;
			}
		}
	}

?>
