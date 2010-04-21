<?php

	Class EventException extends Exception {}

	Class EventFilterIterator extends FilterIterator{
		public function __construct($path){
			parent::__construct(new DirectoryIterator($path));
		}

		public function accept(){
			if($this->isDir() == false && preg_match('/^.+\.php$/i', $this->getFilename())){
				return true;
			}
			return false;
		}
	}

	Class EventIterator implements Iterator{

		private $position;
		private $events;

		public function __construct(){

			$this->events = array();
			$this->position = 0;

			foreach(new EventFilterIterator(EVENTS) as $file){
				$this->events[] = $file->getPathname();
			}

			foreach(new DirectoryIterator(EXTENSIONS) as $dir){
				if(!$dir->isDir() || $dir->isDot() || !is_dir($dir->getPathname() . '/events')) continue;

				foreach(new EventFilterIterator($dir->getPathname() . '/events') as $file){
					$this->events[] = $file->getPathname();
				}
			}

		}

		public function length(){
			return count($this->events);
		}

		public function rewind(){
			$this->position = 0;
		}

		public function current(){
			return $this->events[$this->position]; //Datasource::loadFromPath($this->events[$this->position]);
		}

		public function key(){
			return $this->position;
		}

		public function next(){
			++$this->position;
		}

		public function valid(){
			return isset($this->events[$this->position]);
		}
	}

	Abstract Class Event{
		
		const PRIORITY_HIGH = 3;
		const PRIORITY_NORMAL = 2;
		const PRIORITY_LOW = 1;
		
		protected static $_loaded;
		
		protected $_about;
		protected $_parameters;
		
		public function &about(){
			return $this->_about;
		}

		public function &parameters(){
			return $this->_parameters;
		}
			
		public static function load($pathname){
			if(!is_array(self::$_loaded)){
				self::$_loaded = array();
			}

			if(!is_file($pathname)){
		        throw new EventException(
					__('Could not find Event <code>%s</code>. If the Event was provided by an Extension, ensure that it is installed, and enabled.', array(basename($pathname)))
				);
			}

			if(!isset(self::$_loaded[$pathname])){
				self::$_loaded[$pathname] = require($pathname);
			}

			$obj = new self::$_loaded[$pathname];
			$obj->parameters()->pathname = $pathname;

			return $obj;

		}

		public static function loadFromName($name){
			return self::load(self::__find($name) . "/{$name}.php");
		}

		protected static function __find($name){

		    if(is_file(EVENTS . "/{$name}.php")) return EVENTS;
		    else{

				$extensions = ExtensionManager::instance()->listInstalledHandles();

				if(is_array($extensions) && !empty($extensions)){
					foreach($extensions as $e){
						if(is_file(EXTENSIONS . "/{$e}/events/{$name}.php")) return EXTENSIONS . "/{$e}/events";
					}
				}
	    	}

		    return false;
	    }
		
		/*
		private function __processParameters(){
			
			if(isset($this->_env) && is_array($this->_env)){
				if(isset($this->eParamOVERRIDES) && is_array($this->eParamOVERRIDES) && !empty($this->eParamOVERRIDES)){
					foreach($this->eParamOVERRIDES as $field => $replacement){
						$replacement = $this->__processParametersInString(stripslashes($replacement), $this->_env);
						
						if($replacement === NULL){
							unset($this->eParamOVERRIDES[$field]);
							continue;
						}
						
						$this->eParamOVERRIDES[$field] = $replacement;
					}
				}
				
				if(isset($this->eParamDEFAULTS) && is_array($this->eParamDEFAULTS) && !empty($this->eParamDEFAULTS)){
					foreach($this->eParamDEFAULTS as $field => $replacement){
						$replacement = self::__processParametersInString(stripslashes($replacement), $this->_env);

						if($replacement === NULL){
							unset($this->eParamDEFAULTS[$key]);
							continue;
						}

						$this->eParamDEFAULTS[$field] = $replacement;
					}
				}
			}	
		}
		
		private static function __processParametersInString($value, array $env=NULL){

			if(preg_match_all('@{\$([^}]+)}@i', $value, $matches, PREG_SET_ORDER)){

				foreach($matches as $index => $match){
					list($pattern, $param) = $match;
					$replacement = self::__findParameterInEnv($param, $env);
					
					if($value == $pattern && $replacement === NULL) return NULL;
					
					$value = str_replace($pattern, $replacement, $value);
				}

			}

			return $value;
		}
		
		private static function __findParameterInEnv($needle, $env){

			if(isset($env['env']['url'][$needle])){
				return $env['env']['url'][$needle];
			}
			
			elseif(isset($env['param'][$needle])){
				return $env['param'][$needle];
			}

			return NULL;			
		}		
		*/
		
		## This function is required in order to edit it in the event editor page. 
		## Do not overload this function if you are creating a custom event. It is only
		## used by the event editor
		public function allowEditorToParse(){
			return false;
		}
		
		public function priority(){
			return self::PRIORITY_NORMAL;
		}

		abstract public function trigger();
	}
	
