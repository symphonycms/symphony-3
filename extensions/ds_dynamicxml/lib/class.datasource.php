<?php

	require_once LIB . '/class.cache.php';
	require_once LIB . '/class.xslproc.php';
	require_once LIB . '/class.datasource.php';
	require_once LIB . '/class.gateway.php';

	Class DynamicXMLDataSource extends DataSource {
		public function __construct(){
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
				'timeout' => 6,
				'cache-lifetime' => 60,
				'automatically-discover-namespaces' => 'yes',
				'namespaces' => array(),
				'url' => NULL,
				'xpath' => '*',
				'root-element' => NULL,
			);
		}

		final public function getExtension(){
			return 'ds_dynamicxml';
		}

		public function getTemplate(){
			return EXTENSIONS . '/ds_dynamicxml/templates/datasource.php';
		}
		
	/*-----------------------------------------------------------------------*/
		
		public function prepare(array $data = null) {
			if(!is_null($data)){
				if(isset($data['about']['name'])) $this->about()->name = $data['about']['name'];

				$this->parameters()->namespaces = array();

				if(is_array($data['namespaces']) && !empty($data['namespaces'])) {
					foreach($data['namespaces']['name'] as $index => $name) {
						if(!strlen(trim($name)) > 0) continue;

						$this->parameters()->namespaces[$index] = array(
							'name' => $name,
							'uri' => $data['namespaces']['uri'][$index]
						);
					}
				}

				if(isset($data['url'])) $this->parameters()->url = $data['url'];
				if(isset($data['xpath'])) $this->parameters()->xpath = $data['xpath'];
				if(isset($data['cache-lifetime'])) $this->parameters()->{'cache-lifetime'} = $data['cache-lifetime'];
				if(isset($data['timeout'])) $this->parameters()->{'timeout'} = $data['timeout'];

				// Namespaces ---------------------------------------------------------

				if(isset($data['automatically-discover-namespaces'])) {
					$this->parameters()->{'automatically-discover-namespaces'} = $data['automatically-discover-namespaces'];

					if ($data['automatically-discover-namespaces'] == 'yes') {
						$gateway = new Gateway();
						$gateway->init();
						$gateway->setopt('URL', $this->parameters()->url);
						$gateway->setopt('TIMEOUT', $this->parameters()->timeout);
						$result = $gateway->exec();

						preg_match_all('/xmlns:([a-z][a-z-0-9\-]*)="([^\"]+)"/i', $result, $matches);

						if (isset($matches[2][0])) {
							$namespaces = array();

							if (!is_array($data['namespaces'])) {
								$data['namespaces'] = array();
							}

							foreach ($data['namespaces'] as $namespace) {
								$namespaces[] = $namespace['name'];
								$namespaces[] = $namespace['uri'];
							}

							foreach ($matches[2] as $index => $uri) {
								$name = $matches[1][$index];

								// Duplicate Namespaces
								if (in_array($name, $namespaces) or in_array($uri, $namespaces)) continue;
								if (General::in_array_multi($name, $this->parameters()->namespaces)) continue;

								$namespaces[] = $name;
								$namespaces[] = $uri;

								$this->parameters()->namespaces[$index] = array(
									'name'	=> $name,
									'uri'	=> $uri
								);
							}
						}
					}
				}
			}
		}
		
		public function view(SymphonyDOMElement $wrapper, MessageStack $errors) {
			$page = $wrapper->ownerDocument;
			$page->insertNodeIntoHead($page->createScriptElement(URL . '/extensions/ds_sections/assets/view.js'), 55533140);

			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$right = $layout->createColumn(Layout::LARGE);

		//	Essentials --------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Essentials'));

			// Name:
			$label = Widget::Label(__('Name'));
			$input = Widget::Input('fields[about][name]', General::sanitize($this->about()->name));
			$label->appendChild($input);

			if (isset($errors->{'about::name'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'about::name'});
			}

			$fieldset->appendChild($label);

		//	Source ------------------------------------------------------------

			$label = Widget::Label(__('Source URL'));
			$label->appendChild(Widget::Input(
				'fields[url]', General::sanitize($this->parameters()->url)
			));

			if (isset($errors->url)) {
				$label = Widget::wrapFormElementWithError($label, $errors->url);
			}

			$fieldset->appendChild($label);

			$fragment = $page->createDocumentFragment();
			$fragment->appendXML(__('Use <code>{$param}</code> syntax to specify dynamic portions of the URL.'));

			$fieldset->appendChild(
				$page->createElement('p', $fragment, array(
					'class' => 'help'
				))
			);

			$left->appendChild($fieldset);

		//	Timeouts ------------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Time Limits'));

			$input = Widget::Input('fields[cache-lifetime]', max(0, intval($this->parameters()->{'cache-lifetime'})));
			$input->setAttribute('size', 4);

			$fragment = Symphony::Parent()->Page->createDocumentFragment();
			$fragment->appendChild(
				new DOMText(__('Update cached result every '))
			);
			$fragment->appendChild($input);
			$fragment->appendChild(
				new DOMText(__(' minutes'))
			);
			$label = Widget::Label(null, $fragment);

			if(isset($errors->{'cache-lifetime'})){
				$label = Widget::wrapFormElementWithError($label, $errors->{'cache-lifetime'});
			}
			$fieldset->appendChild($label);

			$input = Widget::Input('fields[timeout]', max(1, intval($this->parameters()->{'timeout'})));
			$input->setAttribute('size', 4);

			$fragment = Symphony::Parent()->Page->createDocumentFragment();
			$fragment->appendChild(
				new DOMText(__('Set gateway timeout limit to '))
			);
			$fragment->appendChild($input);
			$fragment->appendChild(
				new DOMText(__(' seconds'))
			);
			$label = Widget::Label(null, $fragment);

			if(isset($errors->{'timeout'})){
				$label = Widget::wrapFormElementWithError($label, $errors->{'timeout'});
			}
			$fieldset->appendChild($label);


			$left->appendChild($fieldset);

		//	Included Elements

			$fieldset = Widget::Fieldset(__('XML Processing'));
			$label = Widget::Label(__('Included Elements'));
			$label->appendChild(Widget::Input('fields[xpath]', General::sanitize($this->parameters()->xpath)));

			if(isset($errors->xpath)){
				$label = Widget::wrapFormElementWithError($label, $errors->xpath);
			}

			$fieldset->appendChild($label);

			$help = Symphony::Parent()->Page->createElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('Use an XPath expression to select which elements from the source XML to include.'));
			$fieldset->appendChild($help);

			$right->appendChild($fieldset);

		//	Namespace Declarations

			$fieldset = Widget::Fieldset(__('Namespace Declarations'), $page->createElement('em', 'Optional'));

			$container = $page->createElement('div');
			$container->setAttribute('class', 'filters-duplicator');

			$templates = $page->createElement('ol');
			$templates->setAttribute('class', 'templates');

			$instances = $page->createElement('ol');
			$instances->setAttribute('class', 'instances');

			$this->appendNamespace($templates);

			if(is_array($this->parameters()->namespaces))
				foreach($this->parameters()->namespaces as $index => $namespace) {

				$this->appendNamespace($instances, $namespace);
			}

			$container->appendChild($templates);
			$container->appendChild($instances);
			$fieldset->appendChild($container);

			$input = Widget::Input('fields[automatically-discover-namespaces]', 'yes', 'checkbox');
			if ($this->parameters()->{'automatically-discover-namespaces'} == 'yes') {
				$input->setAttribute('checked', 'checked');
			}

			$label = Widget::Label(__('Automatically add discovered namespaces'));
			$label->prependChild($input);
			$fieldset->appendChild($label);

			$right->appendChild($fieldset);
			$layout->appendTo($wrapper);
		}

		protected function appendNamespace(SymphonyDOMElement $wrapper, $namespace = array()) {
			$document = $wrapper->ownerDocument;

			$li = $document->createElement('li');

			$name = $document->createElement('span', __('Namespace'));
			$name->setAttribute('class', 'name');
			$li->appendChild($name);

			$group = $document->createElement('div');
			$group->setAttribute('class', 'group double');

			// Name
			$label = Widget::Label(__('Name'));
			$label->appendChild(Widget::Input('fields[namespaces][name][]', $namespace['name']));
			$group->appendChild($label);

			// URI
			$label = Widget::Label(__('URI'));
			$label->appendChild(Widget::Input('fields[namespaces][uri][]', $namespace['uri']));
			$group->appendChild($label);

			$group->appendChild($label);
			$li->appendChild($group);
			$wrapper->appendChild($li);
		}

		public function save(MessageStack $errors){
			if(strlen(trim($this->parameters()->url)) == 0){
				$errors->append('url', __('This is a required field'));
			}

			if(strlen(trim($this->parameters()->xpath)) == 0){
				$errors->append('xpath', __('This is a required field'));
			}

			//	Cache Lifetime
			if(!is_numeric($this->parameters()->{'cache-lifetime'})){
				$errors->append('cache-lifetime', __('Must be a valid number'));
			}

			elseif($this->parameters()->{'cache-lifetime'} <= 0){
				$errors->append('cache-lifetime', __('Must be greater than zero'));
			}

			else{
				$this->parameters()->{'cache-lifetime'} = (int)$this->parameters()->{'cache-lifetime'};
			}

			//	Timeout
			if(!is_numeric($this->parameters()->{'timeout'})){
				$errors->append('timeout', __('Must be a valid number'));
			}

			elseif($this->parameters()->{'timeout'} <= 0){
				$errors->append('timeout', __('Must be greater than zero'));
			}

			else{
				$this->parameters()->{'timeout'} = (int)$this->parameters()->{'timeout'};
			}

			return parent::save($errors);
		}
		
	/*-----------------------------------------------------------------------*/

		public function render(Register $ParameterOutput){
			$result = null;
			$doc = new XMLDocument;

			if(isset($this->parameters()->url)){
				$this->parameters()->url = self::replaceParametersInString($this->parameters()->url, $ParameterOutput);
			}
			
			if(isset($this->parameters()->xpath)){
				$this->parameters()->xpath = self::replaceParametersInString($this->parameters()->xpath, $ParameterOutput);
			}
			
			$cache_id = md5($this->parameters()->url . serialize($this->parameters()->namespaces) . $this->parameters()->xpath);

			$cache = Cache::instance();
			$cachedData = $cache->read($cache_id);

			$writeToCache = false;
			$force_empty_result = false;
			$valid = true;
			$result = NULL;
			$creation = DateTimeObj::get('c');

			if(isset($this->parameters()->timeout)){
				$timeout = (int)max(1, $this->parameters()->timeout);
			}

			if((!is_array($cachedData) || empty($cachedData)) || (time() - $cachedData['creation']) > ($this->parameters()->{'cache-timeout'} * 60)){
				if(Mutex::acquire($cache_id, $timeout, TMP)){

					$start = precision_timer();

					$ch = new Gateway;

					$ch->init();
					$ch->setopt('URL', $this->parameters()->url);
					$ch->setopt('TIMEOUT', $this->parameters()->timeout);
					$xml = $ch->exec();
					$writeToCache = true;

					$end = precision_timer('STOP', $start);

					$info = $ch->getInfoLast();

					Mutex::release($cache_id, TMP);

					$xml = trim($xml);

					if((int)$info['http_code'] != 200 || !preg_match('/(xml|plain|text)/i', $info['content_type'])){

						$writeToCache = false;

						if(is_array($cachedData) && !empty($cachedData)){
							$xml = trim($cachedData['data']);
							$valid = false;
							$creation = DateTimeObj::get('c', $cachedData['creation']);
						}

						else{
							$result = $doc->createElement($this->parameters()->{'root-element'});
							$result->setAttribute('valid', 'false');

							if($end > $timeout){
								$result->appendChild(
									$doc->createElement('error',
										sprintf('Request timed out. %d second limit reached.', $timeout)
									)
								);
							}
							else{
								$result->appendChild(
									$doc->createElement('error',
										sprintf('Status code %d was returned. Content-type: %s', $info['http_code'], $info['content_type'])
									)
								);
							}

							return $result;
						}
					}

					elseif(strlen($xml) > 0 && !General::validateXML($xml, $errors)){

						$writeToCache = false;

						if(is_array($cachedData) && !empty($cachedData)){
							$xml = trim($cachedData['data']);
							$valid = false;
							$creation = DateTimeObj::get('c', $cachedData['creation']);
						}

						else{
							$result = $doc->createElement(
								$this->parameters()->{'root-element'},
								$doc->createElement('error', __('XML returned is invalid.')),
								array('valid' => 'false')
							);

							return $result;
						}

					}

					elseif(strlen($xml) == 0){
						$force_empty_result = true;
					}

				}

				elseif(is_array($cachedData) && !empty($cachedData)){
					$xml = trim($cachedData['data']);
					$valid = false;
					$creation = DateTimeObj::get('c', $cachedData['creation']);
					if(empty($xml)) $force_empty_result = true;
				}

				else $force_empty_result = true;

			}

			else{
				$xml = trim($cachedData['data']);
				$creation = DateTimeObj::get('c', $cachedData['creation']);
			}

			if(!$force_empty_result) {
				$result = new XMLDocument;
				$root =	$result->createElement($this->parameters()->{'root-element'});

				//XPath Approach, saves Transforming the Document.
				$xDom = new XMLDocument;
				$xDom->loadXML($xml);

				if($xDom->hasErrors()) {

					$root->setAttribute('valid', 'false');
					$root->appendChild(
						$result->createElement('error', __('XML returned is invalid.'))
					);

					$messages = $result->createElement('messages');

					foreach($xDom->getErrors() as $e){
						if(strlen(trim($e->message)) == 0) continue;
						$messages->appendChild(
							$result->createElement('item', General::sanitize($e->message))
						);
					}
					$root->appendChild($messages);

				}

				else {
					if($writeToCache){
						$cache->write($cache_id, $xml);
					}

					$xpath = new DOMXPath($xDom);

					## Namespaces
					if(is_array($this->parameters()->namespaces) && !empty($this->parameters()->namespaces)){
						foreach($this->parameters()->namespaces as $index => $namespace) {
							$xpath->registerNamespace($namespace['name'], $namespace['uri']);
						}
					}

					$xpath_list = $xpath->query($this->parameters()->xpath);

					foreach($xpath_list as $node) {
						if($node instanceof XMLDocument) {
							$root->appendChild(
								$result->importNode($node->documentElement, true)
							);
						}

						else {
							$root->appendChild(
								$result->importNode($node, true)
							);
						}

					}

					$root->setAttribute('status', ($valid === true ? 'fresh' : 'stale'));
					$root->setAttribute('creation', $creation);
				}
			}

			if(!$root->hasChildNodes() || $force_empty_result) $this->emptyXMLSet($root);

			$result->appendChild($root);

			return $result;
		}

		public function prepareSourceColumnValue() {

			return Widget::TableData(
				Widget::Anchor(@parse_url($this->parameters()->url, PHP_URL_HOST), $this->parameters()->url, array(
					'title' => $this->parameters()->url,
					'rel' => 'external'
				))
			);

		}
	}
