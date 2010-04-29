<?php

	require_once(TOOLKIT . '/class.entry.php');

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
			   'sort-field' => 'system:id',
			   'sort-order' => 'desc',
			   'included-elements' => array(),
			   'parameter-output' => array(),
			);
		}

		final public function type(){
			return 'ds_sections';
		}

		public function template(){
			return EXTENSIONS . '/ds_sections/templates/datasource.php';
		}

		public function save(MessageStack &$errors){

			if (strlen(trim($this->parameters()->limit)) == 0 || (is_numeric($this->parameters()->limit) && $this->parameters()->limit < 1)) {
				$errors->append('limit', __('A result limit must be set'));
			}

			if (strlen(trim($this->parameters()->page)) == 0 || (is_numeric($this->parameters()->page) && $this->parameters()->page < 1)) {
				$errors->append('page', __('A page number must be set'));
			}

			return parent::save($errors);
		}

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

		public function render(Register &$ParameterOutput){
			$execute = true;

			$result = new XMLDocument;
			$result->appendChild($result->createElement($this->parameters()->{'root-element'}));

			$root = $result->documentElement;

			//	Conditions
			//	If any one condtion returns true (that is, do not execute), the DS will not execute at all
			if(is_array($this->parameters()->conditions)) {
				foreach($this->parameters()->conditions as $condition) {
					$c = Datasource::resolveParameter($condition['parameter'], $ParameterOutput);

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

			$order = $sort = $joins = $where = NULL;

			//	Apply the Sorting & Direction
			if($this->parameters()->{'sort-order'} == 'random'){
				$sort = 'RAND()';
			}

			else{

				$sort = (strtolower($this->parameters()->{'sort-order'}) == 'asc' ? 'ASC' : 'DESC');

				// System Field
				if(preg_match('/^system:/i', $this->parameters()->{'sort-field'})){
					switch(preg_replace('/^system:/i', NULL, $this->parameters()->{'sort-field'})){
						case 'id':
							$order = 'e.id';
							break;

						case 'creation-date':
							$order = 'e.creation_date';
							break;

						case 'modification-date':
							$order = 'e.modification_date';
							break;

					}
				}
				// Non System Field
				else{
					$join = NULL;
					$sort_field = $section->fetchFieldByHandle($this->parameters()->{'sort-field'});
					$sort_field->buildSortingSQL($join, $order);

					$joins .= sprintf($join, $sort_field->section, $sort_field->{'element-name'});
					$order = sprintf($order, $sort);
				}
			}

			//	Process Datasource Filters for each of the Fields
			if(is_array($this->parameters()->filters) && !empty($this->parameters()->filters)) {
				foreach($this->parameters()->filters as $element_name => $filter){

					if($element_name == 'system:id'){
						$filter_value = $this->prepareFilterValue($filter['value'], $ParameterOutput);
						$filter_value = array_map('intval', $filter_value);
						$where .= sprintf(
							" AND e.id %s IN (%s)",
							($filter['type'] == 'is-not' ? 'NOT' : NULL),
							implode(',', $filter_value)
						);
					}
					else{
						$field = $section->fetchFieldByHandle($element_name);
						$field->buildFilterQuery($filter, $joins, $where, $ParameterOutput);
					}
				}
			}
			
			// Escape percent symbold:
			$where = str_replace('%', '%%', $where);

			$query = sprintf(
				'SELECT SQL_CALC_FOUND_ROWS e.*
				FROM `tbl_entries` AS `e`
				%1$s
				WHERE `section` = "%2$s"
				%3$s
				ORDER BY %4$s
				LIMIT %5$d, %6$d',

				$joins,
				$section->handle,
				$where,
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

				// Build Entry Records
				if($entries->length() > 0){

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
								$included_elements->fields[] = array(
									'element-name' => $element_name,
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
								$parameter_output->fields[$element] = array();
							}
						}
					}

					foreach($entries as $e){

						// If there are included elements, need an entry element.
						if(is_array($this->parameters()->{'included-elements'}) && !empty($this->parameters()->{'included-elements'})){
							$entry = $result->createElement('entry');
							$entry->setAttribute('id', $e->id);
							$root->appendChild($entry);

							foreach($included_elements->system as $field){
								switch($field){
									case 'creation-date':
										$entry->appendChild(General::createXMLDateObject($result, strtotime($e->creation_date), 'creation-date'));
										break;

									case 'modification-date':
										$entry->appendChild(General::createXMLDateObject($result, strtotime($e->modification_date), 'modification-date'));
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
								$section->fetchFieldByHandle($field['element-name'])->appendFormattedElement($entry, $e->data()->{$field['element-name']}, false, $field['mode']);
							}
						}

						if(is_array($this->parameters()->{'parameter-output'}) && !empty($this->parameters()->{'parameter-output'})){
							foreach($parameter_output->system as $field => $existing_values){
								switch($field){
									case 'id':
										$parameter_output->system[$field][] = $e->id;
										break;

									case 'creation-date':
										$parameter_output->system[$field][] = DateTimeObj::get('Y-m-d H:i:s', strtotime($e->creation_date));
										break;

									case 'modification-date':
										$parameter_output->system[$field][] = DateTimeObj::get('Y-m-d H:i:s', strtotime($e->modification_date));
										break;

									case 'user':
										$parameter_output->system[$field][] = $e->user_id;
										break;
								}
							}

							foreach($parameter_output->fields as $field => $existing_values){
								//	TODO?
							}
						}

					}

					// Add in the param output values to the ParameterOutput object
					if(is_array($this->parameters()->{'parameter-output'}) && !empty($this->parameters()->{'parameter-output'})){
						foreach($parameter_output->system as $field => $values){
							$key = sprintf('ds-%s-%s', $this->parameters()->{'root-element'}, $field);
							$ParameterOutput->$key = array_unique($values);
						}

						foreach($parameter_output->fields as $field => $values){
							$key = sprintf('ds-%s-%s', $this->parameters()->{'root-element'}, $field);
							$ParameterOutput->$key = array_unique($values);
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
					'error', General::sanitize($e->getMessage())
				));
			}

			return $result;

		}

		public function prepareSourceColumnValue(){
			$section = Section::loadFromHandle($this->_parameters->section);

			if ($section instanceof Section) {
				return Widget::TableData(
					Widget::Anchor($section->name, URL . '/symphony/blueprints/sections/edit/' . $section->handle . '/', array(
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
	}
