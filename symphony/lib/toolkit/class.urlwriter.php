<?php
	
	class URLWriter {
		protected $url;
		protected $params;
		
		public function __construct($url, array $params) {
			$this->url = $url;
			$this->params =(object)$params;
		}
		
		public function parameters() {
			return $this->params;
		}
		
		public function __clone() {
			$this->params = clone $this->params;
		}
		
		public function __toString() {
			$query = '';
			
			foreach ($this->params as $index => $value) {
				if (is_null($value) or $value == '') {
					$query .= '&' . $index;
				}
				
				else {
					$query .= '&' . $index . '=' . $value;
				}
			}
			
			if ($query == '') return $this->url;
			
			return $this->url . '?' . ltrim($query, '&');
		}
	}