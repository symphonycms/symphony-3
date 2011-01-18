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
		* Build the navigation XML and append it to the root element
		* Copied method -- TODO cleanup
		*/
		public function buildNavigationXML($root) {
			$xpath = new DOMXPath($this->document);
			$template = new XMLDocument();
			$template->preserveWhiteSpace = false;
			$template->load(TEMPLATES . '/navigation.xml');
			$navigation = $this->document->importNode(
				$template->documentElement, true
			);
			$first_child = $navigation->firstChild;
			
			// Force visible to be set:
			foreach ($xpath->query('group//item[not(@visible)]', $navigation) as $item) {
				$item->setAttribute('visible', 'yes');
			}
			
			// Set all items as inactive:
			foreach ($xpath->query('group//item', $navigation) as $item) {
				$item->setAttribute('active', 'no');
			}
			
			// Add section navigation:
			foreach (new SectionIterator as $section) {
				// Find the navigation group:
				$group = $xpath->query(
					sprintf(
						'group[@name = "%s"]',
						htmlentities($section->{'navigation-group'})
					),
					$navigation
				);
				$group = $group->item(0);
				
				if (is_null($group)) {
					$group = $this->document->createElement('group');
					$group->setAttribute('name', $section->{'navigation-group'});
					$group->setAttribute('type', 'sections');
					$first_child->parentNode->insertBefore($group, $first_child);
				}
				
				$item = $this->document->createElement('item');
				$item->setAttribute('link', 'publish/' . $section->handle);
				$item->setAttribute('name', $section->name);
				$item->setAttribute('type', 'section');
				$item->setAttribute('visible', (
					$section->{'hidden-from-publish-menu'} == 'no'
					? 'yes' : 'no'
				));
				$item->setAttribute('active', 'no');
				$group->appendChild($item);
				
				// New link:
				$sub_item = clone $item;
				$sub_item->setAttribute('link', 'publish/' . $section->handle . '/new');
				$sub_item->setAttribute('name', __('New'));
				$item->appendChild($sub_item);
				
				// Edit link:
				$sub_item = clone $sub_item;
				$sub_item->setAttribute('link', 'publish/' . $section->handle . '/edit');
				$sub_item->setAttribute('name', __('Edit'));
				$sub_item->setAttribute('visible', 'no');
				$item->appendChild($sub_item);
			}
			
			// Remove empty groups:
			foreach ($xpath->query('group[not(item)]', $navigation) as $group) {
				$group->parentNode->removeChild($group);
			}
			
			// Assign handles to all groups:
			foreach ($xpath->query('group[not(@handle)]', $navigation) as $group) {
				$group->setAttribute('handle', Lang::createHandle(
					$group->getAttribute('name')
				));
			}
			
			// Find active page:
			$active = $xpath->query(
				sprintf(
					'group//item[@link = "%s"]',
					htmlentities($this->path)
				),
				$navigation
			);
			$active = $active->item(0);
			
			if ($active instanceof DOMElement) {
				$active->setAttribute('active', 'yes');
			}
			
			//$this->document->formatOutput = true;
			//echo '<pre>', htmlentities($this->document->saveXML($navigation)); exit;
			
			$root->appendChild($navigation);
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