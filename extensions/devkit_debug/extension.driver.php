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
				'version'		=> '1.0.5',
				'release-date'	=> '2009-12-01',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://pixelcarnage.com/',
					'email'			=> 'rowan@pixelcarnage.com'
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
		
		/*
		public function frontendDevKitResolve($context) {
			if (false or isset($_GET['debug'])) {
				require_once(EXTENSIONS . '/debugdevkit/content/content.debug.php');
				
				$context['devkit'] = new Content_DebugDevKit_Debug();
				self::$active = true;
			}
			
			else if (false and isset($_GET['debug-edit'])) {
				require_once(EXTENSIONS . '/debugdevkit/content/content.debug.php');
				require_once(EXTENSIONS . '/debugdevkit/content/content.edit.php');
				
				$context['devkit'] = new Content_DebugDevKit_Edit();
				self::$active = true;
			}
		}
		
		public function manipulateDevKitNavigation($context) {
			$xml = $context['xml'];
			$item = $xml->createElement('item');
			$item->setAttribute('name', __('Debug'));
			$item->setAttribute('handle', 'debug');
			$item->setAttribute('active', (self::$active ? 'yes' : 'no'));
			
			$parent = $xml->documentElement;
			
			if ($parent->hasChildNodes()) {
				$parent->insertBefore($item, $parent->firstChild);
			}
			
			else {
				$xml->documentElement->appendChild($item);
			}
		}
		*/
	}
	
?>