<?php

	/**
	 * The AdministrationView class
	 */

	require_once(LIB . '/class.xmldocument.php');

	Class AdministrationView extends SymphonyView {

		/**
		* View's Driver object
		*/
		public $driver;

		/**
		* Array containing the actions stack
		*/
		public $actions = array();

		/**
		* Array containing the drawer contents
		*/
		public $drawer = array();

		/**
		* Array containing the admin nav
		*/
		public $navigation = array();

		/**
		* String containing the view's title
		*/
		public $title;

		/**
		* When instantiating an AdministrationView object, set the root location
		* for views (/symphony/content/), and initilize the View (which sets up
		* the headers, context object, and document.
		*/
		public function __construct() {
			// Set root location
			$this->location = CONTENT;

			// Initialize View (in class.view.php)
			$this->initialize();
		}

		/**
		* Perform initial checks and load requested view
		*
		* @param string $url
		*  URL path of requested view
		*/
		public function load($url = null) {
			// Check for login
			Controller::instance()->isLoggedIn();

			if (!Controller::instance()->isLoggedIn()) {
				$this->parseURL('/login');
			}

			else {
				try {
					if (is_null($url)) {
						// Get user's default section
						$section_handle = Controller::instance()->User->default_section;

						// If the section exists, load publish view
						try {
							$section = Section::loadFromHandle($section_handle);
							$this->parseURL('/publish/' . $section_handle);
						}
						
						catch (Exception $e) {
							$this->parseURL('/blueprints/sections/');
						}
					}
					
					else {
						$this->parseURL($url);
					}

					// TODO: Fix this
					if (!($this instanceof AdministrationView)) {
						throw new Exception('View not found');
					}
				}

				catch (Exception $e) {
					throw $e;
					//catch the exception
					print('Sorry could not load ' . $url);
				}
			}
		}
		
		/**
		* Parses the URL to figure out what View to load
		* 
		* @param	$path		string	View path including URL parameters to attempt to find
		* @param	$expression string	Expression used to match the view driver/conf file. Use printf syntax.
		*/
		public function parseURL($path, $expression = '%s.driver.php') {
			return parent::parseURL($path, $expression);
		}
		
		/**
		* Use data passed from parseURL() to locate the view driver and
		* template.
		*
		* @param string $path
		*  View path
		*
		* @param array $params
		*  URL parameters to be passed to View
		*/
		public function loadFromPath($path, array $params = null) {
			// Setup basic view info:
			$this->path = trim($path, '\\/');
			$this->params = $params;
			$this->handle = preg_replace('~^.*/~', null, $this->path);
			
			// Determine path to driver file
			$driver_file = sprintf(
				'%s/%s/%s.driver.php',
				$this->location,
				$this->path,
				$this->handle
			);
			
			// Make sure the driver file exists
			if (!file_exists($driver_file)) {
				throw new ViewException(__('View, %s, could not be found.', array($this->path)), self::ERROR_VIEW_NOT_FOUND);
			}

			// Determine the driver's Classname
			$driver_class = ucfirst($this->handle) . 'Driver';

			// Require the driver
			require_once($driver_file);

			// Set the view's driver object
			// TODO These drivers are kind of repetetive at the moment. There's probably
			// a much smarter way to do this
			$this->driver = new $driver_class();

			// Determine path to the template file
			$template_file = sprintf('%s/%s/%s.xsl', $this->location, $this->path, $this->handle);

			// Set the view's template if it exists
			// TODO this probably needs to be rethought
			if (file_exists($template_file) && is_readable($template_file)) {
				$this->stylesheet = file_get_contents($template_file);
			}

			// The Controller is responsible for initilizing the system context info
			// (website name, user info, etc). Here we have the view register its
			// own context info (handle, params, path, and so on)
			$this->getViewContext();

			// Initialize the navigation
			$this->initializeNavigation();
		}

		/**
		* Register the view's context
		*/
		public function getViewContext() {

			// This needs to get fixed up
			// Path stuff needs to get parsed better so we can build breadcrumbs, etc.
			// Params should be split up
			$this->context->register(array(
				'view'	=> array(
					'title'			=> $this->title,
					'handle'		=> $this->handle,
					'params'		=> (!is_null($this->params) ? implode($this->params, '/') : ''),
					'path'			=> $this->path,
					'root'			=> $this->location
				)
			));
		}

		/**
		* Method for registering actions with the page
		*/
		public function registerAction(array $action) {
			if (is_array($action) && !empty($action)) {
				array_push($this->actions, $action);
			}
		}

		/**
		* Build XML for actions and append it to the root element
		*
		* @param SymphonyDOMElement $root
		*/
		public function buildActionsXML($root) {

			/*  SAMPLE $actions array:

				array(
					array(
						'name'	=> 'Create New',
						'type'	=> 'new',
						'callback'	=> $this->path . '/new'
					),
					array(
						'name'	=> 'Etc'
					),
				);

				Need to think this through a little better (ability to
				set classes, IDs, and other kinds of stuff)
			*/
			$actions = $this->document->createElement('actions');

			foreach($this->actions as $node) {

				$action = $this->document->createElement('action');

				foreach($node as $name => $text) {
					$action->appendChild($this->document->createElement($name, $text));
				}

				$actions->appendChild($action);
			}

			$root->appendChild($actions);

		}

		/**
		* Build XML for drawer and append it to the root element
		*/
		public function buildDrawerXML($root) {

		}

		/**
		* Copied method
		*/
		private static function __navigationFindGroupIndex($nav, $name){
			foreach($nav as $index => $item){
				if($item['name'] == $name) return $index;
			}
			return false;
		}

		/**
		* Copied method... TODO cleanup
		*/
		protected function initializeNavigation(){

			$this->navigation = array();

			$xml = simplexml_load_file(ASSETS . '/navigation.xml');

			foreach($xml->xpath('/navigation/group') as $n){

				$index = (string)$n->attributes()->index;
				$children = $n->xpath('children/item');
				$content = $n->attributes();

				if(isset($this->navigation[$index])){
					do{
						$index++;
					}while(isset($nav[$index]));
				}

				$this->navigation[$index] = array(
					'name' => __(strval($content->name)),
					'index' => $index,
					'children' => array()
				);

				if(strlen(trim((string)$content->limit)) > 0){
					$this->navigation[$index]['limit'] = (string)$content->limit;
				}

				if(count($children) > 0){
					foreach($children as $child){
						$limit = (string)$child->attributes()->limit;

						$item = array(
							'link' => (string)$child->attributes()->link,
							'name' => __(strval($child->attributes()->name)),
							'visible' => ((string)$child->attributes()->visible == 'no' ? 'no' : 'yes'),
						);

						if(strlen(trim($limit)) > 0) $item['limit'] = $limit;

						$this->navigation[$index]['children'][] = $item;
					}
				}
			}

			foreach(new SectionIterator as $s){
				$group_index = self::__navigationFindGroupIndex($this->navigation, $s->{'navigation-group'});

				if($group_index === false){
					$group_index = General::array_find_available_index($this->navigation, 0);

					$this->navigation[$group_index] = array(
						'name' => $s->{'navigation-group'},
						'index' => $group_index,
						'children' => array(),
						'limit' => NULL
					);
				}

				$this->navigation[$group_index]['children'][] = array(
					'link' => 'publish/' . $s->handle,
					'name' => $s->name,
					'type' => 'section',
					'section' => array('id' => $s->guid, 'handle' => $s->handle),
					'visible' => ($s->{'hidden-from-publish-menu'} == 'no' ? 'yes' : 'no')
				);
			}
			
			$found = $this->__findActiveNavigationGroup($this->path);
			
			## Normal searches failed. Use a regular expression using the page root. This is less
			## efficent and should never really get invoked unless something weird is going on
			if(!$found) $this->__findActiveNavigationGroup('/^' . str_replace('/', '\/', $this->path) . '/i', true);
			
			ksort($this->navigation);
		}

		/**
		* Copied method...
		*/
		protected function __findLocationIndexFromName($name){
			foreach($this->navigation as $index => $group){
				if($group['name'] == $name){
					return $index;
				}
			}

			return false;
		}

		/**
		* Copied method... TODO cleanup
		*/
		protected function __findActiveNavigationGroup($pageroot, $pattern=false){

			foreach($this->navigation as $index => $contents){
				if(is_array($contents['children']) && !empty($contents['children'])){
					foreach($contents['children'] as $item){

						if($pattern && preg_match($pageroot, $item['link'])){
							$this->navigation[$index]['class'] = 'active';
							return true;
						}

						elseif($item['link'] == $pageroot){
							$this->navigation[$index]['class'] = 'active';
							return true;
						}

					}
				}
			}

			return false;

		}

		/**
		* Build the navigation XML and append it to the root element
		* Copied method -- TODO cleanup
		*/
		public function buildNavigationXML($root){

			$nav_xml = $this->document->createElement('navigation');

			foreach($this->navigation as $n){
				$can_access = true;

				if(!isset($n['visible']) or $n['visible'] != 'no'){

					if($can_access == true) {

						$group_xml = $this->document->createElement('group');
						$name = $this->document->createElement('name',$n['name']);
						$name->setAttribute('handle',Lang::createHandle($n['name']));
						$group_xml->appendChild($name);
						$items_xml = $this->document->createElement('items');

						$hasChildren = false;

						if(is_array($n['children']) && !empty($n['children'])){
							foreach($n['children'] as $c){

								$can_access_child = true;

								if($c['visible'] != 'no'){

									if($can_access_child == true) {

										$item = $this->document->createElement('item');
										$item_name = $this->document->createElement('name', $c['name']);
										$item_name->setAttribute('handle',Lang::createHandle($c['name']));
										$item->appendChild(
											$item_name
										);
										$item->appendChild(
											$this->document->createElement('link', $c['link'])
										);
										$items_xml->appendChild($item);
										$hasChildren = true;

									}
								}

							}

							if($hasChildren){
								$group_xml->appendChild($items_xml);
								$nav_xml->appendChild($group_xml);
							}
						}
					}
				}
			}

			$root->appendChild($nav_xml);
		}

		/**
		* Build the View's output
		*
		* @return string Result of transformation
		*/
		public function buildOutput() {

			// Cover for an oversight. Rethink this.
			if($_SERVER['REQUEST_METHOD'] == 'POST') {
				$this->driver->processRequests();
			}

			// Set up the root XML element
			$root = $this->document->documentElement;

			// Build the view's context XML and append it
			$this->buildContextXML($root);

			// Build and append the navigation XML
			$this->buildNavigationXML($root);

			// Have the driver register its actions
			$this->driver->registerActions();

			// Build and append the actions XML
			$this->buildActionsXML($root);

			// Have the driver register its drawer contents
			$this->driver->registerDrawer();

			// Build and append the drawer XML
			$this->buildDrawerXML($root);

			// Setup Data XML
			$data = $this->document->createElement('data');

			// Build Data XML
			$this->driver->buildDataXML($data);

			// TODO: DELEGATE for allowing extensions to append XML?
			$root->appendChild($data);

			// This is in class.view.php
			return $this->transform(TEMPLATES . '/interface');

		}

	}