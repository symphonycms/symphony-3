<?php

	class Duplicator {
		protected $duplicator;
		protected $instances;
		protected $page;
		protected $tabs;
		protected $templates;
		
		public function __construct($add, $orderable = true) {
			$this->child_name = $child_name;
			$this->page = Symphony::Parent()->Page;
			
			$this->duplicator = $this->page->createElement('div');
			$this->duplicator->addClass('duplicator-widget');
			
			$controls = $this->page->createElement('div');
			$controls->addClass('controls');
			$this->duplicator->appendChild($controls);
			
			$add_item = $this->page->createElement('a', $add);
			$add_item->addClass('add');
			$controls->appendChild($add_item);
			
			$this->templates = $this->page->createElement('ol');
			$this->templates->setAttribute('class', 'templates');
			$this->duplicator->appendChild($this->templates);
			
			$content = $this->page->createElement('div');
			$content->addClass('content');
			$this->duplicator->appendChild($content);
			
			$this->tabs = $this->page->createElement('ol');
			$this->tabs->addClass('tabs');
			$content->appendChild($this->tabs);
			
			if ($orderable) {
				$this->tabs->addClass('orderable-widget');
			}
			
			$this->instances = $this->page->createElement('ol');
			$this->instances->addClass('instances');
			$content->appendChild($this->instances);
		}
		
		public function __call($name, $params) {
			return call_user_method_array($name, $this->duplicator, $params);
		}
		
		public function __get($name) {
			return $this->duplicator->$name;
		}
		
		public function __set($name, $value) {
			return $this->duplicator->$name = $value;
		}
		
		public function createTemplate() {
			$template = $this->page->createElement('li');
			$template->addClass('template');
			$this->templates->appendChild($template);
			
			return $template;
		}
		
		public function createTab($name, $type = null) {
			$tab = $this->page->createElement('li');
			$tab->addClass('tab');
			$tab->addClass('orderable-item');
			$tab->addClass('orderable-handle');
			
			if (!$this->tabs->hasChildNodes()) {
				$tab->addClass('active');
			}
			
			$this->tabs->appendChild($tab);
			
			$span = $this->page->createElement('span');
			$span->addClass('name');
			$span->setValue($name);
			$tab->appendChild($span);
			
			if (!is_null($type)) {
				$em = $this->page->createElement('em');
				$em->setValue($type);
				$tab->appendChild($em);
			}
			
			$remove = $this->page->createElement('a');
			$remove->addClass('remove');
			$remove->setValue('×');
			$tab->appendChild($remove);
			
			return $tab;
		}
		
		public function createInstance() {
			$instance = $this->page->createElement('li');
			$instance->addClass('instance');
			
			if (!$this->instances->hasChildNodes()) {
				$instance->addClass('active');
			}
			
			$this->instances->appendChild($instance);
			
			return $instance;
		}
		
		public function appendTo(SymphonyDOMElement $wrapper) {
			$wrapper->appendChild($this->duplicator);
		}
	}
