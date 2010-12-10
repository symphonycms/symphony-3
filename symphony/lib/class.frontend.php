<?php

	require_once(LIB . '/class.renderer.php');
	require_once(LIB . '/class.frontendview.php');

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

	Class Frontend extends Renderer {
		protected static $view;
		protected static $Parameters;

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
		}

		public function resolve($url=NULL){
			try{
				if(is_null($url)){
					$views = FrontendView::findFromType('index');
					self::$view = array_shift($views);
				}
				else{
					self::$view = FrontendView::loadFromURL($url);
				}

				if(!(self::$view instanceof FrontendView)) throw new Exception('Page not found');

				if(!Frontend::instance()->isLoggedIn() && in_array('admin', self::$view->types)){

					$views = FrontendView::findFromType('403');
					self::$view = array_shift($views);

					if(!(self::$view instanceof FrontendView)){
						throw new SymphonyErrorPage(
							__('Please <a href="%s">login</a> to view this page.', array(ADMIN_URL . '/login/')),
							__('Forbidden'), NULL,
							array('HTTP/1.0 403 Forbidden')
						);
					}
				}
				$this->docroot = VIEWS . '/' . self::$view->path;
			}

			catch(Exception $e){
				$views = FrontendView::findFromType('404');
				self::$view = array_shift($views);

				if(!(self::$view instanceof FrontendView)){
					throw new FrontendPageNotFoundException($url);
				}
			}
		}

		public function display($url=NULL){

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

			self::$Context->register(array(
				'view'		=> array(
					'title' => self::$view->title,
					'handle' => self::$view->handle,
					'path' => $current_path,
					'current-url' => URL . $current_path,
					'root-view' => (!is_null($root_page) ? $root_page : self::$view->handle),
					'parent-path' => '/' . self::$view->path,
					'upload-limit' => min(
						ini_size_to_bytes(ini_get('upload_max_filesize')),
						Symphony::Configuration()->core()->symphony->{'maximum-upload-size'}
					)
				)
			));

			if(isset(self::$view->{'url-parameters'}) && is_array(self::$view->{'url-parameters'})){
				foreach(self::$view->{'url-parameters'} as $p){
					self::$Context->$p = NULL;
				}

				foreach(self::$view->parameters() as $p => $v){
					self::$Context->$p = str_replace(' ', '+', $v);
				}

			}

			if(is_array($_GET) && !empty($_GET)){
				foreach($_GET as $key => $val){
					if(in_array($key, array('symphony-page', 'debug', 'profile'))) continue;
					// self::$Parameters->{"url-{$key}"} = $val; "url" is not prepended by $_GET params
					self::$Context->{$key} = $val;
				}
			}

			if(is_array($_COOKIE[__SYM_COOKIE_PREFIX__]) && !empty($_COOKIE[__SYM_COOKIE_PREFIX__])){
				foreach($_COOKIE[__SYM_COOKIE_PREFIX__] as $key => $val){
					self::$Context->{"cookie-{$key}"} = $val;
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
					'parameters' => &self::$Context,
					'document' => &self::$Document,
					'headers' => &self::$Headers
				)
			);

			self::$view->buildOutput(self::$Document);

			//$output = self::$view->render(self::$Context, self::$Document, self::$Headers);
			$output = self::render();

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

			//self::Headers()->render();

			return $output;

		}
	}

	return 'Frontend';