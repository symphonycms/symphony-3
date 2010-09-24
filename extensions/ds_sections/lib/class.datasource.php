<?php

	require_once LIB . '/class.datasource.php';
	require_once LIB . '/class.entry.php';
	require_once LIB . '/class.duplicator.php';

	Class SectionsDataSource extends DataSource {
		public function __construct(){
			// Set Default Values
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
				'root-element' => NULL,
				'limit' => 20,
				'page' => 1,
				'section' => NULL,
				'conditions' => array(),
				'filters' => array(),
				'redirect-404-on-empty' => false,
				'append-pagination' => false,
				'append-sorting' => false,
				'sort-field' => 'system:id',
				'sort-order' => 'desc',
				'included-elements' => array(),
				'parameter-output' => array(),
				'dependencies' => array(),
			);
		}

		public function getType() {
			return 'SectionsDataSource';
		}

		public function getTemplate(){
			return EXTENSIONS . '/ds_sections/templates/template.datasource.php';
		}

		public function prepareSourceColumnValue(){
			$section = Section::loadFromHandle($this->_parameters->section);

			if ($section instanceof Section) {
				return Widget::TableData(
					Widget::Anchor($section->name, ADMIN_URL . '/blueprints/sections/edit/' . $section->handle . '/', array(
						'title' => $section->handle
					))
				);
			}

			else {
				return Widget::TableData(__('None'), array(
					'class' => 'inactive'
				));
			}
		}

	/*-----------------------------------------------------------------------*/

		public function prepare(array $data=NULL) {
			if(!is_null($data)){
				if(isset($data['about']['name'])) $this->about()->name = $data['about']['name'];
				$this->parameters()->section = $data['section'];

				$this->parameters()->conditions = $this->parameters()->filters = array();

				if(isset($data['conditions']) && is_array($data['conditions'])){
					foreach($data['conditions']['parameter'] as $index => $parameter){
						$this->parameters()->conditions[$index] = array(
							'parameter' => $parameter,
							'logic' => $data['conditions']['logic'][$index]
						);
					}
				}

				if(isset($data['filters']) && is_array($data['filters'])){
					$this->parameters()->filters = $data['filters'];
				}

				$this->parameters()->{'redirect-404-on-empty'} = (isset($data['redirect-404-on-empty']) && $data['redirect-404-on-empty'] == 'yes');
				$this->parameters()->{'append-pagination'} = (isset($data['append-pagination']) && $data['append-pagination'] == 'yes');
				$this->parameters()->{'append-sorting'} = (isset($data['append-sorting']) && $data['append-sorting'] == 'yes');

				/*
				TODO: Are these going to be used?
				$this->parameters()->{'append-associated-entry-count'} = (isset($data['append-associated-entry-count']) && $data['append-associated-entry-count'] == 'yes');
				$this->parameters()->{'html-encode'} = (isset($data['html-encode']) && $data['html-encode'] == 'yes');
				*/

				if(isset($data['sort-field'])) $this->parameters()->{'sort-field'} = $data['sort-field'];
				if(isset($data['sort-order'])) $this->parameters()->{'sort-order'} = $data['sort-order'];
				if(isset($data['limit'])) $this->parameters()->{'limit'} = $data['limit'];
				if(isset($data['page'])) $this->parameters()->{'page'} = $data['page'];

				if(isset($data['included-elements'])){
					$this->parameters()->{'included-elements'} = (array)$data['included-elements'];
				}

				if(isset($data['parameter-output'])){
					$this->parameters()->{'parameter-output'} = (array)$data['parameter-output'];
				}

				// Calculate dependencies
				$this->parameters()->{'dependencies'} = array();
				if(preg_match_all('/\$ds-([^\s\/?*:;{},\\\\"\'\.]+)/i', serialize($this->parameters()), $matches) > 0){
					$this->parameters()->{'dependencies'} = array_unique($matches[1]);
				}

			}
		}

		public function view(SymphonyDOMElement $wrapper, MessageStack $errors) {
			$page = Administration::instance()->Page;
			$page->insertNodeIntoHead($page->createScriptElement(URL . '/extensions/ds_sections/assets/view.js'));

			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$right = $layout->createColumn(Layout::SMALL);

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

			// Section:
			$field_groups = $options = array();

			foreach (new SectionIterator as $section) {
				$field_groups[$section->handle] = array(
					'fields'	=> $section->fields,
					'section'	=> $section
				);

				$options[] = array($section->handle, ($this->parameters()->section == $section->handle), $section->name);
			}

			$label = Widget::Label(__('Section'));
			$label->appendChild(Widget::Select('fields[section]', $options, array('id' => 'context')));

			$fieldset->appendChild($label);
			$left->appendChild($fieldset);


		//	Conditions ---------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Conditions'), '<code>$param</code>');

			$duplicator = new Duplicator(__('Add Condition'));
			//$duplicator->setAttribute('class', 'conditions-duplicator');

			// Templates:
			$this->appendCondition($duplicator);

			// Instances:
			if(is_array($this->parameters()->conditions) && !empty($this->parameters()->conditions)){
				foreach($this->parameters()->conditions as $condition){
					$this->appendCondition($duplicator, $condition);
				}
			}

			$duplicator->appendTo($fieldset);
			$left->appendChild($fieldset);

		//	Filtering ---------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Filtering'), '<code>{$param}</code> or <code>Value</code>');

			$container_filter_results = $page->createElement('div');
			$fieldset->appendChild($container_filter_results);

		//	Redirect/404 ------------------------------------------------------
		/*
			$label = Widget::Label(__('Required URL Parameter <i>Optional</i>'));
			$label->appendChild(Widget::Input('fields[required_url_param]', $this->parameters()->required_url_param));
			$fieldset->appendChild($label);

			$p = new XMLElement('p', __('An empty result will be returned when this parameter does not have a value. Do not wrap the parameter with curly-braces.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);
		*/
			$left->appendChild($fieldset);

		//	Sorting -----------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Sorting'));

			$container_sort_by = $page->createElement('div');
			$fieldset->appendChild($container_sort_by);

			$label = Widget::Label(__('Sort Order'));

			$options = array(
				array('asc', ('asc' == $this->parameters()->{'sort-order'}), __('Acending')),
				array('desc', ('desc' == $this->parameters()->{'sort-order'}), __('Descending')),
				array('random', ('random' == $this->parameters()->{'sort-order'}), __('Random')),
			);

			$label->appendChild(Widget::Select('fields[sort-order]', $options));
			$fieldset->appendChild($label);

			$right->appendChild($fieldset);

		//	Limiting ----------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Limiting'), '<code>{$param}</code> or <code>Value</code>');

			// Show a maximum of # results
			$label = Widget::Label(__('Limit results per page'));
			$input = Widget::Input('fields[limit]', $this->parameters()->limit);

			$label->appendChild($input);

			if (isset($errors->limit)) {
				$label = Widget::wrapFormElementWithError($label, $errors->limit);
			}

			$fieldset->appendChild($label);

			// Show page # of results:
			$label = Widget::Label(__('Show page of results'));
			$input = Widget::Input('fields[page]', $this->parameters()->page);

			$label->appendChild($input);

			if (isset($errors->page)) {
				$label = Widget::wrapFormElementWithError($label, $errors->page);
			}

			$fieldset->appendChild($label);

			// Can redirect on empty:
			$fieldset->appendChild(Widget::Input('fields[redirect-404-on-empty]', 'no', 'hidden'));

			$label = Widget::Label(__('Redirect to 404 page when no results are found'));
			$input = Widget::Input('fields[redirect-404-on-empty]', 'yes', 'checkbox');

			if ($this->parameters()->{'redirect-404-on-empty'} == true) {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$fieldset->appendChild($label);
			$right->appendChild($fieldset);

		//	Output options ----------------------------------------------------

			$fieldset = Widget::Fieldset(__('Output Options'));

			//$container_parameter_output = $page->createElement('div');
			$context_content = $page->createElement('div');
			$fieldset->appendChild($context_content);

			$fieldset->appendChild(Widget::Input('fields[append-pagination]', 'no', 'hidden'));

			$label = Widget::Label(__('Append pagination data'));
			$input = Widget::Input('fields[append-pagination]', 'yes', 'checkbox');

			if ($this->parameters()->{'append-pagination'} == true) {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$fieldset->appendChild($label);

			$fieldset->appendChild(Widget::Input('fields[append-sorting]', 'no', 'hidden'));

			$label = Widget::Label(__('Append sorting data'));
			$input = Widget::Input('fields[append-sorting]', 'yes', 'checkbox');

			if ($this->parameters()->{'append-sorting'} == true) {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$fieldset->appendChild($label);

/*
			$fieldset->appendChild(Widget::Input('fields[append-associated-entry-count]', 'no', 'hidden'));

			$label = Widget::Label(__('Append entry count'));
			$input = Widget::Input('fields[append-associated-entry-count]', 'yes', 'checkbox');

			if ($this->parameters()->{'append-associated-entry-count'} == true) {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$fieldset->appendChild($label);

			$label = Widget::Label(__('HTML-encode text'));
			$input = Widget::Input('fields[html-encode]', 'yes', 'checkbox');

			if ($this->parameters()->{'html-encode'} == true) {
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
				$section_active = ($this->parameters()->section == $section_handle);
				$filter_data = $this->parameters()->filters;
				$fields = array();
				$duplicator = new Duplicator(__('Add Filter'));
				$duplicator->addClass('filtering-duplicator context context-' . $section_handle);

				// System ID template:
				$item = $duplicator->createTemplate(__('System ID'));

				$type_label = Widget::Label(__('Type'));
				$type_label->setAttribute('class', 'small');
				$type_label->appendChild(Widget::Select(
					'type',
					array(
						array('is', false, 'Is'),
						array('is-not', false, 'Is not')
					)
				));

				$label = Widget::Label(__('Value'));
				$label->appendChild(Widget::Input('value'));
				$label->appendChild(Widget::Input(
					'element-name', 'system:id', 'hidden'
				));

				$item->appendChild(Widget::Group(
					$type_label, $label
				));

				// Field templates:
				if (is_array($section_data['fields']) && !empty($section_data['fields'])) {
					foreach ($section_data['fields'] as $field) {
						if (!$field->canFilter()) continue;

						$element_name = $field->{'element-name'};
						$fields[$element_name] = $field;

						$item = $duplicator->createTemplate($field->name, $field->name());
						$field->displayDatasourceFilterPanel(
							$item, null, null
						);
					}
				}

				// Field isntances:
				if (is_array($filter_data) && !empty($filter_data)) {
					foreach ($filter_data as $filter) {
						if (isset($fields[$filter['element-name']])) {
							$element_name = $filter['element-name'];
							$field = $fields[$element_name];
							$item = $duplicator->createInstance($field->{'publish-label'}, $field->name());

							$field->displayDatasourceFilterPanel(
								$item, $filter, $errors->$element_name
							);
						}

						else if ($filter['element-name'] == 'system:id') {
							$item = $duplicator->createInstance(__('System ID'));

							$type_label = Widget::Label(__('Type'));
							$type_label->appendChild(Widget::Select(
								'type',
								array(
									array('is', false, 'Is'),
									array('is-not', $filter['type'] == 'is-not', 'Is not')
								)
							));

							$label = Widget::Label(__('Value'));
							$label->appendChild(Widget::Input(
								"value", $filter['value']
							));

							$label->appendChild(Widget::Input(
								'element-name', 'system:id', 'hidden'
							));

							$item->appendChild(Widget::Group(
								$type_label, $label
							));
						}
					}
				}

				$duplicator->appendTo($container_filter_results);

				// Select boxes:
				$sort_by_options = array(
					array('system:id', ($section_active and $this->parameters()->{'sort-field'} == 'system:id'), __('System ID')),
					array('system:creation-date', ($section_active and $this->parameters()->{'sort-field'} == 'system:creation-date'), __('System Creation Date')),
					array('system:modification-date', ($section_active and $this->parameters()->{'sort-field'} == 'system:modification-date'), __('System Modification Date')),
				);
				$options_parameter_output = array(
					array(
						'system:id',
						($section_active and in_array('system:id', $this->parameters()->{'parameter-output'})),
						__('$ds-?.system.id')
					),
					array(
						'system:creation-date',
						($section_active and in_array('system:creation-date', $this->parameters()->{'parameter-output'})),
						__('$ds-?.system.creation-date')
					),
					array(
						'system:modification-date',
						($section_active and in_array('system:modification-date', $this->parameters()->{'parameter-output'})),
						__('$ds-?.system.modification-date')
					),
					array(
						'system:user',
						($section_active and in_array('system:user', $this->parameters()->{'parameter-output'})),
						__('$ds-?.system.user')
					)
				);
				$included_elements_options = array(
					// TODO: Determine what system fields will be included.
					array(
						'system:creation-date',
						($section_active and in_array('system:creation-date', $this->parameters()->{'included-elements'})),
						__('System Creation Date')
					),
					array(
						'system:modification-date',
						($section_active and in_array('system:modification-date', $this->parameters()->{'included-elements'})),
						__('System Modification Date')
					),
					array(
						'system:user',
						($section_active and in_array('system:user', $this->parameters()->{'included-elements'})),
						__('System User')
					),
					/*array(
						'system:pagination',
						($section_active and in_array('system:pagination', $this->parameters()->{'included-elements'})),
						__('system:pagination')
					)*/
				);

				if (is_array($section_data['fields']) && !empty($section_data['fields'])) {
					foreach ($section_data['fields'] as $field) {
						$field_handle = $field->{'element-name'};
						$field_label = $field->{'publish-label'};
						$modes = $field->fetchIncludableElements();

						if ($field->isSortable()) {
							$sort_by_options[] = array(
								$field_handle,
								($section_active and $field_handle == $this->parameters()->{'sort-field'}),
								$field_label
							);
						}

						if ($field->allowDatasourceParamOutput()) {
							$options_parameter_output[] = array(
								$field_handle,
								($section_active and in_array($field_handle, $this->parameters()->{'parameter-output'})),
								__('$ds-?.%s', array($field_handle))
							);
						}

						if (is_array($modes)) foreach ($modes as $field_mode) {
							if(is_array($field_mode)){
								$included_elements_options[] = array(
									$field_mode['handle'],
									($section_active and in_array($field_mode['handle'], $this->parameters()->{'included-elements'})),
									$field_mode['name'] . (isset($field_mode['mode']) && strlen(trim($field_mode['mode'])) > 0 ? sprintf(" (%s)", $field_mode['mode']) : NULL)
								);
							}
							else{
								$included_elements_options[] = array(
									$field_mode,
									($section_active and in_array($field_mode, $this->parameters()->{'included-elements'})),
									$field_mode
								);
							}
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

				$include_label = Widget::Label(__('Included Fields'));

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

		protected function appendCondition(Duplicator $duplicator, $condition = array()) {
			$document = $duplicator->ownerDocument;

			if (empty($condition)) {
				$item = $duplicator->createTemplate(__('Don\'t Execute When'));
			}

			else {
				$item = $duplicator->createInstance(__('Don\'t Execute When'));
			}

			if (!isset($condition['parameter'])) {
				$condition['parameter'] = null;
			}

			if (!isset($condition['logic'])) {
				$condition['logic'] = 'empty';
			}

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
			$item->appendChild($group);
		}

		public function save(MessageStack $errors){

			if (strlen(trim($this->parameters()->limit)) == 0 || (is_numeric($this->parameters()->limit) && $this->parameters()->limit < 1)) {
				$errors->append('limit', __('A result limit must be set'));
			}

			if (strlen(trim($this->parameters()->page)) == 0 || (is_numeric($this->parameters()->page) && $this->parameters()->page < 1)) {
				$errors->append('page', __('A page number must be set'));
			}

			return parent::save($errors);
		}

	/*-----------------------------------------------------------------------*/

		/*public function canAppendAssociatedEntryCount() {
			return false;
		}

		public function canAppendPagination() {
			return false;
		}

		public function canHTMLEncodeText() {
			return false;
		}

		public function canRedirectOnEmpty() {
			return false;
		}

		public function getFilters() {
			return array();
		}

		public function getGroupField() {
			return '';
		}

		public function getIncludedElements() {
			return array();
		}

		public function getOutputParams() {
			return array();
		}

		public function getPaginationLimit() {
			return '20';
		}

		public function getPaginationPage() {
			return '1';
		}

		public function getRequiredURLParam() {
			return '';
		}

		public function getRootElement() {
			return 'sections';
		}

		public function getSection() {
			return null;
		}

		public function getSortField() {
			return 'system:id';
		}

		public function getSortOrder() {
			return 'desc';
		}*/

		public function render(Register $ParameterOutput, $joins = NULL, array $where = array(), $filter_operation_type = self::FILTER_AND){
			$execute = true;

			$result = new XMLDocument;
			$result->appendChild($result->createElement($this->parameters()->{'root-element'}));

			$root = $result->documentElement;

			//	Conditions
			//	If any one condtion returns true (that is, do not execute), the DS will not execute at all
			if(is_array($this->parameters()->conditions)) {
				foreach($this->parameters()->conditions as $condition) {
					if(preg_match('/:/', $condition['parameter'])) {
						$c = Datasource::replaceParametersInString($condition['parameter'], $ParameterOutput);
					}
					else {
						$c = Datasource::resolveParameter($condition['parameter'], $ParameterOutput);
					}

					// Is Empty
					if($condition['logic'] == 'empty' && (is_null($c) || strlen($c) == 0)){
						$execute = false;
					}

					// Is Set
					elseif($condition['logic'] == 'set' && !is_null($c)){
						$execute = false;
					}

					if($execute !== true) {
						return NULL;
					}

				}
			}

			// Grab the section
			try{
				$section = Section::loadFromHandle($this->parameters()->section);
			}
			catch(SectionException $e){

			}
			catch(Exception $e){

			}

			$pagination = (object)array(
				'total-entries' => NULL,
				'entries-per-page' => max(1, (int)self::replaceParametersInString($this->parameters()->limit, $ParameterOutput)),
				'total-pages' => NULL,
				'current-page' => max(1, (int)self::replaceParametersInString($this->parameters()->page, $ParameterOutput)),
			);

			$pagination->{'record-start'} = max(0, ($pagination->{'current-page'} - 1) * $pagination->{'entries-per-page'});

			$order = $sort = NULL;

			//	Apply the Sorting & Direction
			if($this->parameters()->{'sort-order'} == 'random'){
				$order = 'RAND()';
			}

			else{

				$sort = (strtolower($this->parameters()->{'sort-order'}) == 'asc' ? 'ASC' : 'DESC');

				// System Field
				if(preg_match('/^system:/i', $this->parameters()->{'sort-field'})){
					switch(preg_replace('/^system:/i', NULL, $this->parameters()->{'sort-field'})){
						case 'id':
							$order = "e.id {$sort}";
							break;

						case 'creation-date':
							$order = "e.creation_date {$sort}";
							break;

						case 'modification-date':
							$order = "e.modification_date {$sort}";
							break;

					}
				}
				// Non System Field
				else{
					$join = NULL;
					$sort_field = $section->fetchFieldByHandle($this->parameters()->{'sort-field'});

					if($sort_field instanceof Field && $sort_field->isSortable() && method_exists($sort_field, "buildSortingQuery")) {
						$sort_field->buildSortingQuery($join, $order);

						$joins .= sprintf($join, $sort_field->section, $sort_field->{'element-name'});
						$order = sprintf($order, $sort);
					}
				}
			}

			//	Process Datasource Filters for each of the Fields
			if(is_array($this->parameters()->filters) && !empty($this->parameters()->filters)) {
				foreach($this->parameters()->filters as $k => $filter){
					if($filter['element-name'] == 'system:id'){
						$filter_value = $this->prepareFilterValue($filter['value'], $ParameterOutput);

						if(!is_array($filter_value)) continue;

						$filter_value = array_map('intval', $filter_value);

						if(empty($filter_value)) continue;

						$where[] = sprintf(
							"(e.id %s IN (%s))",
							($filter['type'] == 'is-not' ? 'NOT' : NULL),
							implode(',', $filter_value)
						);
					}
					else{
						$field = $section->fetchFieldByHandle($filter['element-name']);
						if($field instanceof Field) {
							$field->buildFilterQuery($filter, $joins, $where, $ParameterOutput);
						}
					}
				}
			}

			// Escape percent symbold:
			$where = array_map(create_function('$string', 'return str_replace(\'%\', \'%%\', $string);'), $where);

			$query = sprintf(
				'SELECT DISTINCT SQL_CALC_FOUND_ROWS e.*
				FROM `tbl_entries` AS `e`
				%1$s
				WHERE `section` = "%2$s"
				%3$s
				ORDER BY %4$s
				LIMIT %5$d, %6$d',

				$joins,
				$section->handle,
				is_array($where) && !empty($where) ? 'AND (' . implode(($filter_operation_type == self::FILTER_AND ? ' AND ' : ' OR '), $where) . ')' : NULL,
				$order,
				$pagination->{'record-start'},
				$pagination->{'entries-per-page'}
			);

			try{
				$entries = Symphony::Database()->query($query, array(
						$section->handle,
						$section->{'publish-order-handle'}
					), 'EntryResult'
				);

				if(isset($this->parameters()->{'append-pagination'}) && $this->parameters()->{'append-pagination'} === true){
					$pagination->{'total-entries'} = (int)Symphony::Database()->query("SELECT FOUND_ROWS() AS `total`")->current()->total;
					$pagination->{'total-pages'} = (int)ceil($pagination->{'total-entries'} * (1 / $pagination->{'entries-per-page'}));

					// Pagination Element
					$root->appendChild(General::buildPaginationElement(
						$result, $pagination->{'total-entries'}, $pagination->{'total-pages'}, $pagination->{'entries-per-page'}, $pagination->{'current-page'}
					));
				}

				if(isset($this->parameters()->{'append-sorting'}) && $this->parameters()->{'append-sorting'} === true){
					$sorting = $result->createElement('sorting');
					$sorting->setAttribute('field', $this->parameters()->{'sort-field'});
					$sorting->setAttribute('order', $this->parameters()->{'sort-order'});
					$root->appendChild($sorting);
				}

				$schema = array();
				// Build Entry Records
				if($entries->valid()){

					// Do some pre-processing on the include-elements.
					if(is_array($this->parameters()->{'included-elements'}) && !empty($this->parameters()->{'included-elements'})){
						$included_elements = (object)array('system' => array(), 'fields' => array());
						foreach($this->parameters()->{'included-elements'} as $element){
							$element_name = $mode = NULL;

							if(preg_match_all('/^([^:]+):\s*(.+)$/', $element, $matches, PREG_SET_ORDER)){
								$element_name = $matches[0][1];
								$mode = $matches[0][2];
							}
							else{
								$element_name = $element;
							}

							if($element_name == 'system'){
								$included_elements->system[] = $mode;
							}
							else{
								$field = $section->fetchFieldByHandle($element_name);

								if(!$field instanceof Field) continue;

								$schema[] = $element_name;
								$included_elements->fields[] = array(
									'element-name' => $element_name,
									'instance' => $field,
									'mode' => (!is_null($mode) > 0 ? trim($mode) : NULL)
								);
							}
						}
					}

					// Do some pre-processing on the param output array
					if(is_array($this->parameters()->{'parameter-output'}) && !empty($this->parameters()->{'parameter-output'})){
						$parameter_output = (object)array('system' => array(), 'fields' => array());
						foreach($this->parameters()->{'parameter-output'} as $element){
							if(preg_match('/^system:/i', $element)){
								$parameter_output->system[preg_replace('/^system:/i', NULL, $element)] = array();
							}
							else{
								$schema[] = $element;
								$parameter_output->fields[$element] = array();
							}
						}
					}

					$entries->setSchema($schema);
					foreach($entries as $e){
						// If there are included elements, need an entry element.
						if(is_array($this->parameters()->{'included-elements'}) && !empty($this->parameters()->{'included-elements'})){
							$entry = $result->createElement('entry');
							$entry->setAttribute('id', $e->id);
							$root->appendChild($entry);

							foreach($included_elements->system as $field){
								switch($field){
									case 'creation-date':
										$entry->appendChild(General::createXMLDateObject(
											$result, DateTimeObj::fromGMT($e->creation_date), 'creation-date'
										));
										break;

									case 'modification-date':
										$entry->appendChild(General::createXMLDateObject(
											$result, DateTimeObj::fromGMT($e->modification_date), 'modification-date'
										));
										break;


									case 'user':
										$obj = User::load($e->user_id);
										$user = $result->createElement('user', $obj->getFullName());
										$user->setAttribute('id', $e->user_id);
										$user->setAttribute('username', $obj->username);
										$user->setAttribute('email-address', $obj->email);
										$entry->appendChild($user);
										break;
								}
							}

							foreach($included_elements->fields as $field){
								$field['instance']->appendFormattedElement(
									$entry, $e->data()->{$field['element-name']}, false, $field['mode'], $e
								);
							}
						}

						if(is_array($this->parameters()->{'parameter-output'}) && !empty($this->parameters()->{'parameter-output'})){
							foreach($parameter_output->system as $field => $existing_values){
								switch($field){
									case 'id':
										$parameter_output->system[$field][] = $e->id;
										break;

									case 'creation-date':
										$parameter_output->system[$field][] = DateTimeObj::get('Y-m-d H:i:s', DateTimeObj::fromGMT($e->creation_date));
										break;

									case 'modification-date':
										$parameter_output->system[$field][] = DateTimeObj::get('Y-m-d H:i:s', DateTimeObj::fromGMT($e->modification_date));
										break;

									case 'user':
										$parameter_output->system[$field][] = $e->user_id;
										break;
								}
							}

							foreach($parameter_output->fields as $field => $existing_values){
								if (!isset($e->data()->$field) or is_null($e->data()->$field)) continue;

								$o = $section->fetchFieldByHandle($field)->getParameterOutputValue(
									$e->data()->$field, $e
								);

								if(is_array($o)){
									$parameter_output->fields[$field] = array_merge($o, $parameter_output->fields[$field]);
								}
								else{
									$parameter_output->fields[$field][]	= $o;
								}

							}
						}

					}

					// Add in the param output values to the ParameterOutput object
					if(is_array($this->parameters()->{'parameter-output'}) && !empty($this->parameters()->{'parameter-output'})){
						foreach($parameter_output->system as $field => $values){
							$key = sprintf('ds-%s.system.%s', $this->parameters()->{'root-element'}, $field);
							$values = array_filter($values);

							if(is_array($values) && !empty($values)) $ParameterOutput->$key = array_unique($values);
						}

						foreach($parameter_output->fields as $field => $values){
							$key = sprintf('ds-%s.%s', $this->parameters()->{'root-element'}, $field);
							$values = array_filter($values);

							if(is_array($values) && !empty($values)) $ParameterOutput->$key = array_unique($values);
						}
					}
				}

				// No Entries, Redirect
				elseif($this->parameters()->{'redirect-404-on-empty'} === true){
					throw new FrontendPageNotFoundException;
				}

				// No Entries, Show empty XML
				else{
					$this->emptyXMLSet($root);
				}

			}
			catch(DatabaseException $e){
				$root->appendChild($result->createElement(
					'error', $e->getMessage()
				));
			}

			return $result;
		}
	}
