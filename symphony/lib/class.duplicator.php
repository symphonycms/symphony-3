<?php

	class Duplicator {
		protected $duplicator;
		protected $instances;
		protected $page;
		protected $tabs;
		protected $templates;
		protected $reflection;
		
		public function __construct($add, $orderable = true) {
			$this->child_name = $child_name;
			$this->page = Symphony::Parent()->Page;
			
			$this->duplicator = $this->page->createElement('div');
			$this->duplicator->addClass('duplicator-widget');
			
			$controls = $this->page->createElement('ol');
			$controls->addClass('controls');
			$this->duplicator->appendChild($controls);
			
			$add_item = $this->page->createElement('li', $add);
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
				$this->duplicator->addClass('orderable-widget');
			}
			
			$this->instances = $this->page->createElement('ol');
			$this->instances->addClass('instances');
			$content->appendChild($this->instances);
			
			$this->reflection = new ReflectionObject($this->duplicator);
		}
		
		public function __call($name, $params) {
			$method = $this->reflection->getMethod($name);
			
			return $method->invokeArgs($this->duplicator, $params);
		}
		
		public function __get($name) {
			return $this->duplicator->$name;
		}
		
		public function __set($name, $value) {
			return $this->duplicator->$name = $value;
		}
		
		public function createTemplate($name, $type = null) {
			$template = $this->page->createElement('li');
			$template->addClass('template');
			$this->templates->appendChild($template);
			
			$span = $this->page->createElement('span');
			$span->addClass('name');
			$span->setValue($name);
			$template->appendChild($span);
			
			if (!is_null($type)) {
				$em = $this->page->createElement('em');
				$em->setValue($type);
				$span->appendChild($em);
			}
			
			return $template;
		}
		
		public function createInstance($name, $type = null) {
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
				$span->appendChild($em);
			}
			
			$remove = $this->page->createElement('a');
			$remove->addClass('remove');
			$remove->setValue('×');
			$tab->appendChild($remove);
			
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
