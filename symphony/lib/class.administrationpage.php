<?php

	require_once(LIB . '/class.xmldocument.php');
	require_once(LIB . '/class.section.php');
	require_once(LIB . '/class.layout.php');
	require_once(LIB . '/class.alertstack.php');

	Class AdministrationPage extends XMLDocument{
		public $_navigation;
		public $_context;
		protected $_alerts;

		public function __construct(){
			parent::__construct('1.0', 'utf-8');
			$this->formatOutput = true;
			$this->alerts = new AlertStack;
		}

		/*public function setTitle($value, $position=null) {
			$doc = new XMLDocument;
			$doc->loadHTML("<title>{$value}</title>");
			$node = $this->importNode($doc->xpath('//title')->item(0), true);
			return $this->insertNodeIntoHead($node, $position);
		}*/

		public function Context(){
			return $this->_context;
		}

		public function minify(array $files, $output_pathname, $unlink_existing=true){

			if(file_exists($output_pathname) && $unlink_existing === true) unlink($output_pathname);

			foreach($files as $pathname){
				if(!file_exists($pathname) || !is_readable($pathname)) throw new Exception("File '{$pathname}' could not be found, or is not readable.");

				$contents = file_get_contents($pathname);

				if(file_put_contents($output_pathname, $contents . "\n", FILE_APPEND) === false){
					throw new Exception("Could not write to '{$output_pathname}.");
				}
			}
		}

		public function build($context = NULL){
			$this->_context = $context;
			/*
			$meta = $this->createElement('meta');
			$this->insertNodeIntoHead($meta);
			$meta->setAttribute('http-equiv', 'Content-Type');
			$meta->setAttribute('content', 'text/html; charset=UTF-8');

			$styles = array(
				ADMIN_URL . '/assets/css/symphony.css',
				ADMIN_URL . '/assets/css/symphony.duplicator.css',
				ADMIN_URL . '/assets/css/symphony.layout.css'
			);

			$scripts = array(
				ADMIN_URL . '/assets/js/jquery.js',
				ADMIN_URL . '/assets/js/jquery-ui.js',
				ADMIN_URL . '/assets/js/symphony.collapsible.js',
				ADMIN_URL . '/assets/js/symphony.orderable.js',
				ADMIN_URL . '/assets/js/symphony.duplicator.js',
				ADMIN_URL . '/assets/js/symphony.layout.js',
				ADMIN_URL . '/assets/js/symphony.tags.js',
				ADMIN_URL . '/assets/js/symphony.selectable.js',
				ADMIN_URL . '/assets/js/symphony.js'
			);

			// Builds a super JS and CSS document
			if(Symphony::Configuration()->core()->symphony->{'condense-scripts-and-stylesheets'} == 'yes'){

				if(file_exists(CACHE . '/admin-styles.css')){
					$styles = array(URL . '/manifest/cache/admin-styles.css');
				}
				else{
					try{
						$this->minify(array_map(create_function('$a', 'return DOCROOT . "/symphony/assets/css/" . basename($a);'), $styles), CACHE . '/admin-styles.css');
						$styles = array(URL . '/manifest/cache/admin-styles.css');
					}
					catch(Exception $e){
					}
				}

				if(file_exists(CACHE . '/admin-scripts.js')){
					$scripts = array(URL . '/manifest/cache/admin-scripts.js');
				}
				else{
					try{
						$this->minify(array_map(create_function('$a', 'return DOCROOT . "/symphony/assets/js/" . basename($a);'), $scripts), CACHE . '/admin-scripts.js');
						$scripts = array(URL . '/manifest/cache/admin-scripts.js');
					}
					catch(Exception $e){
					}
				}
			}

			foreach($styles as $pathname){
				$this->insertNodeIntoHead($this->createStylesheetElement($pathname));
			}

			foreach($scripts as $pathname){
				$this->insertNodeIntoHead($this->createScriptElement($pathname));
			}*/

			###
			# Delegate: InitaliseAdminPageHead
			# Description: Allows developers to insert items into the page HEAD. Use $context['parent']->Page
			#			   for access to the page object
			//Extension::notify('InitaliseAdminPageHead', '/administration/');

			//$this->Headers->append('Content-Type', 'text/html; charset=UTF-8');

			$this->prepare();

			if(isset($_REQUEST['action'])){
				$this->action();
			}

			$xml = $this->view();
			//$this->appendSession();
			$xml->appendChild($this->appendNavigation());

			###
			# Delegate: AppendElementBelowView
			# Description: Allows developers to add items just above the page footer. Use $context['parent']->Page
			#			   for access to the page object
			Extension::notify('AppendElementBelowView', '/administration/');

			$this->appendAlert();

			return $xml;
		}

		public function view(){
			return $this->__switchboard();
		}

		public function action(){
			return $this->__switchboard('action');
		}

		public function prepare(){
			return $this->__switchboard('prepare');
		}

		public function __switchboard($type='view'){

			if(!isset($this->_context[0]) || trim($this->_context[0]) == '') $context = 'index';
			else $context = $this->_context[0];

			$function = '__' . $type . ucfirst($context);

			// If there is no view function, throw an error
			if (!is_callable(array($this, $function))){

				if ($type == 'view'){
					throw new AdministrationPageNotFoundException;
				}

				return false;
			}
			return $this->$function();
		}

		public function alerts(){
			return $this->alerts;
		}

		public function appendAlert(){
			###
			# Delegate: AppendPageAlert
			# Description: Allows for appending of alerts. Administration::instance()->Page->Alert is way to tell what
			# is currently in the system
			Extension::notify('AppendPageAlert', '/administration/');

			if ($this->alerts()->valid()) {
				$this->alerts()->appendTo($this->Body);
			}
		}

		public function appendSession(){

			$ul = $this->createElement('ul');
			$ul->setAttribute('id', 'session');

			$li = $this->createElement('li');
			$li->appendChild(
				Widget::Anchor(Administration::instance()->User->getFullName(), ADMIN_URL . '/system/users/edit/' . Administration::instance()->User->id . '/')
			);
			$ul->appendChild($li);

			$li = $this->createElement('li');
			$li->appendChild(
				Widget::Anchor(__('Logout'), ADMIN_URL . '/logout/')
			);
			$ul->appendChild($li);

			###
			# Delegate: AddElementToFooter
			# Description: Add new list elements to the footer
			Extension::notify('AddElementToFooter', '/administration/', array('wrapper' => &$ul));

			$this->Form->appendChild($ul);
		}

		public function appendSubheading($string, $link=NULL){
			$h2 = $this->createElement('h2', $string);
			if(!is_null($link)) $h2->appendChild($link);

			$this->Form->appendChild($h2);
		}

		public function appendNavigation(){

			$nav = $this->getNavigationArray();

			####
			# Delegate: NavigationPreRender
			# Description: Immediately before displaying the admin navigation. Provided with the navigation array
			#              Manipulating it will alter the navigation for all pages.
			# Global: Yes
			Extension::notify('NavigationPreRender', '/administration/', array('navigation' => &$nav));

			$nav_xml = $this->createElement('navigation');

			foreach($nav as $n){
				$can_access = true;

				if(!isset($n['visible']) or $n['visible'] != 'no'){

					if($can_access == true) {

						$group_xml = $this->createElement('group');
						$name = $this->createElement('name',$n['name']);
						$name->setAttribute('handle',Lang::createHandle($n['name']));
						$group_xml->appendChild($name);
						$items_xml = $this->createElement('items');

						$hasChildren = false;

						if(is_array($n['children']) && !empty($n['children'])){
							foreach($n['children'] as $c){

								$can_access_child = true;

								if($c['visible'] != 'no'){

									if($can_access_child == true) {

										$item = $this->createElement('item');
										$item_name = $this->createElement('name', $c['name']);
										$item_name->setAttribute('handle',$c['handle']);
										$item->appendChild(
											$item_name
										);
										$item->appendChild(
											$this->createElement('link', $c['link'])
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

			return $nav_xml;
		}

		public function getNavigationArray(){
			if(empty($this->_navigation)) $this->__buildNavigation();
			return $this->_navigation;
		}

		private static function __navigationFindGroupIndex($nav, $name){
			foreach($nav as $index => $item){
				if($item['name'] == $name) return $index;
			}
			return false;
		}

		protected function __buildNavigation(){

			$nav = array();

			$xml = simplexml_load_file(ASSETS . '/navigation.xml');

			foreach($xml->xpath('/navigation/group') as $n){

				$index = (string)$n->attributes()->index;
				$children = $n->xpath('children/item');
				$content = $n->attributes();

				if(isset($nav[$index])){
					do{
						$index++;
					}while(isset($nav[$index]));
				}

				$nav[$index] = array(
					'name' => __(strval($content->name)),
					'index' => $index,
					'children' => array()
				);

				if(strlen(trim((string)$content->limit)) > 0){
					$nav[$index]['limit'] = (string)$content->limit;
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

						$nav[$index]['children'][] = $item;
					}
				}
			}

			foreach(new SectionIterator as $s){

				$group_index = self::__navigationFindGroupIndex($nav, $s->{'navigation-group'});

				if($group_index === false){
					$group_index = General::array_find_available_index($nav, 0);

					$nav[$group_index] = array(
						'name' => $s->{'navigation-group'},
						'index' => $group_index,
						'children' => array(),
						'limit' => NULL
					);
				}

				$nav[$group_index]['children'][] = array(
					'link' => '/publish/' . $s->handle . '/',
					'name' => $s->name,
					'type' => 'section',
					'section' => array('id' => $s->guid, 'handle' => $s->handle),
					'visible' => ($s->{'hidden-from-publish-menu'} == 'no' ? 'yes' : 'no')
				);
			}

//			$extensions = ExtensionManager::instance()->listInstalledHandles();

			foreach(new ExtensionIterator(ExtensionIterator::FLAG_STATUS, Extension::STATUS_ENABLED) as $e){

				if(!method_exists($e, 'fetchNavigation')) continue;

				$e_navigation = $e->fetchNavigation();

				if(isset($e_navigation) && is_array($e_navigation) && !empty($e_navigation)){

					foreach($e_navigation as $item){

						$type = (isset($item['children']) ? Extension::NAVIGATION_GROUP : Extension::NAVIGATION_CHILD);

						switch($type){

							case Extension::NAVIGATION_GROUP:

								$index = General::array_find_available_index($nav, $item['location']);

								$nav[$index] = array(
									'name' => $item['name'],
									'index' => $index,
									'children' => array(),
									'limit' => (!is_null($item['limit']) ? $item['limit'] : NULL)
								);

								foreach($item['children'] as $child){

									if(!isset($child['relative']) || $child['relative'] == true){
										$link = '/extension/' . Extension::getHandleFromPath(Extension::getPathFromClass(get_class($e))) . '/' . ltrim($child['link'], '/');
									}
									else{
										$link = '/' . ltrim($child['link'], '/');
									}

									$nav[$index]['children'][] = array(

										'link' => $link,
										'name' => $child['name'],
										'visible' => ($child['visible'] == 'no' ? 'no' : 'yes'),
										'limit' => (!is_null($child['limit']) ? $child['limit'] : NULL)
									);
								}

								break;

							case Extension::NAVIGATION_CHILD:

								if(!isset($item['relative']) || $item['relative'] == true){
									$link = '/extension/' . Extension::getHandleFromPath(Extension::getPathFromClass(get_class($e))) . '/' . ltrim($item['link'], '/');
								}
								else{
									$link = '/' . ltrim($item['link'], '/');
								}

								if(!is_numeric($item['location'])){
									// is a navigation group
									$group_name = $item['location'];
									$group_index = $this->__findLocationIndexFromName($nav, $item['location']);
								} else {
									// is a legacy numeric index
									$group_index = $item['location'];
								}

								$child = array(
									'link' => $link,
									'name' => $item['name'],
									'visible' => ($item['visible'] == 'no' ? 'no' : 'yes'),
									'limit' => (!is_null($item['limit']) ? $item['limit'] : NULL)
								);

								if ($group_index === false) {
									// add new navigation group
									$nav[] = array(
										'name' => $group_name,
										'index' => $group_index,
										'children' => array($child),
										'limit' => (!is_null($item['limit']) ? $item['limit'] : NULL)
									);
								} else {
									// add new location by index
									$nav[$group_index]['children'][] = $child;
								}


								break;

						}

					}

				}

			}

			####
			# Delegate: ExtensionsAddToNavigation
			# Description: After building the Navigation properties array. This is specifically
			# 			for extentions to add their groups to the navigation or items to groups,
			# 			already in the navigation. Note: THIS IS FOR ADDING ONLY! If you need
			#			to edit existing navigation elements, use the 'NavigationPreRender' delegate.
			# Global: Yes
			Extension::notify(
				'ExtensionsAddToNavigation', '/administration/', array('navigation' => &$nav)
			);

			$pageCallback = Administration::instance()->getPageCallback();

			$pageRoot = $pageCallback['pageroot'] . (isset($pageCallback['context'][0]) ? $pageCallback['context'][0] . '/' : '');
			$found = $this->__findActiveNavigationGroup($nav, $pageRoot);

			## Normal searches failed. Use a regular expression using the page root. This is less
			## efficent and should never really get invoked unless something weird is going on
			if(!$found) $this->__findActiveNavigationGroup($nav, '/^' . str_replace('/', '\/', $pageCallback['pageroot']) . '/i', true);

			ksort($nav);
			$this->_navigation = $nav;

		}

		protected function __findLocationIndexFromName($nav, $name){
			foreach($nav as $index => $group){
				if($group['name'] == $name){
					return $index;
				}
			}

			return false;
		}

		protected function __findActiveNavigationGroup(&$nav, $pageroot, $pattern=false){

			foreach($nav as $index => $contents){
				if(is_array($contents['children']) && !empty($contents['children'])){
					foreach($contents['children'] as $item){

						if($pattern && preg_match($pageroot, $item['link'])){
							$nav[$index]['class'] = 'active';
							return true;
						}

						elseif($item['link'] == $pageroot){
							$nav[$index]['class'] = 'active';
							return true;
						}

					}
				}
			}

			return false;

		}

		public function appendViewOptions(array $options) {
			$div = $this->createElement('div');
			$div->setAttribute('id', 'tab');
			$list = $this->createElement('ul');

			foreach ($options as $name => $link) {
				$item = $this->createElement('li');
				$item->appendChild(
					Widget::Anchor($name, $link, array(
						'class' => (Administration::instance()->getCurrentPageURL() == rtrim($link, '/') ? 'active' : null)
					))
				);

				$list->appendChild($item);
			}

			$div->appendChild($list);
			$this->Form->appendChild($div);
		}

		public function __toString(){
			return $this->saveXML();
		}

	}

