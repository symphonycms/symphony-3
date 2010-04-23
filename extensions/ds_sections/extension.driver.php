<?php

	require_once 'lib/sectionsdatasource.php';

	Class Extension_DS_Sections extends Extension {
		public function about() {
			return array(
				'name'			=> 'Sections',
				'version'		=> '1.0.0',
				'release-date'	=> '2010-03-02',
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
				'description'	=> 'Create data sources from an XML string.'
			);
		}

		public function prepare(array $data=NULL, DataSource $datasource=NULL) {

			if(is_null($datasource)){
				$datasource = new SectionsDataSource;
			}

			if(!is_null($data)){
				if(isset($data['about']['name'])) $datasource->about()->name = $data['about']['name'];
				$datasource->parameters()->section = $data['section'];

				$datasource->parameters()->conditions = $datasource->parameters()->filter = array();

				if(isset($data['conditions']) && is_array($data['conditions'])){
					foreach($data['conditions']['parameter'] as $index => $parameter){
						$datasource->parameters()->conditions[$index] = array(
							'parameter' => $parameter,
							'logic' => $data['conditions']['logic'][$index],
							'action' => $data['conditions']['action'][$index]
						);
					}
				}

				if(isset($data['filter']) && is_array($data['filter'])){
					$datasource->parameters()->filter = $data['filter'];
				}

				$datasource->parameters()->{'redirect-404-on-empty'} = (isset($data['redirect-404-on-empty']) && $data['redirect-404-on-empty'] == 'yes');
				$datasource->parameters()->{'append-pagination'} = (isset($data['append-pagination']) && $data['append-pagination'] == 'yes');
				$datasource->parameters()->{'append-associated-entry-count'} = (isset($data['append-associated-entry-count']) && $data['append-associated-entry-count'] == 'yes');
				$datasource->parameters()->{'html-encode'} = (isset($data['html-encode']) && $data['html-encode'] == 'yes');

				if(isset($data['sort-field'])) $datasource->parameters()->{'sort-field'} = $data['sort-field'];
				if(isset($data['sort-order'])) $datasource->parameters()->{'sort-order'} = $data['sort-order'];
				if(isset($data['limit'])) $datasource->parameters()->{'limit'} = $data['limit'];
				if(isset($data['page'])) $datasource->parameters()->{'page'} = $data['page'];
				
				if(isset($data['included-elements'])){
					$datasource->parameters()->{'included-elements'} = (array)$data['included-elements'];
				}
				
				if(isset($data['parameter-output'])){
					$datasource->parameters()->{'parameter-output'} = (array)$data['parameter-output'];
				}

			}

			return $datasource;

		}

		public function view(Datasource $datasource, SymphonyDOMElement &$wrapper, MessageStack $errors) {
			$page = Administration::instance()->Page;
			$page->insertNodeIntoHead($page->createScriptElement(URL . '/extensions/ds_sections/assets/view.js'), 55533140);

			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$middle = $layout->createColumn(Layout::LARGE);

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

			// Section:
			$field_groups = $options = array();

			foreach (new SectionIterator as $section) {
				$field_groups[$section->handle] = array(
					'fields'	=> $section->fields,
					'section'	=> $section
				);

				$options[] = array($section->handle, ($datasource->parameters()->source == $section->handle), $section->name);
			}

			$label = Widget::Label(__('Section'));
			$label->appendChild(Widget::Select('fields[section]', $options, array('id' => 'context')));

			$fieldset->appendChild($label);
			$left->appendChild($fieldset);

		//	Sorting -----------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Sorting'));

			$container_sort_by = $page->createElement('div');
			$fieldset->appendChild($container_sort_by);

			$label = Widget::Label(__('Sort Order'));

			$options = array(
				array('asc', ('asc' == $datasource->parameters()->{'sort-order'}), __('Acending')),
				array('desc', ('desc' == $datasource->parameters()->{'sort-order'}), __('Descending')),
				array('random', ('random' == $datasource->parameters()->{'sort-order'}), __('Random')),
			);

			$label->appendChild(Widget::Select('fields[sort-order]', $options));
			$fieldset->appendChild($label);

			$left->appendChild($fieldset);

		//	Limiting ----------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Limiting'), '<code>{$param}</code> or <code>Value</code>');

			// Show a maximum of # results
			$label = Widget::Label(__('Limit results per page'));
			$input = Widget::Input('fields[limit]', $datasource->parameters()->page);

			$label->appendChild($input);

			if (isset($errors->limit)) {
				$label = Widget::wrapFormElementWithError($label, $errors->limit);
			}

			$fieldset->appendChild($label);

			// Show page # of results:
			$label = Widget::Label(__('Show page of results'));
			$input = Widget::Input('fields[page]', $datasource->parameters()->page);

			$label->appendChild($input);

			if (isset($errors->page)) {
				$label = Widget::wrapFormElementWithError($label, $errors->page);
			}

			$fieldset->appendChild($label);

			// Can redirect on empty:
			$fieldset->appendChild(Widget::Input('fields[redirect-404-on-empty]', 'no', 'hidden'));

			$label = Widget::Label(__('Redirect to 404 page when no results are found'));
			$input = Widget::Input('fields[redirect-404-on-empty]', 'yes', 'checkbox');

			if ($datasource->parameters()->{'redirect-404-on-empty'} == true) {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$fieldset->appendChild($label);
			$left->appendChild($fieldset);


		//	Conditions ---------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Conditions'), '<code>$param</code>');

			$container = $page->createElement('div');
			$container->setAttribute('class', 'conditions-duplicator');

			$templates = $page->createElement('ol');
			$templates->setAttribute('class', 'templates');

			$instances = $page->createElement('ol');
			$instances->setAttribute('class', 'instances');

			// Templates:
			$this->appendCondition($templates);

			// Instances:
			if(is_array($datasource->parameters()->conditions) && !empty($datasource->parameters()->conditions)){
				foreach($datasource->parameters()->conditions as $condition){
					$this->appendCondition($instances, $condition);
				}
			}

			$container->appendChild($templates);
			$container->appendChild($instances);
			$fieldset->appendChild($container);
			$middle->appendChild($fieldset);

		//	Filtering ---------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Filtering'), '<code>{$param}</code> or <code>Value</code>');

			$container_filter_results = $page->createElement('div');
			$fieldset->appendChild($container_filter_results);

		//	Redirect/404 ------------------------------------------------------
		/*
			$label = Widget::Label(__('Required URL Parameter <i>Optional</i>'));
			$label->appendChild(Widget::Input('fields[required_url_param]', $datasource->parameters()->required_url_param));
			$fieldset->appendChild($label);

			$p = new XMLElement('p', __('An empty result will be returned when this parameter does not have a value. Do not wrap the parameter with curly-braces.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);
		*/
			$middle->appendChild($fieldset);

		//	Output options ----------------------------------------------------

			$fieldset = Widget::Fieldset(__('Output Options'));

			$group = $page->createElement('div');
			$group->setAttribute('class', 'group');

			$container_parameter_output = $page->createElement('div');
			$group->appendChild($container_parameter_output);

			$container_xml_output = $page->createElement('div');
			$group->appendChild($container_xml_output);

			$fieldset->appendChild($group);

/*
			$fieldset->appendChild(Widget::Input('fields[append-pagination]', 'no', 'hidden'));

			$label = Widget::Label(__('Append pagination data'));
			$input = Widget::Input('fields[append-pagination]', 'yes', 'checkbox');

			if ($datasource->parameters()->{'append-pagination'} == true) {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$fieldset->appendChild($label);

			$fieldset->appendChild(Widget::Input('fields[append-associated-entry-count]', 'no', 'hidden'));

			$label = Widget::Label(__('Append entry count'));
			$input = Widget::Input('fields[append-associated-entry-count]', 'yes', 'checkbox');

			if ($datasource->parameters()->{'append-associated-entry-count'} == true) {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$fieldset->appendChild($label);

			$label = Widget::Label(__('HTML-encode text'));
			$input = Widget::Input('fields[html-encode]', 'yes', 'checkbox');

			if ($datasource->parameters()->{'html-encode'} == true) {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$fieldset->appendChild($label);
*/

			$middle->appendChild($fieldset);

			$layout->appendTo($wrapper);

		//	Build contexts ----------------------------------------------------

			foreach ($field_groups as $section_handle => $section_data) {
				$section = $section_data['section'];
				$section_active = ($datasource->parameters()->section == $section_handle);
				$filter_data = $datasource->parameters()->filter;

				// Filters:
				$context = $page->createElement('div');
				$context->setAttribute('class', 'filters-duplicator context context-' . $section_handle);

				$templates = $page->createElement('ol');
				$templates->setAttribute('class', 'templates');

				$instances = $page->createElement('ol');
				$instances->setAttribute('class', 'instances');

				if (isset($filter_data['id'])) {
					$li = $page->createElement('li');

					$name = $page->createElement('span', __('System ID'));
					$name->setAttribute('class', 'name');
					$li->appendChild($name);

					$label = Widget::Label(__('Value'));
					$label->appendChild(Widget::Input(
						"fields[filter][id]", General::sanitize($filter_data['id'])
					));
					$li->appendChild($label);
					$templates->appendChild($li);
				}

				$li = $page->createElement('li');

				$name = $page->createElement('span', __('System ID'));
				$name->setAttribute('class', 'name');
				$li->appendChild($name);

				$label = Widget::Label(__('Value'));
				$label->appendChild(Widget::Input('fields[filter][id]'));
				$li->appendChild($label);
				$templates->appendChild($li);

				if (is_array($section_data['fields']) && !empty($section_data['fields'])) {
					foreach ($section_data['fields'] as $input) {
						if (!$input->canFilter()) continue;

						$element_name = $input->{'element-name'};

						if (isset($filter_data[$element_name])) {
							$filter = $page->createElement('li');
							$input->displayDatasourceFilterPanel(
								$filter, $filter_data[$element_name],
								$errors->$element_name//, $section->get('id')
							);
							$instances->appendChild($filter);
						}

						$filter = $page->createElement('li');
						$input->displayDatasourceFilterPanel($filter, null, null); //, $section->get('id'));
						$templates->appendChild($filter);
					}
				}

				$context->appendChild($templates);
				$context->appendChild($instances);
				$container_filter_results->appendChild($context);

				// Select boxes:
				$sort_by_options = array(
					array('system:id', ($section_active and $datasource->parameters()->{'sort-field'} == 'system:id'), __('System ID')),
					array('system:date', ($section_active and $datasource->parameters()->{'sort-field'} == 'system:date'), __('System Date')),
				);
				$options_parameter_output = array(
					array(
						'system:id',
						($section_active and in_array('system:id', $datasource->parameters()->{'parameter-output'})),
						__('System ID')
					),
					array(
						'system:date',
						($section_active and in_array('system:date', $datasource->parameters()->{'parameter-output'})),
						__('System Date')
					),
					array(
						'system:user',
						($section_active and in_array('system:user', $datasource->parameters()->{'parameter-output'})),
						__('System User')
					)
				);
				$included_elements_options = array(
					// TODO: Determine what system fields will be included.
					array(
						'system:date',
						($section_active and in_array('system:date', $datasource->parameters()->{'included-elements'})),
						__('system:date')
					),
					array(
						'system:user',
						($section_active and in_array('system:user', $datasource->parameters()->{'included-elements'})),
						__('system:user')
					),
					array(
						'system:pagination',
						($section_active and in_array('system:pagination', $datasource->parameters()->{'included-elements'})),
						__('system:pagination')
					)
				);

				if (is_array($section_data['fields']) && !empty($section_data['fields'])) {
					foreach ($section_data['fields'] as $field) {
						$field_handle = $field->{'element-name'};
						$field_label = $field->label;
						$modes = $field->fetchIncludableElements();

						if ($field->isSortable()) {
							$sort_by_options[] = array(
								$field_handle,
								($section_active and $field_handle == $datasource->parameters()->{'sort-field'}),
								$field_label
							);
						}

						if ($field->allowDatasourceParamOutput()) {
							$options_parameter_output[] = array(
								$field_handle,
								($section_active and in_array($field_handle, $datasource->parameters()->{'parameter-output'})),
								$field_label
							);
						}

						if (is_array($modes)) foreach ($modes as $field_mode) {
							$included_elements_options[] = array(
								$field_mode,
								($section_active and in_array($field_mode, $datasource->parameters()->{'included-elements'})),
								$field_mode
							);
						}
					}
				}

				$label = Widget::Label(__('Sort By'));
				$label->setAttribute('class', 'context context-' . $section_handle);

				$label->appendChild(Widget::Select('fields[sort-field]', $sort_by_options, array('class' => 'filtered')));
				$container_sort_by->appendChild($label);

				$label = Widget::Label(__('Parameter Output'));
				$label->setAttribute('class', 'context context-' . $section_handle);

				$select = Widget::Select('fields[parameter-output][]', $options_parameter_output);
				$select->setAttribute('class', 'filtered');
				$select->setAttribute('multiple', 'multiple');

				$label->appendChild($select);

				$container_parameter_output->parentNode->insertBefore(
					$label, $container_parameter_output
				);

				$label = Widget::Label(__('Included XML Elements'));
				$label->setAttribute('class', 'context context-' . $section_handle);

				$select = Widget::Select('fields[included-elements][]', $included_elements_options);
				$select->setAttribute('class', 'filtered');
				$select->setAttribute('multiple', 'multiple');

				$label->appendChild($select);

				$container_xml_output->parentNode->insertBefore(
					$label, $container_xml_output
				);
			}

			// Cleanup placeholders:
			$container_parameter_output->remove();
			$container_xml_output->remove();
		}

		protected function appendCondition(SymphonyDOMElement $wrapper, $condition = array()) {
			$document = $wrapper->ownerDocument;

			$li = $document->createElement('li');

			$name = $document->createElement('span', __('Don\'t Execute When'));
			$name->setAttribute('class', 'name');
			$li->appendChild($name);

			$group = $document->createElement('div');
			$group->setAttribute('class', 'group double');

			// Parameter
			$label = $document->createElement('label', __('Parameter'));
			$label->appendChild(Widget::input('fields[conditions][parameter][]', $condition['parameter']));
			$group->appendChild($label);

			// Logic
			$label = $document->createElement('label', __('Logic'));
			$label->appendChild(Widget::select('fields[conditions][logic][]', array(
				array('empty', ($condition['logic'] == 'empty'), __('empty')),
				array('set', ($condition['logic'] == 'set'), __('set'))
			), array('class' => 'filtered')));
			$group->appendChild($label);

			// Action
			/*
			$label = $document->createElement('label', __('Action'));
			$label->appendChild(Widget::select('fields[conditions][action][]', array(
				//array('label' => 'Execution', 'options' => array(
					array('execute', ($condition['action'] == 'execute'), __('Execute')),
					array('do-not-execute', ($condition['action'] == 'do-not-execute'), __('Do not Execute')),
				//)),
				//array('label' => 'Redirect', 'options' => array(
				//	array('redirect:404', false, '404'),
				//	array('redirect:/about/me/', false, '/about/me/'),
				//)),
			), array('class' => 'filtered')));
			*/

			$group->appendChild($label);
			$li->appendChild($group);
			$wrapper->appendChild($li);
		}
	}

