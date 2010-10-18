<?php

	require_once(LIB . '/class.symphony.php');
	require_once(LIB . '/class.xmldocument.php');
	require_once(LIB . '/class.lang.php');
	require_once(LIB . '/class.register.php');
	
	Class FrontendPageNotFoundException extends SymphonyErrorPage{
		public function __construct(View $page=NULL){
			
			if(is_null($page)){
				$views = View::findFromType('404');
				$page = array_shift($views);
			}
			
			parent::__construct(
				__('The page you requested does not exist.'),
				__('Page Not Found'),
				$page,
				array('header' => 'HTTP/1.0 404 Not Found')
			);
		}
	}

	Class FrontendPageNotFoundExceptionHandler extends SymphonyErrorPageHandler{
		/*
		public static function render($e){
			// TODO: Fix me to use Views

			$view = View::loadFromURL($_SERVER['PHP_SELF']);
			$page_id = Symphony::Database()->fetchVar('page_id', 0, "SELECT `page_id` FROM `tbl_pages_types` WHERE `type` = '404' LIMIT 1");

			if(is_null($page_id)){
				parent::render(new SymphonyErrorPage(
					__('The page you requested does not exist.'),
					__('Page Not Found'),
					'error',
					array('header' => 'HTTP/1.0 404 Not Found')
				));
			}
			else{
				$url = '/' . Frontend::instance()->resolvePagePath($page_id) . '/';

				$output = Frontend::instance()->display($url);
				header(sprintf('Content-Length: %d', strlen($output)));
				echo $output;
				exit;
			}
		}*/
		public static function render($e){
			parent::render($e);
		}
	}

	Class Frontend extends Symphony {
		protected static $view;
		protected static $Document;
		protected static $Parameters;
		protected static $Headers;
		
		public static function instance() {
			if (!(self::$_instance instanceof Frontend)) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		public static function loadedView(){
			return self::$view;
		}

		public static function Headers() {
			return self::$Headers;
		}
		
		public static function Document() {
			return self::$Document;
		}
		
		public static function Parameters() {
			return self::$Parameters;
		}
		
		public function __construct() {
			parent::__construct();
			
			self::$Headers = new DocumentHeaders;
			
			self::$Document = new XMLDocument;
			self::$Document->appendChild(
				self::$Document->createElement('data')
			);
			
			Widget::init(self::$Document);
		}
		
		public function resolve($url=NULL){
			try{
				if(is_null($url)){
					$views = View::findFromType('index');
					self::$view = array_shift($views);
				}
				else{
					self::$view = View::loadFromURL($url);
				}

				if(!(self::$view instanceof View)) throw new Exception('Page not found');

				if(!Frontend::instance()->isLoggedIn() && in_array('admin', self::$view->types)){

					$views = View::findFromType('403');
					self::$view = array_shift($views);

					if(!(self::$view instanceof View)){
						throw new SymphonyErrorPage(
							__('Please <a href="%s">login</a> to view this page.', array(ADMIN_URL . '/login/')),
							__('Forbidden'), NULL,
							array('HTTP/1.0 403 Forbidden')
						);
					}
				}
			}

			catch(Exception $e){
				$views = View::findFromType('404');
				self::$view = array_shift($views);

				if(!(self::$view instanceof View)){
					throw new FrontendPageNotFoundException($url);
				}
			}
		}
		
		public function display($url=NULL){
			self::$Parameters = new Register;
			
			// Default headers. Can be overwritten later
			//self::$Headers->append('HTTP/1.0 200 OK');
			self::$Headers->append('Content-Type', 'text/html;charset=utf-8');
			self::$Headers->append('Expires', 'Mon, 12 Dec 1982 06:14:00 GMT');
			self::$Headers->append('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
			self::$Headers->append('Cache-Control', 'no-cache, must-revalidate, max-age=0');
			self::$Headers->append('Pragma', 'no-cache');
			
			####
			# Delegate: FrontendPreInitialise
			# Description: TODO
			# Global: Yes
			Extension::notify(
				'FrontendPreInitialise',
				'/frontend/',
				array(
					'view' => &self::$view,
					'url' => &$url
				)
			);

			if(!(self::$view instanceof View)){
				$this->resolve($url);
			}

			####
			# Delegate: FrontendPostInitialise
			Extension::notify(
				'FrontendPostInitialise',
				'/frontend/',
				array(
					'view' => &self::$view
				)
			);

			// SETTING UP PARAMETERS --------------------------

			$root_page = array_shift(explode('/', self::$view->parent()->path));
			$current_path = explode(dirname($_SERVER['SCRIPT_NAME']), $_SERVER['REQUEST_URI'], 2);
			$current_path = '/' . ltrim(end($current_path), '/');

			self::$Parameters->register(array(
				'today' => DateTimeObj::get('Y-m-d'),
				'current-time' => DateTimeObj::get('H:i'),
				'this-year' => DateTimeObj::get('Y'),
				'this-month' => DateTimeObj::get('m'),
				'this-day' => DateTimeObj::get('d'),
				'timezone' => date_default_timezone_get(),
				'website-name' =>Symphony::Configuration()->core()->symphony->sitename,
				'symphony-version' =>Symphony::Configuration()->core()->symphony->version,
				'upload-limit' => min(
					ini_size_to_bytes(ini_get('upload_max_filesize')),
					Symphony::Configuration()->core()->symphony->{'maximum-upload-size'}
				),
				'root' => URL,
				'workspace' => URL . '/workspace',
				'page-title' => self::$view->title,
				'root-page' => (!is_null($root_page) ? $root_page : self::$view->handle),
				'current-page' => self::$view->handle,
				'current-path' => $current_path,
				'parent-path' => '/' . self::$view->path,
				'current-url' => URL . $current_path,
			));

			if(isset(self::$view->{'url-parameters'}) && is_array(self::$view->{'url-parameters'})){
				foreach(self::$view->{'url-parameters'} as $p){
					self::$Parameters->$p = NULL;
				}

				foreach(self::$view->parameters() as $p => $v){
					self::$Parameters->$p = str_replace(' ', '+', $v);
				}

			}

			if(is_array($_GET) && !empty($_GET)){
				foreach($_GET as $key => $val){
					if(in_array($key, array('symphony-page', 'debug', 'profile'))) continue;
					// self::$Parameters->{"url-{$key}"} = $val; "url" is not prepended by $_GET params
					self::$Parameters->{$key} = $val;
				}
			}

			if(is_array($_COOKIE[__SYM_COOKIE_PREFIX__]) && !empty($_COOKIE[__SYM_COOKIE_PREFIX__])){
				foreach($_COOKIE[__SYM_COOKIE_PREFIX__] as $key => $val){
					self::$Parameters->{"cookie-{$key}"} = $val;
				}
			}

			// RENDER THE VIEW --------------------------

			// Can ask the view to operate on an existing
			// Document. Useful if we pass it around beyond
			// the scope of View::render()

			####
			# Delegate: FrontendPreRender
			# Description: TODO
			# Global: Yes
			Extension::notify(
				'FrontendPreRender',
				'/frontend/',
				array(
					'view' => &self::$view,
					'parameters' => &self::$Parameters,
					'document' => &self::$Document,
					'headers' => &self::$Headers
				)
			);

			$output = self::$view->render(self::$Parameters, self::$Document, self::$Headers);

			####
			# Delegate: FrontendPostRender
			# Description: TODO
			# Global: Yes
			Extension::notify(
				'FrontendPostRender',
				'/frontend/',
				array(
					'output' => &$output,
					'headers' => &self::$Headers
				)
			);
			
			self::Headers()->render();
			
			return $output;
			
		}
	}

	return 'Frontend';