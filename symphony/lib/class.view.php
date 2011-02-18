<?php

	require_once(LIB . '/class.documentheaders.php');

	class ViewException extends Exception {}

	/**
	* Thought process: Views could stay simple and generic to allow for other
	* kinds of views (i.e. non XML/XSLT-powered). Not sure if this makes any
	* sense at all, but I figured I'd try it.
	*/
	class View {
		const ERROR_VIEW_NOT_FOUND = 0;
		const ERROR_FAILED_TO_LOAD = 1;

		/**
		 * Set default headers — can be overwritten by individual view
		 */
		public function setHeaders() {
			$this->headers->append('Content-Type', 'text/xml;charset=utf-8');
			$this->headers->append('Expires', 'Mon, 12 Dec 1982 06:14:00 GMT');
			$this->headers->append('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
			$this->headers->append('Cache-Control', 'no-cache, must-revalidate, max-age=0');
			$this->headers->append('Pragma', 'no-cache');
		}
	}

	/**
	* Considering the above, I decided to add SymphonyView as a class of
	* view that assumes XML/XSLT and assumes a certain kind of view hierarchy.
	* Includes methods and properties common to all XML/XSLT-powered views
	* (both frontend and administration)
	*/
	Class SymphonyView extends View {
		public $context;
		public $document;
		public $handle;
		public $headers;
		public $location;
		public $params;
		public $path;
		public $stylesheet;

		/**
		* Initializes objects and properties common to all SymphonyView
		* objects
		*/
		public function initialize() {
			// Initialize headers
			$this->headers = new DocumentHeaders;
			$this->setHeaders();

			// Initialize context
			$this->context = new Register;

			// Initialize XML
			$this->document = new XMLDocument;
			$this->document->appendChild(
				$this->document->createElement('data')
			);
			
			//Initialize XSLT
			$this->stylesheet = new XMLDocument;
			
			Widget::init($this->document);
		}

		/**
		* Parses the URL to figure out what View to load
		* 
		* @param	$path		string	View path including URL parameters to attempt to find
		* @param	$expression string	Expression used to match the view driver/conf file. Use printf syntax.
		*/
		public function parseURL($path, $expression = '%s.conf.xml') {
			$parts = preg_split('/\//', $path, -1, PREG_SPLIT_NO_EMPTY);
			$view = null;
			
			while (!empty($parts)) {
				$part = array_shift($parts);
				$file = sprintf(
					'%s%s/%s/' . $expression,
					$this->location, $view, $part, $part
				);
				
				if (!is_file($file)) {
					array_unshift($parts, $part);
					
					break;
				}

				$view = $view . "/{$part}";
			}
			
			if (is_null($view)) {
				throw new ViewException(__('View, %s, could not be found.', array($path)), self::ERROR_VIEW_NOT_FOUND);
			}
			
			return $this->loadFromPath($view, (!empty($parts) ? $parts : null));
		}

		/**
		* Builds the context XML and prepends it to $this->document's root
		*/
		public function buildContextXML($root) {
			$element = $this->document->createElement('context');
			$root->prependChild($element);

			foreach($this->context as $key => $item){
				if(is_array($item->value) && count($item->value) > 1){
					$p = $this->document->createElement($key);
					foreach($item->value as $k => $v){
						$p->appendChild($this->document->createElement((string)$k, (string)$v));
					}
					$element->appendChild($p);
				}
				else{
					$element->appendChild($this->document->createElement($key, (string)$item));
				}
			}
		}

		/**
		* Performs an XSLT transformation on a SymphonyView's $document using its
		* $stylesheet.
		*
		* @param string $directory
		*  Base directory to perform the transformation in
		*
		* @return string containing result of transformation
		*/
		public function transform($directory = null) {
			// Set directory for performing the transformation
			// This is for XSLT import/include I believe.
			// Defaults to root views dir (/workspace/views/ or
			// /symphony/content/). Can be overridden if called
			// with $directory param.
			if (is_null($directory)) {
				$dir = $this->location;
			}
			
			else {
				$dir = $directory;
			}
			
			// Get current directory
			$cwd = getcwd();
			
			// Move to tranformation directory
			chdir($dir);
			
			// Perform transformation
			$output = XSLProc::transform(
				$this->document->saveXML(),
				$this->stylesheet->saveXML(),
				XSLProc::XML,
				array(), array()
			);

			// Move back to original directory
			chdir($cwd);

			if (XSLProc::hasErrors() && !isset($_REQUEST['debug'])) {
				throw new XSLProcException('Transformation Failed');
			}
			
			// HACK: Simple debug output:
			if (isset($_REQUEST['debug'])) {
				$this->document->formatOutput = true;
				
				echo '<pre>', htmlentities($this->document->saveXML()); exit;
			}

			// Return result of transformation
			return $output;
		}
	}

	Class ViewIterator implements Iterator{
		private $_iterator;
		private $_length;
		private $_position;
		private $_current;

		public function __construct($path=NULL, $recurse=true){
			$this->_iterator = new ViewFilterIterator($path, $recurse);
			$this->_length = $this->_position = 0;
			foreach($this->_iterator as $f){
				$this->_length++;
			}
			$this->_iterator->getInnerIterator()->rewind();
		}

		public function current(){
			$path = str_replace(VIEWS, NULL, $this->_iterator->current()->getPathname());

			if(!($this->_current instanceof self) || $this->_current->path != $path){
				$this->_current = new FrontendView();
				$this->_current->loadFromPath($path);
			}
			return $this->_current;
		}

		public function innerIterator(){
			return $this->_iterator;
		}

		public function next(){
			$this->_position++;
			$this->_iterator->next();
		}

		public function key(){
			return $this->_iterator->key();
		}

		public function valid(){
			return $this->_iterator->getInnerIterator()->valid();
		}

		public function rewind(){
			$this->_position = 0;
			$this->_iterator->rewind();
		}

		public function position(){
			return $this->_position;
		}

		public function length(){
			return $this->_length;
		}
	}

	Class ViewFilterIterator extends FilterIterator{
		public function __construct($path=NULL, $recurse=true){
			if(!is_null($path)) $path = VIEWS . '/' . trim($path, '/');
			else $path = VIEWS;

			parent::__construct(
				$recurse == true
					?	new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST)
					:	new DirectoryIterator($path)
			);

		}

		// Only return folders, and only those that have a 'X.config.xml' file within. This characterises a View.
		public function accept(){
			if($this->getInnerIterator()->isDir() == false) return false;
			preg_match('/\/?([^\\\\\/]+)$/', $this->getInnerIterator()->getPathname(), $match); //Find the view handle

			return (file_exists(sprintf('%s/%s.config.xml', $this->getInnerIterator()->getPathname(), $match[1])));
		}
	}
