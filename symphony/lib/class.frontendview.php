<?php

	require_once(LIB . '/class.frontend.php');
	require_once(LIB . '/class.event.php');

	Class FrontendView extends View {

		const ERROR_DOES_NOT_ACCEPT_PARAMETERS = 2;
		const ERROR_TOO_MANY_PARAMETERS = 3;

		const ERROR_MISSING_OR_INVALID_FIELDS = 4;
		const ERROR_FAILED_TO_WRITE = 5;

		private $_about;
		private $_path;
		private $_parent;
		private $_parameters;
		private $_template;
		private $_handle;
		private $_guid;

		public $location;

		public function __construct(){
			$this->_about = new StdClass;
			$this->_parameters = new StdClass;

			$this->_path = $this->_parent = $this->_template = $this->_handle = $this->_guid = NULL;
			$this->types = array();
			$this->location = VIEWS;
		}

		public function about(){
			return $this->_about;
		}

		public function parameters(){
			return $this->_parameters;
		}

		public function __isset($name){
			if(in_array($name, array('path', 'template', 'handle', 'guid'))){
				return isset($this->{"_{$name}"});
			}
			return isset($this->_about->$name);
		}

		public function __get($name){
			if(in_array($name, array('path', 'template', 'handle', 'guid'))){
				return $this->{"_{$name}"};
			}

			if (!isset($this->_about->$name)) {
				return false;
			}

			return $this->_about->$name;
		}

		public function __set($name, $value){
			if(in_array($name, array('path', 'template', 'handle', 'guid'))){
				$this->{"_{$name}"} = $value;
			}
			else $this->_about->$name = $value;
		}

		public function loadFromURL($path){
			$parts = preg_split('/\//', $path, -1, PREG_SPLIT_NO_EMPTY);
			$view = NULL;

			while(!empty($parts)){

				$p = array_shift($parts);

				if(!is_dir($this->location . $view . "/{$p}")){
					array_unshift($parts, $p);
					break;
				}

				$view = $view . "/{$p}";

			}

			return $this->loadFromPath($view, (!empty($parts) ? $parts : NULL));
		}

		public function loadFromPath($path, array $params=NULL){

			$view = new self;

			$view->path = trim($path, '\\/');

			preg_match('/\/?([^\\\\\/]+)$/', $path, $match); //Find the view handle
			$view->handle = $match[1];

			$pathname = sprintf('%s/%s/%s.config.xml', VIEWS, $view->path, $view->handle);

			if(!file_exists($pathname)){
				throw new ViewException(__('View, %s, could not be found.', array($pathname)), self::ERROR_VIEW_NOT_FOUND);
			}

			$doc = @simplexml_load_file($pathname);

			if(!($doc instanceof SimpleXMLElement)){
				throw new ViewException(__('Failed to load view configuration file: %s', array($pathname)), self::ERROR_FAILED_TO_LOAD);
			}

			foreach($doc as $name => $value){
				if(isset($value->item)){
					$stack = array();
					foreach($value->item as $item){
						array_push($stack, (string)$item);
					}
					$view->$name = $stack;
				}
				else $view->$name = (string)$value;
			}

			if(isset($doc->attributes()->guid)){
				$view->guid = (string)$doc->attributes()->guid;
			}
			else{
				$view->guid = uniqid();
			}

			if(!is_null($params)){

				if(!is_array($view->{'url-parameters'}) || count($view->{'url-parameters'}) <= 0){
					throw new ViewException(__('This view does not accept parameters.', array($pathname)), self::ERROR_DOES_NOT_ACCEPT_PARAMETERS);
				}

				if(count($params) > count($view->{'url-parameters'})){
					throw new ViewException(__('Too many parameters supplied.', array($pathname)), self::ERROR_TOO_MANY_PARAMETERS);
				}

				foreach($params as $index => $p){
					$view->setParameter($view->{'url-parameters'}[$index], $p);
				}
			}

			$template = sprintf('%s/%s/%s.xsl', VIEWS, $view->path, $view->handle);
			if(file_exists($template) && is_readable($template)){
				Frontend::instance()->template = file_get_contents($template);
			}

			return $view;
		}

		public function setParameter($name, $value){
			$this->_parameters->$name = $value;
		}

		public static function loadFromFieldsArray($fields){

			$view = new self;

			foreach($fields as $name => $value){
				$view->$name = $value;
			}

			return $view;
		}

		public static function findFromType($type){
			$views = array();
			foreach(new ViewIterator as $v){
				if(@in_array($type, $v->types)){
					$views[$v->guid] = $v;
				}
			}
			return $views;
		}

		public static function fetchUsedTypes(){
			$types = array();
			foreach(new ViewIterator as $v){
				$types = array_merge((array)$v->types, $types);
			}
			return General::array_remove_duplicates($types);
		}

		public function isChildOf(View $view){
			$current = $this->parent();

			while(!is_null($current)){
				if($current->guid == $view->guid) return true;
				$current = $current->parent();
			}

			return false;
		}

		public static function buildPageTitle(View $v){

			$title = $v->title;

			$current = $v->parent();

			while(!is_null($current)){
				$title = sprintf('%s: %s', $current->title, $title);
				$current = $current->parent();
			}

			return $title;
		}

		public static function countParents(View $v) {
			$current = $v->parent();
			$count = 0;

			while (!is_null($current)) {
				$current = $current->parent();
				$count++;
			}

			return $count;
		}

		public static function move(self $view, $dest){
			$bits = preg_split('~\/~', $dest, -1, PREG_SPLIT_NO_EMPTY);
			$handle = $bits[count($bits) - 1];

			// Config
			rename(
				sprintf('%s/%s/%s.config.xml', VIEWS, $view->path, $view->handle),
				sprintf('%s/%s/%s.config.xml', VIEWS, $view->path, $handle)
			);

			// Template
			rename(
				sprintf('%s/%s/%s.xsl', VIEWS, $view->path, $view->handle),
				sprintf('%s/%s/%s.xsl', VIEWS, $view->path, $handle)
			);

			// Folder
			rename(
				sprintf('%s/%s/', VIEWS, $view->path),
				sprintf('%s/%s/', VIEWS, implode('/', $bits))
			);

			$view->path = implode('/', $bits);
			$view->handle = $handle;
		}

		public static function save(self $view, MessageStack &$messages, $simulate=false){

			if(!isset($view->title) || strlen(trim($view->title)) == 0){
				$messages->append('title', __('Title is required.'));
			}

			$pathname = sprintf('%s/%s/%s.config.xml', VIEWS, $view->path, $view->handle);

			if(file_exists($pathname)){
				$existing = self::loadFromPath($view->path);
				if($existing->guid != $view->guid){
					$messages->append('handle', 'A view with that handle already exists.');
				}
				unset($existing);
			}

			if(isset($view->types) && is_array($view->types) && (bool)array_intersect($view->types, array('index', '404', '403'))){
				foreach($view->types as $t){
					switch($t){
						case 'index':
						case '404':
						case '403':
							$views = self::findFromType($t);
							if(isset($views[$view->guid])) unset($views[$view->guid]);

							if(!empty($views)){
								$messages->append('types', __('A view of type "%s" already exists.', array($t)));
								break 2;
							}
							break;
					}
				}
			}

			if(strlen(trim($view->template)) == 0){
				$messages->append('template', 'Template is required, and cannot be empty.');
			}
			elseif(!General::validateXML($view->template, $errors)) {

				$fragment = Administration::instance()->Page->createDocumentFragment();

				$fragment->appendChild(new DOMText(
					__('This document is not well formed. The following error was returned: ')
				));
				$fragment->appendChild(Administration::instance()->Page->createElement('code', $errors->current()->message));

				$messages->append('template', $fragment);

			}

			if($messages->length() > 0){
				throw new ViewException(__('View could not be saved. Validation failed.'), self::ERROR_MISSING_OR_INVALID_FIELDS);
			}

			if($simulate != true){
				if(!is_dir(dirname($pathname)) && !mkdir(dirname($pathname), intval(Symphony::Configuration()->core()->symphony->{'directory-write-mode'}, 8), true)){
					throw new ViewException(
						__('Could not create view directory. Please check permissions on <code>%s</code>.', $view->path),
						self::ERROR_FAILED_TO_WRITE
					);
				}

				// Save the config
				if(!General::writeFile($pathname, (string)$view,Symphony::Configuration()->core()->symphony->{'file-write-mode'})){
					throw new ViewException(
						__('View configuration XML could not be written to disk. Please check permissions on <code>%s</code>.', $view->path),
						self::ERROR_FAILED_TO_WRITE
					);
				}

				// Save the template file
				$result = General::writeFile(
					sprintf('%s/%s/%s.xsl', VIEWS, $view->path, $view->handle),
					$view->template,
					Symphony::Configuration()->core()->symphony->{'file-write-mode'}
				);

				if(!$result){
					throw new ViewException(
						__('Template could not be written to disk. Please check permissions on <code>%s</code>.', $view->path),
						self::ERROR_FAILED_TO_WRITE
					);
				}
			}

			return true;
		}

		public function __toString(){
			$doc = new DOMDocument('1.0', 'UTF-8');
			$doc->formatOutput = true;

			$root = $doc->createElement('view');
			$doc->appendChild($root);

			if(!isset($this->guid) || is_null($this->guid)){
				$this->guid = uniqid();
			}

			$root->setAttribute('guid', $this->guid);

			$root->appendChild($doc->createElement('title', General::sanitize($this->title)));
			$root->appendChild($doc->createElement('content-type', $this->{'content-type'}));

			if(is_array($this->{'url-parameters'}) && count($this->{'url-parameters'}) > 0){
				$url_parameters = $doc->createElement('url-parameters');
				foreach($this->{'url-parameters'} as $p){
					$url_parameters->appendChild($doc->createElement('item', General::sanitize($p)));
				}
				$root->appendChild($url_parameters);
			}

			if(is_array($this->events) && count($this->events) > 0){
				$events = $doc->createElement('events');
				foreach($this->events as $p){
					$events->appendChild($doc->createElement('item', General::sanitize($p)));
				}
				$root->appendChild($events);
			}

			if(is_array($this->{'data-sources'}) && count($this->{'data-sources'}) > 0){
				$data_sources = $doc->createElement('data-sources');
				foreach($this->{'data-sources'} as $p){
					$data_sources->appendChild($doc->createElement('item', General::sanitize($p)));
				}
				$root->appendChild($data_sources);
			}

			if(is_array($this->types) && count($this->types) > 0){
				$types = $doc->createElement('types');
				foreach($this->types as $t){
					$types->appendChild($doc->createElement('item', General::sanitize($t)));
				}
				$root->appendChild($types);
			}

			return $doc->saveXML();
		}

		public function parent(){
			if($this->_path == $this->handle) return NULL;
			elseif(!($this->_parent instanceof self)){
				$this->_parent = self::loadFromPath(preg_replace("~/{$this->handle}~", NULL, $this->_path));
			}
			return $this->_parent;
		}

		public function children(){
			return new ViewIterator($this->path, false);
		}

		public static function delete($path, $cascade=false){
			$view = self::loadFromPath($path);

			if($cascade == false){
				foreach($view->children() as $child){
					$bits = preg_split('~\/~', $child->path, -1, PREG_SPLIT_NO_EMPTY);
					unset($bits[count($bits) - 2]);
					View::move($child, trim(implode('/', $bits), '/'));
				}
			}

			General::rmdirr(VIEWS . '/' . trim($path, '/'));

		}

		private function __cbSortEventsByPriority($a, $b){
			if ($a->priority() == $b->priority()) {
		        return 0;
		    }
		    return(($a->priority() > $b->priority()) ? -1 : 1);
		}

		public function buildOutput(XMLDocument &$Document=NULL){

			if(is_null($Document)){
				$Document = new XMLDocument;
			}

			$root = $Document->documentElement;
			$datasources = $events = array();

			$events_wrapper = $Document->createElement('events');
			$root->appendChild($events_wrapper);

			if (is_array($this->about()->{'events'}) && !empty($this->about()->{'events'})) {
				$events = $this->about()->{'events'};
			}

			if (is_array($this->about()->{'data-sources'}) && !empty($this->about()->{'data-sources'})) {
				$datasources = $this->about()->{'data-sources'};
			}

			####
			# Delegate: FrontendEventsAppend
			# Description: Append additional Events.
			# Global: Yes
			Extension::notify(
				'FrontendEventsAppend', '/frontend/', array(
					'events'	=> &$events
				)
			);

			if (!empty($events)) {
				$postdata = General::getPostData();
				$events_ordered = array();

				foreach($events as $handle){
					$events_ordered[] = Event::loadFromHandle($handle);
				}

				uasort($events_ordered, array($this, '__cbSortEventsByPriority'));

				foreach($events_ordered as $e){
					if (!$e->canTrigger($postdata)) continue;

					$fragment = $e->trigger($ParameterOutput, $postdata);

					if($fragment instanceof DOMDocument && !is_null($fragment->documentElement)){
						$node = $Document->importNode($fragment->documentElement, true);
						$events_wrapper->appendChild($node);
					}
				}
			}

			####
			# Delegate: FrontendDataSourceAppend
			# Description: Append additional DataSources.
			# Global: Yes
			Extension::notify(
				'FrontendDataSourcesAppend', '/frontend/', array(
					'datasources'	=> &$datasources
				)
			);

			//	Find dependancies and order accordingly
			$datasource_pool = array();
			$dependency_list = array();
			$datasources_ordered = array();
			$all_dependencies = array();

			foreach($datasources as $handle){
				$datasource_pool[$handle] = Datasource::loadFromHandle($handle);
				$dependency_list[$handle] = $datasource_pool[$handle]->parameters()->dependencies;
			}

			$datasources_ordered = General::dependenciesSort($dependency_list);

			$data = $Document->createElement('data');

			if (!empty($datasources_ordered)) {
				foreach($datasources_ordered as $handle){
					$ds = $datasource_pool[$handle];

					try {
						$fragment = $ds->render($ParameterOutput);
					}

					catch (FrontendPageNotFoundException $e) {
						FrontendPageNotFoundExceptionHandler::render($e);
					}

					if($fragment instanceof DOMDocument && !is_null($fragment->documentElement)){
						$node = $Document->importNode($fragment->documentElement, true);
						$data->appendChild($node);
					}

				}
			}

			$root->appendChild($data);

			if($ParameterOutput->length() > 0){
				foreach($ParameterOutput as $p){
					$Parameters->{$p->key} = $p->value;
				}
			}

			####
			# Delegate: FrontendParamsPostResolve
			# Description: Access to the resolved param pool, including additional parameters provided by Data Source outputs
			# Global: Yes
			Extension::notify('FrontendParamsPostResolve', '/frontend/', array('params' => $Parameters));

			$template = $this->template;

			####
			# Delegate: FrontendTemplatePreRender
			# Description: Access to the template source, before it is rendered.
			# Global: Yes
			Extension::notify(
				'FrontendTemplatePreRender', '/frontend/', array(
					'document'	=> $Document,
					'template'	=> &$template
				)
			);

			return $output;
		}

	}