<?php

	require_once('lib/usersdatasource.php');
	require_once(TOOLKIT . '/class.section.php');

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

				if(isset($data['filters']) && is_array($data['filters'])){
					foreach($data['filters'] as $handle => $value){
						$datasource->parameters()->filters[$handle] = $value;
					}
				}
			}

			return $datasource;
		}

		public function view(Datasource $datasource, SymphonyDOMElement &$wrapper, MessageStack $errors) {
			$page = Administration::instance()->Page;
			$page->insertNodeIntoHead($page->createScriptElement(URL . '/extensions/ds_sections/assets/view.js'), 55533140);
			
			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$right = $layout->createColumn(Layout::LARGE);

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

			$label = Widget::Label(__('Filter Users By:'));

			$ol = $page->createElement('ol');
			$ol->setAttribute('class', 'filters-duplicator');

			$this->appendFilter($ol, __('ID'), 'id', $datasource->parameters()->filters['id']);
			$this->appendFilter($ol, __('Username'), 'username', $datasource->parameters()->filters['username']);
			$this->appendFilter($ol, __('First Name'), 'first-name', $datasource->parameters()->filters['first-name']);
			$this->appendFilter($ol, __('Last Name'), 'last-name', $datasource->parameters()->filters['last-name']);
			$this->appendFilter($ol, __('Email Address'), 'email-address', $datasource->parameters()->filters['email-address']);

			$fieldset->appendChild($label);
			$fieldset->appendChild($ol);
			$right->appendChild($fieldset);

		//	Output options ----------------------------------------------------

			$fieldset = Widget::Fieldset(__('Output Options'));

			$select = Widget::Select('fields[included-elements][]', array(
				array('username', in_array('username', $datasource->parameters()->{"included-elements"}), 'username'),
				array('name', in_array('name', $datasource->parameters()->{"included-elements"}), 'name'),
				array('email-address', in_array('email-address', $datasource->parameters()->{"included-elements"}), 'email-address'),
				array('authentication-token', in_array('authentication-token', $datasource->parameters()->{"included-elements"}), 'authentication-token'),
				array('default-section', in_array('default-section', $datasource->parameters()->{"included-elements"}), 'default-section'),
				array('formatting-preference', in_array('formatting-preference', $datasource->parameters()->{"included-elements"}), 'formatting-preference')
			));
			$select->setAttribute('class', 'filtered');
			$select->setAttribute('multiple', 'multiple');

			$label = Widget::Label(__('Included Elements'));
			$label->appendChild($select);
			
			$fieldset->appendChild($label);
			$right->appendChild($fieldset);
			
			$layout->appendTo($wrapper);
		}

		protected function appendFilter(&$wrapper, $name, $handle, $value=NULL) {
			$page = Administration::instance()->Page;

			if (!is_null($value)) {
				$li = $page->createElement('li');
				$li->setAttribute('class', 'unique');
				$li->appendChild($page->createElement('h4', $name));
				$label = Widget::Label(__('Value'));
				$label->appendChild(Widget::Input(
					'fields[filters][' . $handle . ']',
					General::sanitize($value)
				));
				$li->appendChild($label);
			 	$wrapper->appendChild($li);
			}

			$li = $page->createElement('li');
			$li->setAttribute('class', 'unique template');
			$li->appendChild($page->createElement('h4', $name));
			$label = Widget::Label(__('Value'));
			$label->appendChild(Widget::Input('fields[filters][' . $handle . ']'));
			$li->appendChild($label);
		 	$wrapper->appendChild($li);
		}
	}
