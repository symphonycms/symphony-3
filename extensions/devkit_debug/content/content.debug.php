<?php
	
	if (!defined('BITTER_LANGUAGE_PATH')) {
		define('BITTER_LANGUAGE_PATH', EXTENSIONS . '/debugdevkit/lib/bitter/languages');
	}
	
	if (!defined('BITTER_FORMAT_PATH')) {
		define('BITTER_FORMAT_PATH', EXTENSIONS . '/debugdevkit/lib/bitter/formats');
	}
	
	if (!defined('BITTER_CACHE_PATH')) {
		define('BITTER_CACHE_PATH', EXTENSIONS . '/debugdevkit/lib/bitter/caches');
	}
	
	require_once(LIB . '/class.devkit.php');
	require_once(EXTENSIONS . '/debugdevkit/lib/bitter/bitter.php');
	
	// Create cache folter:
	if (!is_dir(BITTER_CACHE_PATH)) {
		General::realiseDirectory(BITTER_CACHE_PATH);
	}
	
	class Content_DebugDevKit_Debug extends DevKit {
		protected $_view = '';
		protected $_xsl = '';
		protected $_full_utility_list = '';
		
		public function __construct(){
			parent::__construct();
			
			$this->_title = __('Debug');
			$this->_query_string = parent::__buildQueryString(array('symphony-page', 'debug'));
			
			if (!empty($this->_query_string)) {
				$this->_query_string = '&amp;' . General::sanitize($this->_query_string);
			}
		}
		
		public function build() {
			$this->_view = (strlen(trim($_GET['debug'])) == 0 ? 'xml' : $_GET['debug']);
			$this->_xsl = @file_get_contents($this->_pagedata['filelocation']);
			
			return parent::build();
		}
		
		protected function buildJump($wrapper) {
			$list = new XMLElement('ul');
			
			$list->appendChild($this->buildJumpItem(
				__('Params'),
				'?debug=params' . $this->_query_string,
				($this->_view == 'params')
			));
			
			$list->appendChild($this->buildJumpItem(
				__('XML'),
				'?debug=xml' . $this->_query_string,
				($this->_view == 'xml')
			));
			
			$filename = basename($this->_pagedata['filelocation']);
			
			$item = $this->buildJumpItem(
				$filename,
				"?debug={$filename}" . $this->_query_string,
				($this->_view == $filename)
			);
			
			$utilities = $this->__buildUtilityList($this->__findUtilitiesInXSL($this->_xsl), 1, $this->_view);
			
			if (is_object($utilities)) {
				$item->appendChild($utilities);
			}
			
			$list->appendChild($item);
			
			$list->appendChild($this->buildJumpItem(
				__('Result'),
				'?debug=result' . $this->_query_string,
				($this->_view == 'result')
			));
			
			$wrapper->appendChild($list);
		}
		
		public function buildContent($wrapper) {
			$this->addStylesheetToHead(URL . '/extensions/debugdevkit/assets/devkit.css', 'screen', 9126343);
			$this->addScriptToHead(URL . '/symphony/assets/jquery.js', 9126342);
			$this->addScriptToHead(URL . '/extensions/debugdevkit/assets/jquery.scrollto.js', 9126344);
			$this->addScriptToHead(URL . '/extensions/debugdevkit/assets/jquery.debug.js', 9126343);
			$this->addScriptToHead(URL . '/extensions/debugdevkit/assets/devkit.js', 9126344);
			
			if ($this->_view == 'params') {
				$wrapper->appendChild($this->__buildParams($this->_param));
				
			} else if ($this->_view == 'xml') {
				$this->appendSource($wrapper, $this->_xml, 'xml');
				
			} else if ($this->_view == 'result') {
				$this->appendSource($wrapper, $this->_output, 'xml');
				
			} else {
				if ($_GET['debug'] == basename($this->_pagedata['filelocation'])) {
					$this->appendSource($wrapper, $this->_xsl, 'xsl');
					
				} else if ($_GET['debug']{0} == 'u') {
					if (is_array($this->_full_utility_list) && !empty($this->_full_utility_list)) {
						foreach ($this->_full_utility_list as $u) {
							if ($_GET['debug'] != 'u-'.basename($u)) continue;
							
							$this->appendSource($wrapper, @file_get_contents(UTILITIES . '/' . basename($u)), 'xsl');
							break;
						}
					}
				}
			}
		}
		
		protected function appendSource($wrapper, $source, $language = 'xml') {
			$bitter = new Bitter(false);
			$bitter->loadFormat('symphony');
			$bitter->loadLanguage($language);
			
			$inner = new XMLElement(
				'div', $bitter->process($source)
			);
			$inner->setAttribute('id', 'source');
			
			$wrapper->appendChild($inner);
		}
		
		protected function __buildParams($params) {
			if (!is_array($params) || empty($params)) return;
			
			$wrapper = new XMLElement('div');
			$wrapper->setAttribute('id', 'params');
			$table = new XMLElement('table');
			
			foreach ($params as $key => $value) {
				$row = new XMLElement('tr');
				$row->appendChild(new XMLElement('th', "\${$key}"));
				$row->appendChild(new XMLElement('td', "'{$value}'"));
				$table->appendChild($row);
			}
			
			$wrapper->appendChild($table);
			
			return $wrapper;
		}
		
		protected function __buildUtilityList($utilities, $level=1, $view = null) {
			if (!is_array($utilities) || empty($utilities)) return;
			
			$list = new XMLElement('ul');
			
			foreach ($utilities as $u) {
				$filename = basename($u);
				$item = $this->buildJumpItem(
					$filename,
					"?debug=u-{$filename}" . $this->_query_string,
					($view == "u-{$filename}"),
					"?debug-edit={$u}" . $this->_query_string
				);
				
				$child_utilities = $this->__findUtilitiesInXSL(
					@file_get_contents(UTILITIES . '/' . $filename)
				);
				
				if (is_array($child_utilities) && !empty($child_utilities)) {
					$item->appendChild($this->__buildUtilityList($child_utilities, $level + 1, $view));
				}
				
				$list->appendChild($item);
			}
			
			return $list;
		}
		
		protected function __findUtilitiesInXSL($xsl) {
			if ($xsl == '') return;
			
			$utilities = null;
			
			if (preg_match_all('/<xsl:(import|include)\s*href="([^"]*)/i', $xsl, $matches)) {
				$utilities = $matches[2];
			}
			
			if (!is_array($this->_full_utility_list)) {
				$this->_full_utility_list = array();
			}
			
			if (is_array($utilities) && !empty($utilities)) {
				$this->_full_utility_list = array_merge($utilities, $this->_full_utility_list);
			}
			
			return $utilities;
		}
	}
	
?>