<?php
	
	class Extension_DevKit_Debug extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public static $view = null;
		public static $class = null;
		
		public function about() {
			return array(
				'name'			=> 'Debug DevKit',
				'version'		=> '2.0',
				'release-date'	=> '2010-04-28',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://rowanlewis.com/',
					'email'			=> 'me@rowanlewis.com'
				),
				'provides'		=> array(
					'devkit'
				)
			);
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendPreInitialise',
					'callback'	=> 'frontendPreInitialise'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendPreRender',
					'callback'	=> 'frontendPreRender'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'DevKiAppendtMenuItem',
					'callback'	=> 'appendDevKitMenuItem'
				)
			);
		}
		
		public function frontendPreInitialise($context) {
			if (isset($_GET['debug'])) {
				self::$class = require_once(EXTENSIONS . '/devkit_debug/lib/class.debug.php');
			}
		}
		
		public function frontendPreRender($context) {
			if (!self::$class) return;
			
			self::$view = $context['view'];
			$context['view'] = new self::$class(self::$view);
		}
		
		public function appendDevKitMenuItem($context) {
			$wrapper = $context['wrapper'];
			$document = $wrapper->ownerDocument;
			
			$item = $document->createElement('item');
			$item->setAttribute('name', __('Debug'));
			$item->setAttribute('handle', 'debug');
			$item->setAttribute('active', (self::$class ? 'yes' : 'no'));
			
			if ($wrapper->hasChildNodes()) {
				$wrapper->insertBefore($item, $wrapper->firstChild);
			}
			
			else {
				$wrapper->appendChild($item);
			}
		}
	}
	
?>