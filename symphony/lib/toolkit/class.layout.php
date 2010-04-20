<?php

	class Layout {
		const SMALL = 'small';
		const LARGE = 'large';
		
		protected $class;
		protected $layout;
		protected $page;
		
		public function __construct() {
			$this->class = 'type-';
			$this->page = Symphony::Parent()->Page;
			$this->layout = $this->page->createElement('div');
			$this->layout->setAttribute('id', 'layout');
		}
		
		public function createColumn($size) {
			if ($size != Layout::SMALL && $size != Layout::LARGE) {
				throw new Exception(sprintf('Invalid column size %s.', var_export($size, true)));
			}
			
			$column = $this->page->createElement('div');
			$column->setAttribute('class', 'column ' . $size);
			$this->layout->appendChild($column);
			$this->class .= substr($size, 0, 1);
			
			return $column;
		}
		
		public function appendTo(SymphonyDOMElement $wrapper) {
			$this->layout->setAttribute('class', $this->class);
			
			###
			# Delegate: LayoutPreGenerate
			# Description: Allows developers to access the layout content
			#			   before it is appended to the page.
			ExtensionManager::instance()->notifyMembers('LayoutPreGenerate', '/backend/', &$this->layout);
			
			$wrapper->appendChild($this->layout);
		}
	}
