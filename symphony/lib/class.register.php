<?php

	Class Parameter{

		public $value;
		public $key;

		public function __construct($key, $value){
			$this->value = $value;
			$this->key = $key;
		}

		public function __toString(){
			if(is_array($this->value)) return implode(',', $this->value);
			return (!is_null($this->value) ? (string)$this->value : '');
		}
	}

	Final Class Register implements Iterator{

		private $parameters;

		private $position;
		private $keys;

		public function register(array $params){
			foreach($params as $key => $value) $this->$key = $value;
		}

		public function __construct(){
			$this->parameters = array();
			$this->position = 0;
		}

		public function __set($name, $value){
			$this->parameters[$name] = new Parameter($name, $value);
			$this->keys = array_keys($this->parameters);
		}

		public function __get($name){
			if(isset($this->parameters[$name])){
				return $this->parameters[$name];
			}
			throw new Exception("No such parameter '{$name}'");
		}

		public function __isset($name){
			return (isset($this->parameters[$name]) && ($this->parameters[$name] instanceof Parameter));
		}

		public function __unset($name){
			unset($this->parameters[$name]);
		}

		public function current(){
			return current($this->parameters);
		}

		public function next(){
			$this->position++;
			next($this->parameters);
		}

		public function position(){
			return $this->position;
		}

		public function rewind(){
			reset($this->parameters);
			$this->position = 0;
		}

		public function key(){
			return $this->keys[$this->position];
		}

		public function length(){
			return count($this->parameters);
		}

		public function valid(){
			return $this->position < $this->length();
		}

		public function toArray(){
			$result = array();
			foreach($this as $key => $parameter){
				$result[$key] = (string)$parameter;
			}
			return $result;
		}
	}

?>