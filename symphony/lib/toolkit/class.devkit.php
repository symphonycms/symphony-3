<?php
	
	require_once(TOOLKIT . '/class.htmldocument.php');
	require_once(TOOLKIT . '/class.view.php');
	require_once(TOOLKIT . '/class.urlwriter.php');
	
	class DevKit extends View {
		protected $document;
		protected $data;
		protected $url;
		
		public function __construct(View $view) {
			parent::__construct();
			
			$this->document = new HTMLDocument();
			
			$this->view = $view;
			$this->data = (object)array();
			$this->url = new URLWriter(URL . getCurrentPage(), $_GET);
			
			// Remove symphony parameters:
			unset($this->url->parameters()->{'symphony-page'});
			unset($this->url->parameters()->{'symphony-renderer'});
		}
		
		public function __isset($name) {
			return isset($this->data->$name);
		}
		
		public function __get($name) {
			if ($name == 'title' and !isset($this->title)) {
				$this->title = __('Untitled');
			}
			
			return $this->data->$name;
		}
		
		public function __set($name, $value) {
			$this->data->$name = $value;
		}
		
		public function templatePathname() {
			return $this->view->templatePathname();
		}
		
		protected function createScriptElement($path) {
			$element = $this->document->createElement('script');
			$element->setAttribute('type', 'text/javascript');
			$element->setAttribute('src', $path);

			// Creating an empty text node forces <script></script>
			$element->appendChild($this->document->createTextNode(''));

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
		
		public function render(Register $parameters, XMLDocument &$document) {
			Widget::init($this->document);
			
			$this->appendHead($this->document->documentElement);
			$this->appendBody($this->document->documentElement);
			
			return $this->document->saveHTML();
		}
		
		protected function appendHead(DOMElement $wrapper) {
			$head = $this->document->xpath('/html/head[1]')->item(0);
			
			$this->appendTitle($head);
			$this->appendIncludes($head);
			$wrapper->appendChild($head);
			
			return $head;
		}
		
		protected function appendTitle(DOMElement $wrapper) {
			$title = $this->document->createElement('title');
			$title->appendChild($this->document->createTextNode(
				__('Symphony') . ' '
			));
			$title->appendChild(
				$this->document->createEntityReference('ndash')
			);
			$title->appendChild($this->document->createTextNode(
				' ' . $this->view->title . ' '
			));
			$title->appendChild(
				$this->document->createEntityReference('ndash')
			);
			$title->appendChild($this->document->createTextNode(
				' ' . $this->title
			));
			
			$wrapper->appendChild($title);
			
			return $title;
		}
		
		protected function appendIncludes(DOMElement $wrapper) {
			$wrapper->appendChild(
				$this->createStylesheetElement(ADMIN_URL . '/assets/css/devkit.css')
			);
		}
		
		protected function appendBody(DOMElement $wrapper) {
			$body = $this->document->xpath('/html/body[1]')->item(0);
			
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
			
			// Tab:
			$tab = $this->document->createElement('p');
			$tab->setAttribute('id', 'tab');
			$tab->appendChild(Widget::Anchor(
				$this->title, ''
			));
			$container->appendChild($tab);
			
			// Header:
			$header = $this->document->createElement('h1');
			$header->appendChild(Widget::Anchor(
				$this->view->title, (string)$this->url
			));
			$container->appendChild($header);
			
			$list = $this->document->createElement('ul');
			$list->setAttribute('class', 'menu');
			
			$root = $this->document->createElement('navigation');
			
			####
			# Delegate: DevKiAppendtMenuItem
			# Description: Allow navigation XML to be manipulated before it is rendered.
			# Global: Yes
			#$this->_page->ExtensionManager->notifyMembers(
			ExtensionManager::instance()->notifyMembers(
				'DevKiAppendtMenuItem', '/frontend/',
				array(
					'wrapper'	=> $root
				)
			);
			
			if ($root->hasChildNodes()) {
				foreach ($root->childNodes as $node) {
					if ($node->getAttribute('active') == 'yes') {
						$item = $this->document->createElement('li', $node->getAttribute('name'));
					}
					
					else {
						$handle = $node->getAttribute('handle');
						
						$url = clone $this->url;
						$url->parameters()->$handle = null;
						
						$item = $this->document->createElement('li');
						$item->appendChild(Widget::Anchor(
							$node->getAttribute('name'),
							'?' . (string)$url
						));
					}
					
					$list->appendChild($item);
				}
			}
			
			$item = $this->document->createElement('li');
			$item->appendChild(Widget::Anchor(
				__('Edit'), ADMIN_URL . '/blueprints/views/edit/' . $this->view->handle . '/'
			));
			$list->prependChild($item);
			
			$container->appendChild($list);
			
			// Main:
			$fieldset = Widget::Fieldset(__('Pages'));
			$container->appendChild($fieldset);
			$wrapper->appendChild($container);
			
			return $container;
		}
		
		protected function appendLink(DOMElement $wrapper, $name, $link, $active = false) {
			$item = $this->document->createElement('li');
			$anchor = $this->document->createElement('a');
			$anchor->setAttribute('href', $link);
			$anchor->setAttribute('class', 'inactive');
			$anchor->appendChild(
				$this->document->createTextNode($name)
			);
			
			if ($active == true) {
				$anchor->setAttribute('class', 'active');
			}
			
			$item->appendChild($anchor);
			$wrapper->appendChild($item);
			
			return $item;
		}
	}
	
?>
