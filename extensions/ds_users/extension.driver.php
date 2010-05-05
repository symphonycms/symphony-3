<?php

	require_once('lib/usersdatasource.php');
	require_once(LIB . '/class.section.php');

	Class Extension_DS_Users extends Extension {
		public function about() {
			return array(
				'name'			=> 'Users',
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
				'description'	=> 'Create data sources from backend user data.'
			);
		}

		public function prepare(array $data=NULL, UsersDataSource $datasource=NULL) {

			if(is_null($datasource)) $datasource = new UsersDataSource;

			if(!is_null($data)){
				if(isset($data['about']['name'])) $datasource->about()->name = $data['about']['name'];
				if(isset($data['included-elements'])) $datasource->parameters()->{'included-elements'} = $data['included-elements'];

				$datasource->parameters()->filters = array();

				if(isset($data['filters']) && is_array($data['filters'])){
					foreach($data['filters'] as $handle => $value){
						$datasource->parameters()->filters[$handle] = $value;
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
			$middle = $layout->createColumn(Layout::LARGE);
			$right = $layout->createColumn(Layout::SMALL);

		//	Essentials --------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Essentials'), null, array(
				'class' => 'settings'
			));

			// Name:
			$label = Widget::Label(__('Name'));
			$input = Widget::Input('fields[about][name]', General::sanitize($datasource->about()->name));
			$label->appendChild($input);

			if (isset($errors->{'about::name'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'about::name'});
			}

			$fieldset->appendChild($label);
			$left->appendChild($fieldset);

		//	Filtering ---------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Filtering'), '<code>{$param}</code> or <code>Value</code>', array(
				'class' => 'settings'
			));

			// Filters
			$context = $page->createElement('div');
			$context->setAttribute('class', 'filters-duplicator context context-' . $section_handle);

			$templates = $page->createElement('ol');
			$templates->setAttribute('class', 'templates');

			$instances = $page->createElement('ol');
			$instances->setAttribute('class', 'instances');

			$sortableColumns = array(
				array(
					'name' => __('ID'),
					'column' => 'id',
					'value' => $datasource->parameters()->filters['id']
				),
				array(
					'name' => __('Username'),
					'column' => 'username',
					'value' => $datasource->parameters()->filters['username']
				),
				array(
					'name' => __('First Name'),
					'column' => 'first-name',
					'value' => $datasource->parameters()->filters['first-name']
				),
				array(
					'name' => __('Last Name'),
					'column' => 'last-name',
					'value' => $datasource->parameters()->filters['last-name']
				),
				array(
					'name' => __('Email Address'),
					'column' => 'email',
					'value' => $datasource->parameters()->filters['email']
				)
			);

			foreach($sortableColumns as $column) {
				$this->appendFilter($templates, $column);

				if(is_array($datasource->parameters()->filters) && array_key_exists($column['column'], $datasource->parameters()->filters)) {
					$this->appendFilter($instances, $column);
				}
			}

			$context->appendChild($templates);
			$context->appendChild($instances);
			$fieldset->appendChild($context);
			$middle->appendChild($fieldset);

		//	Output options ----------------------------------------------------

			$fieldset = Widget::Fieldset(__('Output Options'));

			$select = Widget::Select('fields[included-elements][]', array(
				array('username', in_array('username', $datasource->parameters()->{"included-elements"}), 'username'),
				array('name', in_array('name', $datasource->parameters()->{"included-elements"}), 'name'),
				array('email-address', in_array('email-address', $datasource->parameters()->{"included-elements"}), 'email-address'),
				array('language', in_array('language', $datasource->parameters()->{"included-elements"}), 'language'),
				array('authentication-token', in_array('authentication-token', $datasource->parameters()->{"included-elements"}), 'authentication-token'),
				array('default-section', in_array('default-section', $datasource->parameters()->{"included-elements"}), 'default-section'),
			));
			$select->setAttribute('class', 'filtered');
			$select->setAttribute('multiple', 'multiple');

			$label = Widget::Label(__('Included Elements'));
			$label->appendChild($select);

			$fieldset->appendChild($label);
			$right->appendChild($fieldset);

			$layout->appendTo($wrapper);
		}

		protected function appendFilter(SymphonyDOMElement $wrapper, $condition = array()) {
			$document = $wrapper->ownerDocument;

			$li = $document->createElement('li');

			$name = $document->createElement('span', $condition['name']);
			$name->setAttribute('class', 'name');
			$li->appendChild($name);

			$wrapper->appendChild($li);

			$label = Widget::Label(__('Value'));
			$input = Widget::Input('fields[filters][' . $condition['column'] . ']');

			if(isset($condition['value'])) {
				$input->setAttribute("value", $condition['value']);
			}

			$label->appendChild($input);

			$li->appendChild($label);

			$wrapper->appendChild($li);
		}
	}
