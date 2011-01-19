<?php

	/**
	* The Controller class...
	*/

	require_once(LIB . '/class.symphony.php');
	require_once(LIB . '/class.lang.php');
	require_once(LIB . '/class.view.php');
	require_once(LIB . '/class.context.php');

	class Controller extends Symphony {
		/**
		* A view object
		*/
		public $View;

		/**
		* URL
		*/
		public $url;

		/**
		* Checks if an instance already exists, etc.
		*/
		public static function instance() {
			if (!(self::$_instance instanceof Controller)) {
				self::$_instance = new self;
			}
			
			return self::$_instance;
		}

		/**
		* Returns the loaded view. Needed it for something... TODO find out what
		*/
		public function loadedView() {
			return $this->View;
		}

		/**
		* Parses URL and resolves
		*/
		public function createView() {
			// Set Symphony renderer based on URL
			$renderer = (isset($_GET['symphony-renderer'])
				? $_GET['symphony-renderer']
				: 'frontend');

			$path = null;

			// Is this for access to real non-Symphony files and dirs?
			if (file_exists(realpath($renderer))) {
				$path = realpath($renderer);

				// Ensure the renderer is child of DOCROOT. Closes potential
				// security hole
				if (substr($path, 0, strlen(DOCROOT)) != DOCROOT) {
					$path = NULL;
				}
			}

			// Set path to renderer
			else if (file_exists(LIB . "/class.{$renderer}view.php")) {
				$path = LIB . "/class.{$renderer}view.php";
			}

			if (is_null($path)) {
				throw new Exception('Invalid Symphony renderer specified.');
			}

			// Include renderer view class
			require_once($path);

			// Instantiate the view object
			$classname = ucfirst($renderer) . 'View';
			
			return new $classname();
		}

		/**
		 * Initialize contextual XML (formerly params)
		 */
		public function initializeContext() {
			$this->View->context->register(array(
				'system'	=> array(
					'site-name'			=> Symphony::Configuration()->core()->symphony->sitename,
					'site-url'			=> URL,
					'admin-url'			=> URL . '/symphony',
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

			if ($this->User) {
				$this->View->context->register(array(
					'session'	=> self::instance()->User->fields()
				));
			}
		}

		/**
		* Fields requests, routes them to appropriate View, returns output
		*/
		public function renderView() {
			// Set URL
			$this->url = getCurrentPage();

			// Create view
			$this->View = $this->createView();

			// Initialize View
			$this->View->load($this->url);

			// Initialize context
			$this->initializeContext();

			// Tell the view to build its output
			$output = $this->View->buildOutput();

			return $output;
		}
	}