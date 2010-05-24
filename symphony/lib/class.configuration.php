<?php

	Class ConfigurationElement{
		
		const NODE_OBJECT = 'object';
		const NODE_ARRAY = 'array';
		
		protected $doc;
		protected $path;
		protected $properties;
	
		public function __construct($path){
			$this->properties = NULL;
			$this->path = $path;
			if(!file_exists($path)){
				$this->doc = new SimpleXMLElement('<configuration></configuration>');
			}
			else{
				$this->doc = simplexml_load_file($this->path);
				self::__loadVariablesFromNode($this->doc, $this->properties);
			}
		}
	
		protected function __loadVariablesFromNode(SimpleXMLElement $elements, &$group){
			
			$node_type = NULL;
			
			// Determine the type of group being created. Either an array or stdclass object
			if(isset($elements->item)){
				$group = array();
				$node_type = self::NODE_ARRAY;
			}
			else{
				$group = new StdClass;
				$node_type = self::NODE_OBJECT;
			}
			
			foreach($elements as $e){

				$name = $e->getName();
				
				// If the name is 'item' use a numeric index
				if($name == 'item'){
					$index = count($group);
				}
				else{
					$index = $name;
				}
				
				if(count($e->children()) > 0){
					$value = NULL;
					self::__loadVariablesFromNode($e, $value);
				}
				else{
					$value = (string)$e;
				}
				
				// Using the value above, construct the group
				if($node_type == self::NODE_ARRAY){
					$group[$index] = $value;
				}
				
				else{
					$group->$name = $value;
				}
				
			}
		}
		
		public function properties(){
			return $this->properties;
		}
		
		public function __get($name){
			if (!isset($this->properties->$name)) return null;
			
			return $this->properties->$name;
		}
	
		public function __set($name, $value){
			$this->properties->$name = $value;
		}
	
		public function __unset($name){
			unset($this->properties->$name);
		}
	
		public function save(){
			file_put_contents($this->path, (string)$this);
		}
	
		public function __toString(){
			$doc = new DOMDocument('1.0', 'UTF-8');
			$doc->formatOutput = true;
		
			$root = $doc->createElement('configuration');
			$doc->appendChild($root);
		
			self::__generateXML($this->properties, $root);

			return $doc->saveXML();
		}
	
		protected static function __generateXML($elements, DOMNode &$parent){
			
			foreach($elements as $name => $e){
				
				$element_name = (is_numeric($name) ? 'item' : $name);
				
				if($e instanceof StdClass || is_array($e)){
					$element = $parent->ownerDocument->createElement($element_name);
					self::__generateXML($e, $element);
				}
				
				else{
					$element = $parent->ownerDocument->createElement($element_name, (string)$e);
				}
				
				$parent->appendChild($element);
			}
		}
	}
	
	Class Configuration{
		private static $objects;

		public function __call($handle, array $param){
			if(!isset(self::$objects[$handle]) || !(self::$objects[$handle] instanceof ConfigurationElement)){
				$class = 'ConfigurationElement';
				if(isset($param[0]) && strlen(trim($param[0])) > 0) $class = $param[0];
				self::$objects[$handle] = new $class(CONFIG . "/{$handle}.xml");
			}
			return self::$objects[$handle];
		}
		
		public function save(){
			foreach(self::$objects as $handle => $obj){
				$obj->save();
			}
		}
	}
