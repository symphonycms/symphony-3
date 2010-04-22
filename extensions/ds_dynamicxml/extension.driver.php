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

				if(isset($data['namespaces']) && is_array($data['namespaces'])){
					foreach($data['namespaces']['name'] as $index => $name){
						$datasource->parameters()->namespaces[$index] = array('uri' => $data['namespaces']['uri'][$index], 'name' => $name);
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

								if (in_array($name, $namespaces) or in_array($uri, $namespaces)) continue;

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
			$left->appendChild($fieldset);

		//	Source ------------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Source'));
			$label = Widget::Label(__('URL'));
			$label->appendChild(Widget::Input(
				'fields[url]', General::sanitize($datasource->parameters()->url)
			));

			if (isset($errors->url)) {
				$label = Widget::wrapFormElementWithError($label, $errors->url);
			}

			$fieldset->appendChild($label);

			$fragment = Symphony::Parent()->Page->createDocumentFragment();
			$fragment->appendXML(__('Use <code>{$param}</code> syntax to specify dynamic portions of the URL.'));

			$fieldset->appendChild(
				Symphony::Parent()->Page->createElement('p', $fragment, array(
					'class' => 'help'
				))
			);
			
			$right->appendChild($fieldset);
			
		//	Namespace Declarations

			$fieldset = Widget::Fieldset(__('Namespace Declarations'), Symphony::Parent()->Page->createElement('i', 'Optional'));

			$ol = Symphony::Parent()->Page->createElement('ol');
			$ol->setAttribute('class', 'filters-duplicator');

			if(is_array($datasource->parameters()->namespaces))	foreach($datasource->parameters()->namespaces as $index => $namespace) {

				$li = Symphony::Parent()->Page->createElement('li');
				$li->appendChild(Symphony::Parent()->Page->createElement('h4', 'Namespace'));

				$group = Symphony::Parent()->Page->createElement('div');
				$group->setAttribute('class', 'group');

				$label = Widget::Label(__('Name'));
				$label->appendChild(Widget::Input("fields[namespaces][name][{$index}]", General::sanitize($namespace['name'])));
				$group->appendChild($label);

				$label = Widget::Label(__('URI'));
				$label->appendChild(Widget::Input("fields[namespaces][uri][{$index}]", General::sanitize($namespace['uri'])));
				$group->appendChild($label);

				$li->appendChild($group);
				$ol->appendChild($li);

			}

			else {

				$li = Symphony::Parent()->Page->createElement('li');
				$li->setAttribute('class', 'template');
				$li->appendChild(Symphony::Parent()->Page->createElement('h4', __('Namespace')));

				$group = Symphony::Parent()->Page->createElement('div');
				$group->setAttribute('class', 'group');

				$label = Widget::Label(__('Name'));
				$label->appendChild(Widget::Input('fields[namespaces][name][]'));
				$group->appendChild($label);

				$label = Widget::Label(__('URI'));
				$label->appendChild(Widget::Input('fields[namespaces][uri][]'));
				$group->appendChild($label);

				$li->appendChild($group);
				$ol->appendChild($li);

			}

			$fieldset->appendChild($ol);

			$input = Widget::Input('fields[automatically-discover-namespaces]', 'yes', 'checkbox');
			if ($datasource->parameters()->{'automatically-discover-namespaces'} == 'yes') {
				$input->setAttribute('checked', 'checked');
			}

			$label = Widget::Label(__('Automatically discover namespaces'));
			$label->prependChild($input);
			$fieldset->appendChild($label);

			$help = Symphony::Parent()->Page->createElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('Search the source document for namespaces, any that it finds will be added to the declarations above.'));
			$fieldset->appendChild($help);
			
			$right->appendChild($fieldset);

			$fieldset = Widget::Fieldset(__('Included Elements'));
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

		//	Timeouts ------------------------------------------------------------
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
				new DOMText(__('Gateway timeout limit '))
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

			$right->appendChild($fieldset);
			
			$layout->appendTo($wrapper);
		}
	}
