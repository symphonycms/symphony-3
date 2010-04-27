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

	Class Event{

		const PRIORITY_HIGH = 3;
		const PRIORITY_NORMAL = 2;
		const PRIORITY_LOW = 1;

		const ERROR_MISSING_OR_INVALID_FIELDS = 4;
		const ERROR_FAILED_TO_WRITE = 5;

		protected static $_loaded;

		protected $_about;
		protected $_parameters;

		public function __construct(){
			$this->_about = (object)array(
				'name'			=> NULL,
				'author'		=> (object)array(
					'name'			=> NULL,
					'website'		=> URL,
					'email'			=> NULL
				),
				'version'		=> '1.0',
				'release-date'	=> DateTimeObj::get('Y-m-d')
			);

			$this->_parameters = (object)array(
				'root-element' => NULL,
				'source' => NULL,
				'filters' => array(),
				'overrides' => array(),
				'defaults' => array(),
				'output-id-on-save' => false
			);
		}

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

		public static function loadFromHandle($name){
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

		public function __get($name){
			if($name == 'handle'){
				return Lang::createFilename($this->about()->name);
			}
		}

		public static function save(Event $event, MessageStack &$errors){
			$editing = (isset($event->parameters()->{'root-element'}))
						? $event->parameters()->{'root-element'}
						: false;

			if (!isset($event->about()->name) || empty($event->about()->name)) {
				$errors->append('about::name', __('This is a required field'));
			}

			try {
				$existing = self::loadFromHandle($event->handle);
			}
			catch(EventException $e) {
				//	Event not found, continue
			}

			if($existing instanceof Event && $editing != $event->handle) {
				throw new EventException(__('An Event with the name <code>%s</code> already exists.', array($event->about()->name)));
			}

			$event->parameters()->{'root-element'} = $event->handle;
			$classname = Lang::createHandle(ucwords($event->about()->name), '_', false, true, array('/[^a-zA-Z0-9_\x7f-\xff]/' => NULL), true);
			$pathname = EVENTS . "/" . $event->handle . ".php";

			if($errors->length() <= 0){
				$data = array(
					$classname,
					// About info:
					var_export($event->about()->name, true),
					var_export($event->about()->author->name, true),
					var_export($event->about()->author->website, true),
					var_export($event->about()->author->email, true),
					var_export($event->about()->version, true),
					var_export($event->about()->{'release-date'}, true),
					var_export($event->parameters()->{'root-element'}, true),
					var_export($event->parameters()->source, true),
					trim(General::var_export($event->parameters()->filters, true, 4)),
					trim(General::var_export($event->parameters()->overrides, true, 4)),
					trim(General::var_export($event->parameters()->defaults, true, 4)),
					$event->parameters()->{'output-id-on-save'} == true ? 'true' : 'false'
				);

				if(General::writeFile(
					$pathname,
					vsprintf(file_get_contents(TEMPLATES . '/template.event.php'), $data),
					Symphony::Configuration()->core()->symphony->{'file-write-mode'}
				)){
					if($editing != $event->handle) General::deleteFile(EVENTS . '/' . $editing . '.php');

					return $pathname;
				}
				$errors->append('write', __('Failed to write event "%s" to disk.', array($filename)));
			}

			throw new EventException(__('Event could not be saved. Validation failed.'), self::ERROR_MISSING_OR_INVALID_FIELDS);
		}

		public function delete($handle){
			/*
				TODO:
				Upon deletion of the event, views need to be updated to remove
				it's associated with the event
			*/
			$event = Event::loadFromHandle($handle);

			if(!$event->allowEditorToParse()) {
				throw new EventException(__('Event cannot be deleted, the Editor does not have permission.'));
			}

			return General::deleteFile(EVENTS . "/{$handle}.php");
		}

		/*
		private function __processParameters(){

			if(isset($this->_env) && is_array($this->_env)){
				if(isset($this->eParamOVERRIDES) && is_array($this->eParamOVERRIDES) && !empty($this->eParamOVERRIDES)){
					foreach($this->eParamOVERRIDES as $field => $replacement){
						$replacement = $this->replaceParametersInString(stripslashes($replacement), $this->_env);

						if($replacement === NULL){
							unset($this->eParamOVERRIDES[$field]);
							continue;
						}

						$this->eParamOVERRIDES[$field] = $replacement;
					}
				}

				if(isset($this->eParamDEFAULTS) && is_array($this->eParamDEFAULTS) && !empty($this->eParamDEFAULTS)){
					foreach($this->eParamDEFAULTS as $field => $replacement){
						$replacement = self::replaceParametersInString(stripslashes($replacement), $this->_env);

						if($replacement === NULL){
							unset($this->eParamDEFAULTS[$key]);
							continue;
						}

						$this->eParamDEFAULTS[$field] = $replacement;
					}
				}
			}
		}

		private static function replaceParametersInString($value, array $env=NULL){

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

		public function trigger(){
			return NULL;
		}

		public static function getHandleFromFilename($filename){
			return preg_replace('/(.php$|\/.*\/)/i', NULL, $filename);
		}
	}

