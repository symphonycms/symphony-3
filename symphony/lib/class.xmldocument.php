<?php

	require_once('class.messagestack.php');

	Class XMLDocument extends DOMDocument{

		protected $errors;
		protected static $_errorLog;

		public function __construct($version='1.0', $encoding='utf-8'){
			parent::__construct($version, $encoding);
			$this->registerNodeClass('DOMDocument', 'XMLDocument');
			$this->registerNodeClass('DOMElement', 'SymphonyDOMElement');

			$this->preserveWhitespace = false;
			$this->formatOutput = false;
			$this->errors = new MessageStack;
		}

		public function xpath($query, DOMNode $node = null){
			$xpath = new DOMXPath($this);
			
			if ($node) {
				return $xpath->query($query, $node);
			}
			
			return $xpath->query($query);
		}

		public function flushLog(){
			$this->errors->flush();
		}

		public function loadXML($source, $options = 0){

			$this->flushLog();

			libxml_use_internal_errors(true);

			$result = parent::loadXML($source, $options);

			self::processLibXMLerrors($this->errors);

			return $result;
		}

		static function processLibXMLerrors(MessageStack $errors){
			foreach(libxml_get_errors() as $error){
				$error->type = $type;
				$errors->append(NULL, $error);
			}

			libxml_clear_errors();
		}

		public function hasErrors(){
			return (bool)($this->errors instanceof MessageStack && $this->errors->valid());
		}

		public function getErrors(){
			return $this->errors;
		}

		##	Overloaded Methods for DOMDocument
		public function createElement($name, $value = null, array $attributes = array()){
			$element = parent::createElement($name);
			if(!is_null($value)) $element->setValue($value);
			$element->setAttributeArray($attributes);

			return $element;
		}
	}

	##	Convenience Methods for DOMElement
	Class SymphonyDOMElement extends DOMElement {
		public function prependChild(DOMNode $node) {
			if (is_null($this->firstChild)) {
				$this->appendChild($node);
			}

			else {
				$this->insertBefore($node, $this->firstChild);
			}
		}

		public function setValue($value) {
			$this->removeChildNodes();
			
			//	TODO: Possibly might need to Remove existing Children before adding..
			if($value instanceof DOMElement || $value instanceof DOMDocumentFragment) {
				$this->appendChild($value);
			}

			elseif(is_array($value) && !empty($value)) {
				foreach($value as $v) {
					$this->appendChild($v);
				}
			}

			elseif(!is_null($value) && is_string($value)) {
				//$this->nodeValue = $value;
				$this->appendChild(new DOMText($value));
			}
		}

		public function setAttributeArray(array $attributes) {
			if(is_array($attributes) && !empty($attributes)) {
				foreach($attributes as $key => $val){
					$this->setAttribute($key, $val);
				}
			}
		}
		
		public function removeChildNodes() {
			while ($this->hasChildNodes() === true) {
				$this->removeChild($this->firstChild);
			}
		}

		public function remove() {
			$this->parentNode->removeChild($this);
		}
		
		public function wrapWith(DOMElement $wrapper) {
			$this->parentNode->replaceChild($wrapper, $this);
			$wrapper->appendChild($this);
		}

		public function __toString(){
			$doc = new DOMDocument('1.0', 'UTF-8');
			$doc->formatOutput = true;

			$doc->importNode($this, true);

			return $doc->saveHTML();
		}

	}