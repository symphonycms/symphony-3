<?php

	Interface iExtension{

/*		
		public function about();

		public function enable();
		public function disable();

		public function install();
		public function update($previous_version=NULL);
		public function uninstall();

		public function getSubscribedDelegates();
		public function fetchNavigation();
*/

	}


	Class ExtensionException extends Exception{
	}

	Abstract Class Extension{
		
		static private $loaded_extensions;
		static private $extension_configuration;
		static private $extensions;
		
		const NAVIGATION_CHILD = 'child';
		const NAVIGATION_GROUP = 'group';
		
		const STATUS_ENABLED = 'enabled';
		const STATUS_DISABLED = 'disabled';
		const STATUS_NOT_INSTALLED = 'not-installed';
		const STATUS_REQUIRES_UPDATE = 'requires-update';

		public static function enable($handle){
			
			$extension = self::load($handle);
			$status = self::status($handle);
			
			$node = end(self::$extension_configuration->xpath("//extension[@handle='{$handle}'][1]"));
			
			if($status == self::STATUS_NOT_INSTALLED){
				if(is_callable(array($extension, 'install'))){
					$extension->install();
				}
				
				// Create the XML configuration object
				if(empty($node)){
					$node = self::$extension_configuration->addChild('extension');
					$node->addAttribute('handle', $handle);
					$node->addAttribute('version', $extension->about()->version);
				}
			}
			
			elseif($status == self::STATUS_REQUIRES_UPDATE){
				if(is_callable(array($extension, 'update'))){
					$extension->update($this->extension_configuration->xpath((string)"//extension[@handle='{$handle}']/@version"));
				}
				
				$node['version'] = $extension->about()->version;

			}
			
			if(is_callable(array($extension, 'enable'))){
				$extension->enable();
			}
			
			$node['status'] = self::STATUS_ENABLED;
			
			self::rebuildConfiguration();
		}
		
		public static function disable($handle){
			$extension = self::load($handle);
			$node = end(self::$extension_configuration->xpath("//extension[@handle='{$handle}'][1]"));
			if(is_callable(array($extension, 'disable'))){
				$extension->disable();
			}
			$node['status'] = self::STATUS_DISABLED;
			self::rebuildConfiguration();
		}
		
		public static function uninstall($handle){
			$extension = self::load($handle);
			$node = end(self::$extension_configuration->xpath("//extension[@handle='{$handle}'][1]"));
			if(is_callable(array($extension, 'uninstall'))){
				$extension->uninstall();
			}
			$node['status'] = self::STATUS_NOT_INSTALLED;
			self::rebuildConfiguration();
		}
		
		public static function findSubscribed($delegate, $page){
			// Prepare the xpath
			$xpath = sprintf(
				"delegates/item[@delegate='%s'][@page='*' or %s]", 
				$delegate, 
				implode(' or ', array_map(create_function('$value', 'return "@page=\'{$value}\'";'), (array)$page))
			);

			$nodes = self::$extension_configuration->xpath("//extension[@status='enabled'][{$xpath}]");
			
			return $nodes;
		}
		
		public static function delegateSubscriptionCount($delegate, $page){
			$nodes = self::findSubscribed($delegate, $page);
			return count($nodes);
		}
		
		public static function notify($delegate, $page, $context=array()){
			
			$count = 0;
			$nodes = self::findSubscribed($delegate, $page);
			
			if(!empty($nodes)){
				
				// Prepare the xpath
				$xpath = sprintf(
					"delegates/item[@delegate='%s'][@page='*' or %s]", 
					$delegate, 
					implode(' or ', array_map(create_function('$value', 'return "@page=\'{$value}\'";'), (array)$page))
				);
				
				foreach($nodes as $e){
					$extension = self::load((string)$e->attributes()->handle);
					$delegates = $e->xpath($xpath);
				
					foreach($delegates as $d){
						$count++;
						if(is_callable(array($extension, (string)$d->attributes()->callback))){
							$extension->{(string)$d->attributes()->callback}($context);
						}
					}
				}
			}
			
			return $count;
		}
		
		public static function init($config=NULL){
			
			self::$extensions = array();
			
			if(is_null($config)){
				$config = MANIFEST . '/extensions.xml';
			}
			
			if(!file_exists($config)){
				self::$extension_configuration = new SimpleXMLElement('<extensions></extensions>');
			}
			else{
				$previous = libxml_use_internal_errors(true);
				self::$extension_configuration = simplexml_load_file($config);
				libxml_use_internal_errors($previous);
			
				if(!(self::$extension_configuration instanceof SimpleXMLElement)){
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
			$doc = dom_import_simplexml(self::$extension_configuration);
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
				
				// TODO: Check if the extension is already present in the config, and retain the version & status if it is
				// This ensures that the status of "requires-update" remains intact.
				
				$pathname = self::getPathFromClass(get_class($extension));
				$handle = self::getHandleFromPath($pathname);
				
				$element = $doc->createElement('extension');
				$element->setAttribute('handle', $handle);
				
				$node = end(self::$extension_configuration->xpath("//extension[@handle='{$handle}'][1]"));
				if(!empty($node)){
					$element->setAttribute('version', $node->attributes()->version);
					$element->setAttribute('status', $node->attributes()->status);
				}
				else{
					$element->setAttribute('version', $extension->about()->version);
					$element->setAttribute('status', self::status($handle));
				}
				
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
			
		 	self::$extension_configuration = simplexml_import_dom($doc);
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
				if(is_null(self::$loaded_extensions[$pathname]) || !class_exists(self::$loaded_extensions[$pathname])){
					throw new ExtensionException('Extension driver found at "'.$pathname.'" did not return a valid classname for instantiation.');
				}
				self::$extensions[$handle] = new self::$loaded_extensions[$pathname];
			}

			return self::$extensions[$handle];
			
		}
		
		public static function status($handle){
			
			$status = self::STATUS_NOT_INSTALLED;
			
			$extension = self::load($handle);
			
			$node = end(self::$extension_configuration->xpath("//extension[@handle='{$handle}'][1]"));

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

