<?php

	Class Layout{
		const SMALL = 'small';
		const MEDIUM = 'medium';
		const LARGE = 'large';
		
		protected $_columns;
		protected $_proportions;
		public $div;

		public function __construct($column) {
			$page = Symphony::Parent()->Page;
			$columns = func_get_args();
			$this->_columns = array();
			$class = array('columns');
			
			if (count($columns) > 4) throw new Exception('Too many columns, a maximum of four may be given.');
			
			foreach ($columns as $index => $column) {
				if ($column == Layout::SMALL || $column == Layout::MEDIUM || $column == Layout::LARGE) {
					$this->_columns[$index + 1] = $page->createElement('div', null, array (
						'class'	=> 'column size-' . $column
					));
					$class[] = $column;
					
					continue;
				}
				
				throw new Exception(sprintf('Invalid column type %s.', var_export($column, true)));
			}
			
			$this->div = $page->createElement('div');
			$this->div->setAttributeArray(array(
				'id'	=> 'layout',
				'class'	=> implode('-', $class)
			));
		}
		
		public function appendToColumn($column, SymphonyDOMElement $element) {
			if (!isset($this->_columns[$column])) {
				throw new Exception(sprintf('Unknown column %s.', var_export($column, true)));
			}
			
			$this->_columns[$column]->appendChild($element);
		}

		public function generate(){
			foreach($this->_columns as $col){
				$this->div->appendChild($col);
			}

			return $this->div;
		}
	}
