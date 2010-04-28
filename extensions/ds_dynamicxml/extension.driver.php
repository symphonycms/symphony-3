<?php
	require_once('lib/dynamicxmldatasource.php');

	class Extension_DS_DynamicXML extends Extension {
		public function about() {
			return array(
				'name'			=> 'Dynamic XML',
				'version'		=> '1.0.0',
				'release-date'	=> '2010-02-26',
				'type'			=> array(
					'Data Source', 'Core'
				),
				'author'		=> array(
					'name'			=> 'Symphony Team',
					'website'		=> 'http://symphony-cms.com/',
					'email'			=> 'team@symphony-cms.com'
				),
				'provides'		=> array(
					'datasource_template'
				),
				'description'	=> 'Create data sources from XML fetched over HTTP or FTP.'
			);
		}

		public function prepare(array $data = null, DynamicXMLDataSource $datasource = null) {
			if(is_null($datasource)) $datasource = new DynamicXMLDataSource;

			if(!is_null($data)){
				if(isset($data['about']['name'])) $datasource->about()->name = $data['about']['name'];

				$datasource->parameters()->namespaces = array();

				if(is_array($data['namespaces']) && !empty($data['namespaces'])) {
					foreach($data['namespaces']['name'] as $index => $name) {
						if(!strlen(trim($name)) > 0) continue;

						$datasource->parameters()->namespaces[$index] = array(
							'name' => $name,
							'uri' => $data['namespaces']['uri'][$index]
						);
					}
				}

				if(isset($data['url'])) $datasource->parameters()->url = $data['url'];
				if(isset($data['xpath'])) $datasource->parameters()->xpath = $data['xpath'];
				if(isset($data['cache-lifetime'])) $datasource->parameters()->{'cache-lifetime'} = $data['cache-lifetime'];
				if(isset($data['timeout'])) $datasource->parameters()->{'timeout'} = $data['timeout'];

				// Namespaces ---------------------------------------------------------

				if(isset($data['automatically-discover-namespaces'])) {
					$datasource->parameters()->{'automatically-discover-namespaces'} = $data['automatically-discover-namespaces'];

					if ($data['automatically-discover-namespaces'] == 'yes') {
						$gateway = new Gateway();
						$gateway->init();
						$gateway->setopt('URL', $datasource->parameters()->url);
						$gateway->setopt('TIMEOUT', $datasource->parameters()->timeout);
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
								if (General::in_array_multi($name, $datasource->parameters()->namespaces)) continue;

								$namespaces[] = $name;
								$namespaces[] = $uri;

								$datasource->parameters()->namespaces[$index] = array(
									'name'	=> $name,
									'uri'	=> $uri
								);
							}
						}
					}
				}
			}

			return $datasource;
		}

		public function view(Datasource $datasource, SymphonyDOMElement &$wrapper, MessageStack $errors) {

			$page = $wrapper->ownerDocument;
			$page->insertNodeIntoHead($page->createScriptElement(URL . '/extensions/ds_sections/assets/view.js'), 55533140);

			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$right = $layout->createColumn(Layout::LARGE);

		//	Essentials --------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Essentials'));

			// Name:
			$label = Widget::Label(__('Name'));
			$input = Widget::Input('fields[about][name]', General::sanitize($datasource->about()->name));
			$label->appendChild($input);

			if (isset($errors->{'about::name'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'about::name'});
			}

			$fieldset->appendChild($label);

		//	Source ------------------------------------------------------------

			$label = Widget::Label(__('Source URL'));
			$label->appendChild(Widget::Input(
				'fields[url]', General::sanitize($datasource->parameters()->url)
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

			$input = Widget::Input('fields[cache-lifetime]', max(0, intval($datasource->parameters()->{'cache-lifetime'})));
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

			$input = Widget::Input('fields[timeout]', max(1, intval($datasource->parameters()->{'timeout'})));
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
			$label->appendChild(Widget::Input('fields[xpath]', General::sanitize($datasource->parameters()->xpath)));

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

			if(is_array($datasource->parameters()->namespaces))
				foreach($datasource->parameters()->namespaces as $index => $namespace) {

				$this->appendNamespace($instances, $namespace);
			}

			$container->appendChild($templates);
			$container->appendChild($instances);
			$fieldset->appendChild($container);

			$input = Widget::Input('fields[automatically-discover-namespaces]', 'yes', 'checkbox');
			if ($datasource->parameters()->{'automatically-discover-namespaces'} == 'yes') {
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
	}
