<?php

	Class ConfigurationElement{
		protected $doc;
		protected $path;
		protected $properties;
	
		public function __construct($path){
			$this->properties = new StdClass;
			$this->path = $path;
			if(!file_exists($path)){
				$this->doc = new SimpleXMLElement('<configuration></configuration>');
			}
			else{
				$this->doc = simplexml_load_file($this->path);
				self::__loadVariablesFromNode($this->doc, $this->properties);
			}
		}
	
		protected function __loadVariablesFromNode(SimpleXMLElement $elements, StdClass &$group){
			foreach($elements as $e){
				$name = $e->getName();
				
				if(count($e->children()) > 0){
					$group->$name = new StdClass;
					self::__loadVariablesFromNode($e, $group->$name);
				}
				else{
					$group->$name = (string)$e;
				}
			}
		}
		
		public function properties(){
			return $this->properties;
		}
		
		public function __get($name){
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
	
		protected static function __generateXML(StdClass $elements, DOMNode &$parent){
			foreach($elements as $name => $e){
				if($e instanceof StdClass){
					$element = $parent->ownerDocument->createElement($name);
					self::__generateXML($e, $element);
				}
				else{
					$element = $parent->ownerDocument->createElement($name, (string)$e);
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
