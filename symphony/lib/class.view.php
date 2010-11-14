<?php

	require_once(LIB . '/class.documentheaders.php');

	Class ViewException extends Exception {}

	Abstract Class View{

		const ERROR_VIEW_NOT_FOUND = 0;
		const ERROR_FAILED_TO_LOAD = 1;

		abstract function buildOutput(XMLDocument &$Document);

		abstract function loadFromURL($path);

		abstract function loadFromPath($path, array $params);

		public function templatePathname(){
			return sprintf('%s/%s.xsl', $this->path, $this->handle);
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
				$this->_current = FrontendView::loadFromPath($path);
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