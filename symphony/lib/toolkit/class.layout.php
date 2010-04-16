<?php

	Class Layout{
		protected $properties;
		protected $content;
		
		public function __construct($col1=NULL, $col2=NULL, $col3=NULL, $col4=NULL){
			$arguments = func_get_args();
			
			$this->properties = array(
				'cols'				=> count($arguments),
				'proportions'		=> $arguments
			);
			$this->content = array(
				'container'	=> new XMLElement('div', NULL, array('id' => 'layout'))
			);
			
			foreach($this->properties['proportions'] as $key => $val){
				$this->content['columns'][$key + 1] = new XMLElement(
					'div',
					NULL,
					array (
						'class' => 'column ' . $val
					)
				);
			}
		}
		
		public function appendToCol(XMLElement $element, $col){
			if($this->content['columns'][$col]){
				$this->content['columns'][$col]->appendChild($element);
			}
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
