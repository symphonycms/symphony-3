<?php

	Class Layout{
	
		const SMALL = 'small';
		const MEDIUM = 'medium';
		const LARGE = 'large';
		
		protected $properties;
		protected $content;
		protected $page;
		
		public function __construct($column){
			$arguments = func_get_args();
			$this->page = Symphony::Parent()->Page;
			
			if (count($arguments) > 4) throw new Exception('Too many columns, a maximum of four may be given.');
			
			$this->properties = array(
				'cols'				=> count($arguments),
				'proportions'		=> $arguments
			);
			$this->content = array(
				'container'	=> $this->page->createElement('div', NULL, array('id' => 'layout'))
			);

			foreach($this->properties['proportions'] as $index => $column){
				if ($column == Layout::SMALL || $column == Layout::MEDIUM || $column == Layout::LARGE) {
					$this->content['columns'][$index + 1] = $this->page->createElement('div', NULL, array('class' => 'column ' . $column));
					continue;
				}
				throw new Exception(sprintf('Invalid column type %s.', var_export($column, true)));
			}
		}

		public function appendToColumn($column, SymphonyDOMElement $element) {
			if (!isset($this->content['columns'][$column])) {
				throw new Exception(sprintf('Unknown column %s.', var_export($column, true)));
			}
			
			$this->content['columns'][$column]->appendChild($element);
		}

		public function generate(){
			###
			# Delegate: LayoutPreGenerate
			# Description: Allows developers to access the layout content
			#			   before it is appended to the page.
			ExtensionManager::instance()->notifyMembers('LayoutPreGenerate', '/backend/', &$this->content);

			foreach($this->content['columns'] as $col){
				$this->content['container']->appendChild($col);
			}
			return $this->content['container'];
		}
	}
