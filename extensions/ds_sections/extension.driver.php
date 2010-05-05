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

				$datasource->parameters()->conditions = $datasource->parameters()->filters = array();

				if(isset($data['conditions']) && is_array($data['conditions'])){
					foreach($data['conditions']['parameter'] as $index => $parameter){
						$datasource->parameters()->conditions[$index] = array(
							'parameter' => $parameter,
							'logic' => $data['conditions']['logic'][$index]
						);
					}
				}

				if(isset($data['filters']) && is_array($data['filters'])){
					$datasource->parameters()->filters = $data['filters'];
				}

				$datasource->parameters()->{'redirect-404-on-empty'} = (isset($data['redirect-404-on-empty']) && $data['redirect-404-on-empty'] == 'yes');
				$datasource->parameters()->{'append-pagination'} = (isset($data['append-pagination']) && $data['append-pagination'] == 'yes');
				$datasource->parameters()->{'append-sorting'} = (isset($data['append-sorting']) && $data['append-sorting'] == 'yes');
				
				/*
				TODO: Are these going to be used?
				$datasource->parameters()->{'append-associated-entry-count'} = (isset($data['append-associated-entry-count']) && $data['append-associated-entry-count'] == 'yes');
				$datasource->parameters()->{'html-encode'} = (isset($data['html-encode']) && $data['html-encode'] == 'yes');
				*/

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
			$page->insertNodeIntoHead($page->createScriptElement(URL . '/extensions/ds_sections/assets/view.js'));

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

			// Section:
			$field_groups = $options = array();

			foreach (new SectionIterator as $section) {
				$field_groups[$section->handle] = array(
					'fields'	=> $section->fields,
					'section'	=> $section
				);

				$options[] = array($section->handle, ($datasource->parameters()->section == $section->handle), $section->name);
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
			$input = Widget::Input('fields[limit]', $datasource->parameters()->limit);

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
			$right->appendChild($fieldset);

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
			$right->appendChild($fieldset);

		//	Output options ----------------------------------------------------

			$fieldset = Widget::Fieldset(__('Output Options'));
			
			//$container_parameter_output = $page->createElement('div');
			$context_content = $page->createElement('div');
			$fieldset->appendChild($context_content);
			
			$fieldset->appendChild(Widget::Input('fields[append-pagination]', 'no', 'hidden'));

			$label = Widget::Label(__('Append pagination data'));
			$input = Widget::Input('fields[append-pagination]', 'yes', 'checkbox');

			if ($datasource->parameters()->{'append-pagination'} == true) {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$fieldset->appendChild($label);
			
			$fieldset->appendChild(Widget::Input('fields[append-sorting]', 'no', 'hidden'));

			$label = Widget::Label(__('Append sorting data'));
			$input = Widget::Input('fields[append-sorting]', 'yes', 'checkbox');

			if ($datasource->parameters()->{'append-sorting'} == true) {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$fieldset->appendChild($label);

/*
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

			$right->appendChild($fieldset);

			$layout->appendTo($wrapper);

		//	Build contexts ----------------------------------------------------

			foreach ($field_groups as $section_handle => $section_data) {
				$section = $section_data['section'];
				$section_active = ($datasource->parameters()->section == $section_handle);
				$filter_data = $datasource->parameters()->filters;
				$fields = array();

				// Filters:
				$context = $page->createElement('div');
				$context->setAttribute('class', 'filters-duplicator context context-' . $section_handle);

				$templates = $page->createElement('ol');
				$templates->setAttribute('class', 'templates');

				$instances = $page->createElement('ol');
				$instances->setAttribute('class', 'instances');
				
				// System ID template:
				$item = $page->createElement('li');

				$name = $page->createElement('span', __('System ID'));
				$name->setAttribute('class', 'name');
				$item->appendChild($name);
				
				$label = Widget::Label(__('Type'));
				$label->appendChild(Widget::Select(
					'type',
					array(
						array('is', false, 'Is'),
						array('is-not', false, 'Is not')
					)
				));
				$item->appendChild($label);
				
				$label = Widget::Label(__('Value'));
				$label->appendChild(Widget::Input('value'));
				$label->appendChild(Widget::Input(
					'element-name', 'system:id', 'hidden'
				));
				
				$item->appendChild($label);
				$templates->appendChild($item);
				
				// Field templates:
				if (is_array($section_data['fields']) && !empty($section_data['fields'])) {
					foreach ($section_data['fields'] as $field) {
						if (!$field->canFilter()) continue;
						
						$element_name = $field->{'element-name'};
						$fields[$element_name] = $field;
						
						$item = $page->createElement('li');
						$field->displayDatasourceFilterPanel(
							$item, null, null
						);
						$templates->appendChild($item);
					}
				}
				
				// Field isntances:
				if (is_array($filter_data) && !empty($filter_data)) {
					foreach ($filter_data as $filter) {
						if (isset($fields[$filter['element-name']])) {
							$element_name = $filter['element-name'];
							$field = $fields[$element_name];
							$item = $page->createElement('li');
							
							$field->displayDatasourceFilterPanel(
								$item, $filter, $errors->$element_name
							);
							$instances->appendChild($item);
						}
						
						else if ($filter['element-name'] == 'system:id') {
							$item = $page->createElement('li');
		
							$name = $page->createElement('span', __('System ID'));
							$name->setAttribute('class', 'name');
							$item->appendChild($name);
							
							$label = Widget::Label(__('Type'));
							$label->appendChild(Widget::Select(
								'type',
								array(
									array('is', false, 'Is'),
									array('is-not', $filter['type'] == 'is-not', 'Is not')
								)
							));
							$item->appendChild($label);
		
							$label = Widget::Label(__('Value'));
							$label->appendChild(Widget::Input(
								"value", $filter['value']
							));
							
							$label->appendChild(Widget::Input(
								'element-name', 'system:id', 'hidden'
							));
							$item->appendChild($label);
							
							$instances->appendChild($item);
						}
						
						// TODO: What about creation, modified date and author?
					}
				}

				$context->appendChild($templates);
				$context->appendChild($instances);
				$container_filter_results->appendChild($context);

				// Select boxes:
				$sort_by_options = array(
					array('system:id', ($section_active and $datasource->parameters()->{'sort-field'} == 'system:id'), __('System ID')),
					array('system:creation-date', ($section_active and $datasource->parameters()->{'sort-field'} == 'system:creation-date'), __('System Creation Date')),
					array('system:modification-date', ($section_active and $datasource->parameters()->{'sort-field'} == 'system:modification-date'), __('System Modification Date')),
				);
				$options_parameter_output = array(
					array(
						'system:id',
						($section_active and in_array('system:id', $datasource->parameters()->{'parameter-output'})),
						__('System ID')
					),
					array(
						'system:creation-date',
						($section_active and in_array('system:creation-date', $datasource->parameters()->{'parameter-output'})),
						__('System Creation Date')
					),
					array(
						'system:modification-date',
						($section_active and in_array('system:modification-date', $datasource->parameters()->{'parameter-output'})),
						__('System Modification Date')
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
						'system:creation-date',
						($section_active and in_array('system:creation-date', $datasource->parameters()->{'included-elements'})),
						__('system:creation-date')
					),
					array(
						'system:modification-date',
						($section_active and in_array('system:modification-date', $datasource->parameters()->{'included-elements'})),
						__('system:modification-date')
					),
					array(
						'system:user',
						($section_active and in_array('system:user', $datasource->parameters()->{'included-elements'})),
						__('system:user')
					),
					/*array(
						'system:pagination',
						($section_active and in_array('system:pagination', $datasource->parameters()->{'included-elements'})),
						__('system:pagination')
					)*/
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

				$param_label = Widget::Label(__('Parameter Output'));

				$select = Widget::Select('fields[parameter-output][]', $options_parameter_output);
				$select->setAttribute('class', 'filtered');
				$select->setAttribute('multiple', 'multiple');

				$param_label->appendChild($select);

				$include_label = Widget::Label(__('Included XML Elements'));

				$select = Widget::Select('fields[included-elements][]', $included_elements_options);
				$select->setAttribute('class', 'filtered');
				$select->setAttribute('multiple', 'multiple');

				$include_label->appendChild($select);
				
				$group = Widget::Group($param_label, $include_label);
				$group->setAttribute('class', 'group context context-' . $section_handle);
				
				$context_content->parentNode->insertBefore($group, $context_content);
			}
			
			$context_content->remove();
		}

		protected function appendCondition(SymphonyDOMElement $wrapper, $condition = array()) {
			$document = $wrapper->ownerDocument;
			
			if (!isset($condition['parameter'])) {
				$condition['parameter'] = null;
			}
			
			if (!isset($condition['logic'])) {
				$condition['logic'] = 'empty';
			}
			
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
				array('empty', ($condition['logic'] == 'empty'), __('is empty')),
				array('set', ($condition['logic'] == 'set'), __('is set'))
			), array('class' => 'filtered')));
			$group->appendChild($label);

			$group->appendChild($label);
			$li->appendChild($group);
			$wrapper->appendChild($li);
		}
	}

