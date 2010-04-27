<?php
	
	require_once(TOOLKIT . '/class.view.php');
	
	class DevKit extends View {
		protected $document;
		protected $data;
		
		public function __construct() {
			parent::__construct();
			
			$imp = new DOMImplementation;
			$dtd = $imp->createDocumentType('html');
			
			$this->document = $imp->createDocument('', 'html', $dtd);
			$this->document->encoding = 'UTF-8';
			$this->document->formatOutput = true;
			
			$this->data = (object)array();
		}
		
		public function __isset($name) {
			return isset($this->data->$name);
		}
		
		public function __get($name) {
			if ($name == 'title' and !isset($this->title)) {
				$this->title = __('Untitled');
			}
			
			if ($name == 'header' and !isset($this->header)) {
				$this->title = __('Untitled');
			}
			
			return $this->data->$name;
		}
		
		public function __set($name, $value) {
			$this->data->$name = $value;
		}
		
		public function getCurrentURL($excludes = array()) {
			$query = $this->getCurrentURLQuery($excludes);
			
			return URL . getCurrentPage() . $query;
		}
		
		public function getCurrentURLQuery($excludes = array()) {
			$excludes = array_merge(
				$excludes, array('symphony-page', 'symphony-renderer')
			);
			$query = '';
			
			foreach ($_GET as $index => $value) {
				if (in_array($index, $excludes)) continue;
				
				if (is_null($value) or $value == '') {
					$query .= '&' . $index;
				}
				
				else {
					$query .= '&' . $index . '=' . $value;
				}
			}
			
			if ($query == '') return '';
			
			return '?' . ltrim($query, '&');
		}
		
		protected function createScriptElement($path) {
			$element = $this->document->createElement('script');
			$element->setAttribute('type', 'text/javascript');
			$element->setAttribute('src', $path);

			// Creating an empty text node forces <script></script>
			$element->appendChild($this->createTextNode(''));

			return $element;
		}

		protected function createStylesheetElement($path, $type = 'screen') {
			$element = $this->document->createElement('link');
			$element->setAttribute('type', 'text/css');
			$element->setAttribute('rel', 'stylesheet');
			$element->setAttribute('media', $type);
			$element->setAttribute('href', $path);
			
			return $element;
		}
		
		public function render(Register &$Parameters, XMLDocument &$Document = null) {
			//header('content-type: text/plain');
			
			$this->appendHead($this->document->documentElement);
			$this->appendBody($this->document->documentElement);
			
			return $this->document->saveHTML();
		}
		
		protected function appendHead(DOMElement $wrapper) {
			$head = $this->document->createElement('head');
			
			$title = $this->document->createElement('title');
			$title->appendChild($this->document->createTextNode(
				__('Symphony') . ' '
			));
			$title->appendChild(
				$this->document->createEntityReference('ndash')
			);
			$title->appendChild($this->document->createTextNode(
				' ' . $this->title
			));
			$head->appendChild($title);
			
			$this->appendIncludes($head);
			$wrapper->appendChild($head);
			
			return $head;
		}
		
		protected function appendIncludes(DOMElement $wrapper) {
			$wrapper->appendChild(
				$this->createStylesheetElement(ADMIN_URL . '/assets/css/devkit.css')
			);
		}
		
		protected function appendBody(DOMElement $wrapper) {
			$body = $this->document->createElement('body');
			
			$this->appendContent($body);
			$this->appendSidebar($body);
			
			$wrapper->appendChild($body);
			
			return $body;
		}
		
		protected function appendContent(DOMElement $wrapper) {
			$container = $this->document->createElement('div');
			$container->setAttribute('id', 'content');
			
			
			
			$wrapper->appendChild($container);
			
			return $container;
		}
		
		protected function appendSidebar(DOMElement $wrapper) {
			$container = $this->document->createElement('div');
			$container->setAttribute('id', 'sidebar');
			
			$this->appendHeader($container);
			$this->appendMenu($container);
			$this->appendJump($container);
			
			$wrapper->appendChild($container);
			
			return $container;
		}
		
		protected function appendHeader(DOMElement $wrapper) {
			$header = $this->document->createElement('h1');
			$link = $this->document->createElement('a');
			$link->setAttribute('href', $this->getCurrentURL());
			$link->appendChild($this->document->createTextNode(
				$this->header
			));
			
			$header->appendChild($link);
			$wrapper->appendChild($header);
			
			return $header;
		}
		
		protected function appendMenu(DOMElement $wrapper) {
			$container = $this->document->createElement('ul');
			$container->setAttribute('id', 'menu');
			
			
			
			$wrapper->appendChild($container);
			
			return $container;
		}
		
		protected function appendJump(DOMElement $wrapper) {
			$container = $this->document->createElement('ul');
			$container->setAttribute('id', 'jump');
			
			
			
			$wrapper->appendChild($container);
			
			return $container;
		}
		
		
		
		
		
		/*
		protected $_query_string = '';
		protected $_page = null;
		protected $_pagedata = null;
		protected $_xml = null;
		protected $_param = array();
		protected $_output = '';
		
		protected function buildIncludes() {
			$this->addHeaderToPage('Content-Type', 'text/html; charset=UTF-8');
			
			$this->Html->setElementStyle('html');
			$this->Html->setDTD('<!DOCTYPE html>');
			$this->Html->setAttribute('lang', __LANG__);
			$this->addElementToHead(new XMLElement(
				'meta', null,
				array(
					'http-equiv'	=> 'Content-Type',
					'content'		=> 'text/html; charset=UTF-8'
				)
			));
			$this->addStylesheetToHead(ADMIN_URL . '/assets/css/devkit.css', 'screen');
		}
		
		protected function buildHeader($wrapper) {
			$this->setTitle(__(
				'%1$s &ndash; %2$s &ndash; %3$s',
				array(
					__('Symphony'),
					__($this->_title),
					$this->_pagedata['title']
				)
			));
			
			$h1 = new XMLElement('h1');
			$h1->appendChild(Widget::Anchor(
				$this->_pagedata['title'], ($this->_query_string ? '?' . trim(html_entity_decode($this->_query_string), '&') : '.')
			));
			
			$wrapper->appendChild($h1);
		}
		
		protected function buildNavigation($wrapper) {
			$xml = new DOMDocument();
			$xml->preserveWhiteSpace = false;
			$xml->formatOutput = true;
			$root = $xml->createElement('navigation');
			$xml->appendChild($root);
			
			$first = $root->firstChild;
			$xpath = new DOMXPath($xml);
			$list = new XMLElement('ul');
			$list->setAttribute('id', 'navigation');
			
			// Add edit link:
			$item = new XMLElement('li');
			$item->appendChild(Widget::Anchor(
				__('Edit'), ADMIN_URL . '/blueprints/pages/edit/' . $this->_pagedata['id'] . '/'
			));
			$list->appendChild($item);
			
			// Translate navigaton names:
			if ($root->hasChildNodes()) {
				foreach ($root->childNodes as $item) if ($item->tagName == 'item') {
					$item->setAttribute('name', __($item->getAttribute('name')));
				}
			}
			
			####
			# Delegate: ManipulateDevKitNavigation
			# Description: Allow navigation XML to be manipulated before it is rendered.
			# Global: Yes
			#$this->_page->ExtensionManager->notifyMembers(
			ExtensionManager::instance()->notifyMembers(
				'ManipulateDevKitNavigation', '/frontend/',
				array(
					'xml'	=> $xml
				)
			);
			
			if ($root->hasChildNodes()) {
				foreach ($root->childNodes as $node) {
					if ($node->getAttribute('active') == 'yes') {
						$item = new XMLElement('li', $node->getAttribute('name'));
						
					} else {
						$item = new XMLElement('li');
						$item->appendChild(Widget::Anchor(
							$node->getAttribute('name'),
							'?' . $node->getAttribute('handle') . $this->_query_string
						));
					}
					
					$list->appendChild($item);
				}
			}
			
			$wrapper->appendChild($list);
		}
		
		protected function buildJump($wrapper) {
			
		}
		
		protected function buildContent($wrapper) {
			
		}
		
		protected function buildJumpItem($name, $link, $active = false) {
			$item = new XMLElement('li');
			$anchor = Widget::Anchor($name,  $link);
			$anchor->setAttribute('class', 'inactive');
			
			if ($active == true) {
				$anchor->setAttribute('class', 'active');
			}
			
			$item->appendChild($anchor);
			
			return $item;
		}
		
		public function prepare($page, $pagedata, $xml, $param, $output) {
			$this->_page = $page;
			$this->_pagedata = $pagedata;
			$this->_xml = $xml;
			$this->_param = $param;
			$this->_output = $output;
			
			if (is_null($this->_title)) {
				$this->_title = __('Utility');
			}
		}
		
		public function build() {
			$this->buildIncludes();
			
			$header = new XMLElement('div');
			$header->setAttribute('id', 'header');
			$jump = new XMLElement('div');
			$jump->setAttribute('id', 'jump');
			$content = new XMLElement('div');
			$content->setAttribute('id', 'content');
			
			$this->buildHeader($header);
			$this->buildNavigation($header);
			
			$this->buildJump($jump);
			$header->appendChild($jump);
			
			$this->Body->appendChild($header);
			
			$this->buildContent($content);
			$this->Body->appendChild($content);
			
			return parent::generate();
		}
		*/
	}
	
?>
