<?php
	
	if (!defined('BITTER_LANGUAGE_PATH')) {
		define('BITTER_LANGUAGE_PATH', EXTENSIONS . '/devkit_debug/lib/bitter/languages');
	}
	
	if (!defined('BITTER_FORMAT_PATH')) {
		define('BITTER_FORMAT_PATH', EXTENSIONS . '/devkit_debug/lib/bitter/formats');
	}
	
	if (!defined('BITTER_CACHE_PATH')) {
		define('BITTER_CACHE_PATH', EXTENSIONS . '/devkit_debug/lib/bitter/caches');
	}
	
	require_once(TOOLKIT . '/class.devkit.php');
	require_once(EXTENSIONS . '/devkit_debug/lib/bitter/bitter.php');
	
	// Create cache folter:
	if (!is_dir(BITTER_CACHE_PATH)) {
		General::realiseDirectory(BITTER_CACHE_PATH);
	}
	
	class DevKit_Debug extends DevKit {
		protected $show = null;
		protected $view = null;
		protected $input = null;
		protected $output = null;
		protected $params = null;
		protected $template = null;
		protected $utilities = null;
		
		public function __construct(View $view) {
			parent::__construct($view);
			
			$this->title = __('Debug');
			$this->show = ($_GET['debug'] ? $_GET['debug'] : 'xml');
			$this->utilities = $this->findUtilities($this->view->template);
			
			unset($this->url->parameters()->debug);
		}
		
		protected function findUtilities($source) {
			$found = $tree = array();
			
			$this->findUtilitiesRecursive(
				WORKSPACE, $source,
				$found, $tree
			);
			
			if (empty($tree)) return array();
			
			return $tree;
		}
		
		protected function findUtilitiesRecursive($path, $source, &$found, &$tree) {
			$utilities = array();
			
			if (preg_match_all('/<xsl:(import|include)\s*href="([^"]*)/i', $source, $matches)) {
				$utilities = $matches[2];
			}
			
			// Validate paths:
			foreach ($utilities as $index => &$utility) {
				$utility = realpath($path . '/' . $utility);
				
				if (
					$utility === false
					or in_array($utility, $found)
					or !is_file($utility)
				) continue;
				
				$source = file_get_contents($utility);
				$sub_tree = array();
				
				if (trim($source) == '') continue;
				
				$this->findUtilitiesRecursive(
					dirname($utility), $source,
					$found, $sub_tree
				);
				
				$found[] = $utility;
				$tree[] = (object)array(
					'file'	=> $utility,
					'tree'	=> $sub_tree
				);
			}
		}
		
		public function render(Register &$parameters, XMLDocument &$document = null) {
			$this->template = $this->view->template;
			
			if ($this->show == 'xml' or $this->show == 'result' or $this->show == 'params') {
				try {
					$this->output = $this->view->render($parameters, $document);
				}
				
				catch (Exception $e) {
					// We may throw it later.
				}
			}
			
			if ($this->show == 'xml') {
				$document->formatOutput = true;
				$this->input = $document->saveXML();
			}
			
			if ($this->show == 'params') {
				$this->params = $parameters;
			}
			
			return parent::render($parameters, $document);
		}
		
		protected function appendIncludes(DOMElement $wrapper) {
			parent::appendIncludes($wrapper);
			
			$wrapper->appendChild(
				$this->createStylesheetElement(URL . '/extensions/devkit_debug/assets/devkit.css')
			);
			$wrapper->appendChild(
				$this->createScriptElement(URL . '/symphony/assets/js/jquery.js')
			);
			$wrapper->appendChild(
				$this->createScriptElement(URL . '/extensions/devkit_debug/assets/jquery.scrollto.js')
			);
			$wrapper->appendChild(
				$this->createScriptElement(URL . '/extensions/devkit_debug/assets/devkit.js')
			);
		}
		
		protected function appendJump(DOMElement $wrapper) {
			$url = clone $this->url;
			
			$url->parameters()->debug = null;
			$this->appendJumpItem(
				$wrapper, __('XML'),
				(string)$url, ($this->show == 'xml')
			);
			
			$url->parameters()->debug = 'result';
			$this->appendJumpItem(
				$wrapper, __('Result'),
				(string)$url, ($this->show == 'result')
			);
			
			$url->parameters()->debug = 'params';
			$this->appendJumpItem(
				$wrapper, __('Params'),
				(string)$url, ($this->show == 'params')
			);
			
			$url->parameters()->debug = 'view';
			$item = $this->appendJumpItem(
				$wrapper, basename($this->view->templatePathname()),
				(string)$url, ($this->show == 'view')
			);
			
			$this->appendJumpUtility($item, $this->utilities);
		}
		
		protected function appendJumpUtility(DOMElement $wrapper, $utilities) {
			$url = clone $this->url;
			$list = $this->document->createElement('ul');
			
			foreach ($utilities as $utility) {
				$path = ltrim(substr($utility->file, strlen(WORKSPACE)), '/.');
				
				$url->parameters()->debug = $path;
				$item = $this->appendJumpItem(
					$list, basename($utility->file),
					(string)$url, ($this->show == $path)
				);
				
				if (!empty($utility->tree)) {
					$this->appendJumpUtility($item, $utility->tree);
				}
			}
			
			$wrapper->appendChild($list);
			
			return $list;
		}
		
		protected function appendContent(DOMElement $wrapper) {
			$content = parent::appendContent($wrapper);
			$source = null; $type = null;
			
			if ($this->show == 'xml') {
				$this->appendSource($content, $this->input, 'xml');
			}
			
			else if ($this->show == 'result') {
				$this->appendSource($content, $this->output, 'xml');
			}
			
			else if ($this->show == 'params') {
				$this->appendParams($content);
			}
			
			else {
				$this->appendFile($content);
			}
		}
		
		protected function appendParams(DOMElement $wrapper) {
			$container = $this->document->createElement('div');
			$container->setAttribute('id', 'params');
			$table = $this->document->createElement('table');
			
			foreach ($this->params as $key => $value) {
				$row = $this->document->createElement('tr');
				
				$cell = $this->document->createElement('th');
				$cell->appendChild(
					$this->document->createTextNode("\${$key}")
				);
				$row->appendChild($cell);
				
				$cell = $this->document->createElement('td');
				$cell->appendChild(
					$this->document->createTextNode("'{$value}'")
				);
				$row->appendChild($cell);
				
				$table->appendChild($row);
			}
			
			$container->appendChild($table);
			$wrapper->appendChild($container);
			
			return $container;
		}
		
		protected function appendFile(DOMElement $wrapper) {
			$valid = false;
			
			if ($this->show == 'view') {
				$file = VIEWS . '/' . $this->view->templatePathname();
			}
			
			else {
				$file = WORKSPACE . '/' . $this->show;
			}
			
			if (realpath($file) !== false) {
				$file = realpath($file);
				
				// Make sure it's in the workspace:
				if (strpos($file, WORKSPACE) === 0) {
					$valid = true;
				}
			}
			
			if ($valid and is_file($file)) {
				$this->appendSource($wrapper, file_get_contents($file), 'xsl');
			}
			
			else {
				throw new Exception(sprintf(
					'Unable to display %s, file is not readable.',
					var_export($file, true)
				));
			}
		}
		
		protected function appendSource(DOMElement $wrapper, $source, $language = 'xml') {
			$bitter = new Bitter(true);
			$bitter->loadFormat('symphony');
			$bitter->loadLanguage($language);
			
			$inner = $this->document->createElement('div');
			$inner->setAttribute('id', 'source');
			
			$source = $bitter->process($source);
			
			$fragment = $this->document->createDocumentFragment();
			$fragment->appendXML($source);
			$inner->appendChild($fragment);
			
			$wrapper->appendChild($inner);
			
			return $inner;
		}
	}
	
	return 'DevKit_Debug';
	
?>