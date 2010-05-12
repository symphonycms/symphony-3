<?php

	Interface iExtension{
	}
/*		
		public function about();

		public function enable();
		public function disable();

		public function install();
		public function update($previous_version=NULL);
		public function uninstall();

		public function getSubscribedDelegates();
		public function fetchNavigation();

	}
*/

	Class ExtensionException extends Exception{
	}

	Abstract Class Extension{
		
		static private $loaded_extensions;
		static private $extensions;
		
		const NAVIGATION_CHILD = 'child';
		const NAVIGATION_GROUP = 'group';
		
		const STATUS_ENABLED = 'enabled';
		const STATUS_DISABLED = 'disabled';
		const STATUS_NOT_INSTALLED = 'not-installed';
		const STATUS_REQUIRES_UPDATE = 'requires-update';

		public static function enable(){}
		public static function disable(){}
		public static function install(){}
		public static function update(){}
		public static function uninstall(){}
		
		public static function notify($delegate, $page, $context=array()){

			// Prepare the xpath
			$xpath = sprintf(
				"delegates/item[@delegate='%s'][@page='*' or %s]", 
				$delegate, 
				implode(' or ', array_map(create_function('$value', 'return "@page=\'{$value}\'";'), (array)$page))
			);
			
			$nodes = self::$extensions->xpath("//extension[@status='enabled'][{$xpath}]");
			if(empty($nodes)) return;
			
			foreach($nodes as $e){
				$extension = self::load($e->attributes()->handle);
				$delegates = $e->xpath($xpath);
				
				foreach($delegates as $d){
					$extension->{(string)$d->attributes()->callback}($context);
				}
			}
		}
		
		public static function init($config=NULL){
			if(is_null($config)){
				$config = MANIFEST . '/extensions.xml';
			}
			
			if(!file_exists($config)){
				self::$extensions = new SimpleXMLElement('<extensions></extensions>');
			}
			else{
				$previous = libxml_use_internal_errors(true);
				self::$extensions = simplexml_load_file($config);
				libxml_use_internal_errors($previous);
			
				if(!(self::$extensions instanceof SimpleXMLElement)){
					throw new ExtensionException('Failed to load Extension configuration file ' . $config);
				}
			}
		}
		
		public static function getPathFromClass($class){
			$flipped = array_flip(self::$loaded_extensions);
			return (isset($flipped[$class]) ? $flipped[$class] : NULL);
		}
		
		public static function getHandleFromPath($pathname){
			return str_replace(EXTENSIONS . '/', NULL, $pathname);
		}
		
		public static function saveConfiguration($pathname=NULL){
			if(is_null($pathname)){
				$pathname = MANIFEST . '/extensions.xml';
			}
			
			// Import the SimpleXMLElement object into a DOMDocument object. This ensures formatting is preserved
			$doc = dom_import_simplexml(self::$extensions);
			$doc->ownerDocument->preserveWhiteSpace = false;
			$doc->ownerDocument->formatOutput = true;
			
			General::writeFile($pathname, $doc->ownerDocument->saveXML(), Symphony::Configuration()->core()->symphony->{'file-write-mode'});
		}
		
		public static function rebuildConfiguration($config_pathname=NULL){

			$doc = new DOMDocument('1.0', 'utf-8');
			$doc->formatOutput = true;
			$doc->appendChild($doc->createElement('extensions'));
			$root = $doc->documentElement;
			
			foreach(new ExtensionIterator as $extension){
				
				// TODO: Check if the extension is already present in the config, and retain the version if it is
				// This ensures that the status of "requires-update" remains intact.
				
				$pathname = self::getPathFromClass(get_class($extension));
				$handle = self::getHandleFromPath($pathname);
				
				$element = $doc->createElement('extension');
				$element->setAttribute('handle', $handle);
				$element->setAttribute('version', $extension->about()->version);
				$element->setAttribute('status', self::status($handle));
				$root->appendChild($element);
				
				if(method_exists($extension, 'getSubscribedDelegates')){
					
					$delegates = $doc->createElement('delegates');
					foreach((array)$extension->getSubscribedDelegates() as $delegate){
						$item = $doc->createElement('item');
						$item->setAttribute('page', $delegate['page']);
						$item->setAttribute('delegate', $delegate['delegate']);
						$item->setAttribute('callback', $delegate['callback']);
						$delegates->appendChild($item);
					}
					$element->appendChild($delegates);
				}	
			}
			
		 	self::$extensions = simplexml_import_dom($doc);
			self::saveConfiguration($config_pathname);
		}
		
		public static function load($handle){
			
			$pathname = EXTENSIONS . "/{$handle}";
			
			if(!is_array(self::$loaded_extensions)){
				self::$loaded_extensions = array();
			}
			
			if(!isset(self::$loaded_extensions[$pathname])){
				if(!file_exists(realpath($pathname) . '/extension.driver.php')){
					throw new ExtensionException('No extension driver found at ' . $pathname);
				}
				
				self::$loaded_extensions[$pathname] = require_once(realpath($pathname) . '/extension.driver.php');
			}

			return new self::$loaded_extensions[$pathname];
		}
		
		public static function status($handle){
			
			$status = self::STATUS_NOT_INSTALLED;
			
			$extension = self::load($handle);
			
			$node = end(self::$extensions->xpath("//extension[@handle='{$handle}'][1]"));

			if(!empty($node)){

				if($node->attributes()->status == self::STATUS_ENABLED && $node->attributes()->version != $extension->about()->version){
					$node['status'] = self::STATUS_REQUIRES_UPDATE;
				}
				
				$status = $node->attributes()->status;
			}

			return (string)$status;
		}
	}
	
	Class ExtensionIterator implements Iterator{
		
		const FLAG_STATUS = 'status';
		const FLAG_TYPE = 'type';
		
		private $position;
		private $extensions;

		public function __construct($flag=NULL, $value=NULL){
			$this->extensions = array();
			$this->position = 0;

			foreach(new DirectoryIterator(EXTENSIONS) as $d){
				if(!$d->isDir() || $d->isDot() || !file_exists($d->getPathname() . '/extension.driver.php')) continue;
				
				$extension = Extension::load($d->getFileName());
				
				if(!is_null($flag) && !is_null($value)){
					switch($flag){
						case self::FLAG_STATUS:
							if(!in_array(Extension::status($d->getFileName()), (array)$value)) continue 2;
							break;
							
						case self::FLAG_TYPE:
							if(!isset($extension->about()->type) || (bool)array_intersect((array)$value, (array)$extension->about()->type) === false) continue 2;
							break;
					}
				}
				
				$this->extensions[] = $extension;
			}
		}

		public function length(){
			return count($this->extensions);
		}

		public function rewind(){
			$this->position = 0;
		}

		public function current(){
			return $this->extensions[$this->position];
		}

		public function key(){
			return $this->position;
		}

		public function next(){
			++$this->position;
		}

		public function valid(){
			return isset($this->extensions[$this->position]);
		}
	}
	
	/*
	Extension::init();

	
	foreach(new ExtensionIterator(ExtensionIterator::FLAG_STATUS, Extension::STATUS_ENABLED) as $extension){
		var_dump($extension);
	}
	

	Extension::notify('CustomSaveActions', '/system/settings/extensions/', array('banana' => 'chicken'));

	die();
	*/
	// Extension::rebuildConfiguration();
	// Extension::saveConfiguration();
	// die();

