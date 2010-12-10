<?php

	require_once(LIB . '/class.symphony.php');
	require_once(LIB . '/class.xmldocument.php');
	require_once(LIB . '/class.lang.php');

	Class Renderer extends Symphony {

		protected static $Headers;
		protected static $Context;
		protected static $Document;
		protected static $Template;

		protected $docroot;

		/**
		 * Construct a Renderer object
		 */
		public function __construct() {
			parent::__construct();

			// Initialize Headers
			self::$Headers = new DocumentHeaders;
			$this->setHeaders();

			// Initialize XML
			self::$Document = new XMLDocument;
			self::$Document->appendChild(
				self::$Document->createElement('root')
			);
			Widget::init(self::$Document);

			// Initialize Context object
			self::$Context = new Register;
			$this->initializeContext();
		}

		/**
		 *
		 */
		public static function instance() {
			if(!(self::$_instance instanceof Renderer))
				self::$_instance = new self;

			return self::$_instance;
		}

		/**
		 * Set default headers—can be overwritten later
		 */
		public function setHeaders() {
			self::$Headers->append('Content-Type', 'text/xml;charset=utf-8');
			self::$Headers->append('Expires', 'Mon, 12 Dec 1982 06:14:00 GMT');
			self::$Headers->append('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
			self::$Headers->append('Cache-Control', 'no-cache, must-revalidate, max-age=0');
			self::$Headers->append('Pragma', 'no-cache');
		}

		/**
		 * Set the renderer's XSLT stylesheet
		 */
		public function setTemplate($xsl) {
			self::$Template = $xsl;
		}

		/**
		 * Initialize contextual XML (formerly params)
		 */
		public function initializeContext() {

			self::$Context->register(array(
				'system'	=> array(
					'site-name'				=> Symphony::Configuration()->core()->symphony->sitename,
					'site-url' => URL,
					'symphony-version'	=> Symphony::Configuration()->core()->symphony->version
				),
				'date'		=> array (
					'today' 			=> DateTimeObj::get('Y-m-d'),
					'current-time'		=> DateTimeObj::get('H:i'),
					'this-year'			=> DateTimeObj::get('Y'),
					'this-month'		=> DateTimeObj::get('m'),
					'this-day'			=> DateTimeObj::get('d'),
					'timezone'			=> date_default_timezone_get()
				)
			));
		}

		/**
		 * Add system XML and transform $Document
		 */
		public function render() {

			$root = self::$Document->documentElement;
			$element = self::$Document->createElement('context');
			$root->prependChild($element);

			foreach(self::$Context as $key => $item){
				if(is_array($item->value) && count($item->value) > 1){
					$p = self::$Document->createElement($key);
					foreach($item->value as $k => $v){
						$p->appendChild(self::$Document->createElement((string)$k, (string)$v));
					}
					$element->appendChild($p);
				}
				else{
					$element->appendChild(self::$Document->createElement($key, (string)$item));
				}
			}
			// When the XSLT executes, it uses the CWD as set here

			$cwd = getcwd();
			chdir($this->docroot);
			$output = XSLProc::transform(self::$Document->saveXML(), self::$Template, XSLProc::XML, array(), array());
			chdir($cwd);

			if(XSLProc::hasErrors()){
				throw new XSLProcException('Transformation Failed');
			}
			return $output;

		}

	}

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