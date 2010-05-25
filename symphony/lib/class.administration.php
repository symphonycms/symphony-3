<?php

	require_once(LIB . '/class.symphony.php');
	require_once(LIB . '/class.lang.php');
	require_once(LIB . '/class.ajaxpage.php');

	Class AdministrationPageNotFoundException extends SymphonyErrorPage{
		public function __construct($page){
			parent::__construct(
				__('The page you requested does not exist.'),
				__('Page Not Found'),
				$page,
				array('header' => 'HTTP/1.0 404 Not Found')
			);
		}
	}
	
	Class AdministrationPageNotFoundExceptionHandler extends SymphonyErrorPageHandler{
		public static function render($e){
			parent::render($e);
		}
	}
		
	Class Administration extends Symphony{
		
		private $_currentPage;
		private $_callback;
		
		protected static $Headers;

		public $Page;
		
		public static function Headers() {
			return self::$Headers;
		}
		
		public static function instance(){
			if(!(self::$_instance instanceof Administration)) 
				self::$_instance = new self;
				
			return self::$_instance;
		}
		
		public function __construct(){
			parent::__construct();
			self::$Headers = new DocumentHeaders;
		}
		
		private function __buildPage($page){
	
			$this->isLoggedIn();
			
			if(empty($page)){
				
				if (!$this->isLoggedIn()) {
					$page = '/login';
				}
				
				else {
					$section_handle = $this->User->default_section;
					
					// Make sure section exists:
					try {
						$section = Section::loadFromHandle($section_handle);
						
						redirect(ADMIN_URL . "/publish/{$section_handle}/");
					}
					
					catch (Exception $e) {
						redirect(ADMIN_URL . '/blueprints/sections/');
					}
				}
			}
			
			if(!$this->_callback = $this->getPageCallback($page)){
				throw new AdministrationPageNotFoundException($page);
			}
				
			include_once((isset($this->_callback['driverlocation']) ? $this->_callback['driverlocation'] : CONTENT) . '/content.' . $this->_callback['driver'] . '.php'); 			
			$this->Page = new $this->_callback['classname'];
			
			Widget::init($this->Page);

			if(!$this->isLoggedIn() && $this->_callback['driver'] != 'login'){
				if(is_callable(array($this->Page, 'handleFailedAuthorisation'))) $this->Page->handleFailedAuthorisation();
				else{
				
					include_once(CONTENT . '/content.login.php'); 			
					$this->Page = new contentLogin;
					$this->Page->build();
				
				}
			}
			
			else $this->Page->build($this->_callback['context']);
			
			return $this->Page;
		}
		
		public function getPageCallback($page=NULL, $update=false){
			
			if((!$page || !$update) && $this->_callback) return $this->_callback;
			elseif(!$page && !$this->_callback) trigger_error(__('Cannot request a page callback without first specifying the page.'));

			// Remove multiple slashes and any flags from the URL (e.g. :saved/ or :created/)
			$this->_currentPage = ADMIN_URL . preg_replace(array('/:[^\/]+\/?$/', '/\/{2,}/'), '/', $page);

			$bits = preg_split('/\//', trim($page, '/'), 3, PREG_SPLIT_NO_EMPTY);
			
			if($bits[0] == 'login'){
				array_shift($bits);
				
				$callback = array(
						'driver' => 'login',
						'context' => preg_split('/\//', implode('/', $bits), -1, PREG_SPLIT_NO_EMPTY),
						'classname' => 'contentLogin',
						'pageroot' => '/login/'
					);
			}
			
			elseif($bits[0] == 'extension' && isset($bits[1])){
				
				$extention_name = $bits[1];
				$bits = preg_split('/\//', trim($bits[2], '/'), 2, PREG_SPLIT_NO_EMPTY);
				
				$callback = array(
								'driver' => NULL,
								'context' => NULL,
								'pageroot' => NULL,
								'classname' => NULL,
								'driverlocation' => EXTENSIONS . '/' . $extention_name . '/content/'
							);			
								
				$callback['driver'] = 'index'; //ucfirst($extention_name);
				$callback['classname'] = 'contentExtension' . ucfirst($extention_name) . 'Index';
				$callback['pageroot'] = '/extension/' . $extention_name. '/';	
				
				if(isset($bits[0])){
					$callback['driver'] = $bits[0];
					$callback['classname'] = 'contentExtension' . ucfirst($extention_name) . ucfirst($bits[0]);
					$callback['pageroot'] .= $bits[0] . '/';
				}
				
				if(isset($bits[1])) $callback['context'] = preg_split('/\//', $bits[1], -1, PREG_SPLIT_NO_EMPTY);
				
				if(!is_file($callback['driverlocation'] . '/content.' . $callback['driver'] . '.php')) return false;
								
			}
			
			elseif($bits[0] == 'publish'){
				
				if(!isset($bits[1])) return false;

				$callback = array(
					'driver' => 'publish',
					'context' => array('section_handle' => $bits[1], 'page' => NULL, 'entry_id' => NULL, 'flag' => NULL),
					'pageroot' => '/' . $bits[0] . '/' . $bits[1] . '/',
					'classname' => 'contentPublish'
				);
				
				if(isset($bits[2])){
					$extras = preg_split('/\//', $bits[2], -1, PREG_SPLIT_NO_EMPTY);
					
					$callback['context']['page'] = $extras[0];
					if(isset($extras[1])) $callback['context']['entry_id'] = intval($extras[1]);
				
					//if(isset($extras[2])) $callback['context']['flag'] = $extras[2];
					
					
					if(preg_match('/\/:([^\/]+)\/?$/', $bits[2], $matches)){
						$callback['flag'] = $matches[1];
						$bits[2] = str_replace($matches[0], NULL, $bits[2]);
					}
					
				}
				
				
				else $callback['context']['page'] = 'index';
				
			}
			
			else{
				
				$callback = array(
								'driver' => NULL,
								'context' => NULL,
								'pageroot' => NULL,
								'classname' => NULL,
								'flag' => NULL
							);
			
				$callback['driver'] = ucfirst($bits[0]);
				$callback['pageroot'] = '/' . $bits[0] . '/';
				
				if(isset($bits[1])){
					$callback['driver'] = $callback['driver'] . ucfirst($bits[1]);
					$callback['pageroot'] .= $bits[1] . '/';
				}
		
				if(isset($bits[2])){

					if(preg_match('/^:([^\/]+)\/?$/', $bits[2], $matches) || preg_match('/\/:([^\/]+)\/?$/', $bits[2], $matches)){
						$callback['flag'] = $matches[1];
						$bits[2] = str_replace($matches[0], NULL, $bits[2]);
					}
					
					$callback['context'] = preg_split('/\//', $bits[2], -1, PREG_SPLIT_NO_EMPTY);
				}
			
				$callback['classname'] = 'content' . $callback['driver'];
				$callback['driver'] = strtolower($callback['driver']);

				if(!is_file(CONTENT . '/content.' . $callback['driver'] . '.php')) return false;
				
			}
			
			## TODO: Add delegate for custom callback creation
			
			return $callback;
			
		}
		
		public function getCurrentPageURL(){
			return $this->_currentPage;
		}
		
		public function display($page){
			
			// Default headers. Can be overwritten later
			//self::$Headers->append('HTTP/1.0 200 OK');
			self::$Headers->append('Content-Type', 'text/html;charset=utf-8');
			self::$Headers->append('Expires', 'Mon, 12 Dec 1982 06:14:00 GMT');
			self::$Headers->append('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
			self::$Headers->append('Cache-Control', 'no-cache, must-revalidate, max-age=0');
			self::$Headers->append('Pragma', 'no-cache');
			
			$this->__buildPage($page);
			
			####
			# Delegate: AdminPagePreGenerate
			# Description: Immediately before generating the admin page. Provided with the page object
			# Global: Yes
			Extension::notify('AdminPagePreGenerate', '/backend/', array('oPage' => &$this->Page));

			$output = (string)$this->Page;

			####
			# Delegate: AdminPagePostGenerate
			# Description: Immediately after generating the admin page. Provided with string containing page source
			# Global: Yes
			Extension::notify('AdminPagePostGenerate', '/backend/', array('output' => &$output));
			
			self::Headers()->render();
			
			return $output;	
		}
	}
	
	return 'Administration';
