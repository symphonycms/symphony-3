<?php

	Class fieldLink extends Field{
		static protected $cacheRelations = array();
		static protected $cacheFields = array();
		static protected $cacheValues = array();

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();
			$this->_name = __('Link');

			// Set default
			$this->{'limit'} = 20;
		}

		public function create(){
			return Symphony::Database()->query(sprintf(
				"
					CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`entry_id` int(11) unsigned NOT NULL,
						`relation_id` int(11) unsigned DEFAULT NULL,
						PRIMARY KEY	 (`id`),
						KEY `entry_id` (`entry_id`),
						KEY `relation_id` (`relation_id`)
					)
				",
				$this->{'section'},
				$this->{'element-name'}
			));
		}

		public function update() {
			// TODO: Remove this when table structure is table:
			$this->create();
		}

		public function canToggleData(){
			return ($this->{'allow-multiple-selection'} == 'yes' ? false : true);
		}

		public function getToggleStates(){
			$options = $this->findOptions();
			$output = $options[0]['values'];
			$output[''] = __('None');
			return $output;
		}

		public function toggleEntryData(StdClass $data, $value, Entry $entry=NULL){
			$data['relation_id'] = $new_value;
			return $data;
		}

		public function canFilter(){
			return true;
		}

		public function allowDatasourceOutputGrouping(){
			return true;
		}

		public function allowDatasourceParamOutput(){
			return true;
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function findOptions(array $existing_selection=NULL){
			$values = array();

			foreach($this->{'related-fields'} as $key => $value){
				list($section_handle, $field_handle) = $value;

				try{
					$section = Section::loadFromHandle($section_handle);
				}
				catch(Exception $e){
					continue;
				}

				$group = array('name' => $section->name, 'section' => $section->handle, 'values' => array());

				$join = NULL;
				$order = ' e.id DESC ';
				if(isset($section->{'publish-order-handle'}) && strlen($section->{'publish-order-handle'}) > 0) {

					$sort_field = $section->fetchFieldByHandle($section->{'publish-order-handle'});
					if($sort_field instanceof Field){
						$sort_field->buildSortingQuery($join, $order);

						$joins .= sprintf($join, $sort_field->section, $sort_field->{'element-name'});
						$order = sprintf($order, $section->{'publish-order-direction'});
					}
				}

				$query = sprintf("
					SELECT e.*
					FROM `tbl_entries` AS e
					%s
					WHERE e.section = '%s'
					ORDER BY %s
					LIMIT %d, %d",
					$joins, $section->handle, $order, 0, $this->{'limit'}
				);

				try{
					$entries = Symphony::Database()->query(
						$query,
						array(),
						'EntryResult'
					);

					$entries->setSchema(array($field_handle));

					foreach($entries as $e){
						$group['values'][$e->id] = $section->fetchFieldByHandle($field_handle)->prepareTableValue(
							$e->data()->$field_handle
						);
					}


					$values[] = $group;
				}
				catch(DatabaseException $e){

				}
			}

			return $values;
		}
/*
		public function findFieldIDFromRelationID($id){
			if(is_null($id)) return NULL;

			if (isset(self::$cacheRelations[$id])) {
				return self::$cacheRelations[$id];
			}

			try{
				## Figure out the section
				$section_id = Symphony::Database()->fetchVar('section_id', 0, "SELECT `section_id` FROM `tbl_entries` WHERE `id` = '{$id}' LIMIT 1");


				## Figure out which related-field-id is from that section
				$field_id = Symphony::Database()->fetchVar('field_id', 0, "SELECT f.`id` AS `field_id`
					FROM `tbl_fields` AS `f`
					LEFT JOIN `tbl_sections` AS `s` ON f.parent_section = s.id
					WHERE `s`.id = {$section_id} AND f.id IN ('".@implode("', '", $this->{'related-field-id'})."') LIMIT 1");
			}
			catch(Exception $e){
				return NULL;
			}

			self::$cacheRelations[$id] = $field_id;

			return $field_id;
		}

		protected function __findPrimaryFieldValueFromRelationID($entry_id){
			$field_id = $this->findFieldIDFromRelationID($entry_id);

			if (!isset(self::$cacheFields[$field_id])) {
				self::$cacheFields[$field_id] = Symphony::Database()->fetchRow(0, "
					SELECT
						f.id, f.type,
						s.name AS `section_name`,
						s.handle AS `section_handle`
					 FROM
					 	`tbl_fields` AS f
					 INNER JOIN
					 	`tbl_sections` AS s
					 	ON s.id = f.parent_section
					 WHERE
					 	f.id = '{$field_id}'
					 ORDER BY
					 	f.sortorder ASC
					 LIMIT 1
				");
			}

			$primary_field = self::$cacheFields[$field_id];

			if(!$primary_field) return NULL;

			$field = Field::loadFromType($primary_field['type']);

			if (!isset(self::$cacheValues[$entry_id])) {
				self::$cacheValues[$entry_id] = Symphony::Database()->fetchRow(0,
					"SELECT *
					 FROM `tbl_entries_data_{$field_id}`
					 WHERE `entry_id` = '{$entry_id}' ORDER BY `id` DESC LIMIT 1"
				);
			}

			$data = self::$cacheValues[$entry_id];

			if(empty($data)) return null;

			$primary_field['value'] = $field->prepareTableValue($data);

			return $primary_field;
		}
*/
	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function findDefaultSettings(&$fields){
			if (!isset($fields['allow-multiple-selection'])) $fields['allow-multiple-selection'] = 'no';
		}

		public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $errors) {
			parent::displaySettingsPanel($wrapper, $errors);

			$document = $wrapper->ownerDocument;
			$label = Widget::Label(__('Options'));
			$options = array();

			foreach (new SectionIterator as $section) {
				if(!is_array($section->fields)) continue;

				$fields = array();

				foreach($section->fields as $field) {
					if($field->canPrePopulate() and $field->{'element-name'} != $this->{'element-name'}) {
						$fields[] = array(
							$section->handle . '::' .$field->{'element-name'},
							(isset($this->{'related-fields'}["{$section->handle}::" . $field->{'element-name'}])),
							$field->name
						);
					}
				}

				if(!empty($fields)) {
					$options[] = array(
						'label' => $section->name,
						'options' => $fields
					);
				}
			}

			$label->appendChild(Widget::Select('related-fields][', $options, array('multiple' => 'multiple')));

			if (isset($errors->{'related-fields'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'related-fields'});
			}

			$wrapper->appendChild($label);

			## Maximum entries
			$label = Widget::Label();
			$input = Widget::Input('limit', $this->{'limit'});
			$input->setAttribute('size', '3');

			$label->appendChild(new DOMText(__('Limit to the ')));
			$label->appendChild($input);
			$label->appendChild(new DOMText(__(' most recent entries')));

			$wrapper->appendChild($label);


			$options_list = $wrapper->ownerDocument->createElement('ul');
			$options_list->setAttribute('class', 'options-list');

			$this->appendShowColumnCheckbox($options_list);
			$this->appendRequiredCheckbox($options_list);

			## Allow selection of multiple items
			$label = Widget::Label();
			$input = Widget::Input('allow-multiple-selection', 'yes', 'checkbox');

			if($this->{'allow-multiple-selection'} == 'yes') $input->setAttribute('checked', 'checked');

			$label->appendChild($input);
			$label->appendChild(new DOMText(__('Allow selection of multiple options')));

			$options_list->appendChild($label);
			$wrapper->appendChild($options_list);
		}

		public function getExampleFormMarkup(){
			return Widget::Input('fields['.$this->{'element-name'}.']', '...', 'hidden');
		}

		/*
		public function commit(){
			if(!parent::commit()) return false;

			$id = $this->{'id'};

			if($id === false) return false;

			$fields = array();
			$fields['field_id'] = $id;
			if($this->{'related-field-id'} != '') $fields['related-field-id'] = $this->{'related-field-id'};
			$fields['allow-multiple-selection'] = ($this->{'allow-multiple-selection'} ? $this->{'allow-multiple-selection'} : 'no');
			$fields['limit'] = max(1, (int)$this->{'limit'});
			$fields['related-field-id'] = implode(',', $this->{'related-field-id'});

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id'");

			if(!Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle())) return false;

			//$sections = $this->{'related-field-id'};

			$this->removeSectionAssociation($id);

			//$section_id = Symphony::Database()->fetchVar('parent_section', 0, "SELECT `parent_section` FROM `tbl_fields` WHERE `id` = '".$fields['related-field-id']."' LIMIT 1");

			foreach($this->{'related-field-id'} as $field_id){
				$this->createSectionAssociation(NULL, $id, $field_id);
			}

			return true;
		}
		*/


	    public function toDoc() {
			$related_fields = NULL;
			if(isset($this->properties->{'related-fields'}) && is_array($this->properties->{'related-fields'})){
				$related_fields = $this->properties->{'related-fields'};
			}

			unset($this->properties->{'related-fields'});

			$doc = parent::toDoc();

			$this->properties->{'related-fields'} = $related_fields;

			if(!is_null($related_fields)){
				$element = $doc->createElement('related-fields');

				foreach($related_fields as $key => $value){
					list($section, $field) = $value;
					$item = $doc->createElement('item');
					$item->setAttributeArray(array('section' => $section, 'field' => $field));
					$element->appendChild($item);
				}
				$doc->documentElement->appendChild($element);
			}

			return $doc;
	    }

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/
/*
		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry = NULL, $data = NULL) {

			$entry_ids = array();

			if(!is_null($data['relation_id'])){
				if(!is_array($data['relation_id'])){
					$entry_ids = array($data['relation_id']);
				}
				else{
					$entry_ids = array_values($data['relation_id']);
				}

			}

			$states = $this->findOptions($entry_ids);
			$options = array();

			if($this->{'required'} != 'yes') $options[] = array(NULL, false, NULL);

			if(!empty($states)){
				foreach($states as $s){
					$group = array('label' => $s['name'], 'options' => array());
					foreach($s['values'] as $id => $v){
						$group['options'][] = array($id, in_array($id, $entry_ids), General::sanitize($v));
					}
					$options[] = $group;
				}
			}

			$fieldname = 'fields['.$this->{'element-name'}.']';
			if($this->{'allow-multiple-selection'} == 'yes') $fieldname .= '[]';

			$label = Widget::Label($this->{'label'});
			$label->appendChild(Widget::Select($fieldname, $options, ($this->{'allow-multiple-selection'} == 'yes' ? array('multiple' => 'multiple') : NULL)));

			if ($errors->valid()) {
				$label = Widget::wrapFormElementWithError($label, $errors->current()->message);
			}

			$wrapper->appendChild($label);
		}
*/
		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry = null, $data = null) {
			if(!is_array($data)){
				$data = array($data);
			}

			$selected = array();
			foreach($data as $d){
				if(!($d instanceof StdClass) || !isset($d->relation_id)) continue;

				if(!is_array($d->relation_id)){
					$selected[] = $d->relation_id;
				}
				else{
					$selected = array_merge($d->relation_id, $selected);
				}
			}

			$states = $this->findOptions($selected);

			$options = array();

			if($this->{'required'} != 'yes') $options[] = array(NULL, false, NULL);

			if(!empty($states)){
				foreach($states as $s){
					$group = array('label' => $s['name'], 'options' => array());
					foreach($s['values'] as $id => $v){
						$group['options'][] = array($id, in_array($id, $selected), $v);
					}
					$options[] = $group;
				}
			}

			$fieldname = 'fields['.$this->{'element-name'}.']';
			if($this->{'allow-multiple-selection'} == 'yes') $fieldname .= '[]';

			$label = Widget::Label(
				(isset($this->{'publish-label'}) && strlen(trim($this->{'publish-label'})) > 0
					? $this->{'publish-label'}
					: $this->name)
			);
			$label->appendChild(Widget::Select($fieldname, $options, ($this->{'allow-multiple-selection'} == 'yes' ? array('multiple' => 'multiple') : array())));

			if ($errors->valid()) {
				$label = Widget::wrapFormElementWithError($label, $errors->current()->message);
			}

			$wrapper->appendChild($label);
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		public function loadDataFromDatabase(Entry $entry, $expect_multiple = false){
			return parent::loadDataFromDatabase($entry, true); //$this->{'allow-multiple-selection'} == 'yes');
			/*
			$result = (object)array(
				'relation_id' => null
			);

			try{
				$rows = Symphony::Database()->query(
					"SELECT `relation_id` FROM `tbl_data_%s_%s` WHERE `entry_id` = %s ORDER BY `id` ASC",
					array(
						$entry->section,
						$this->{'element-name'},
						$entry->id
					)
				);

				if($rows->length() > 0){
					$result->relation_id = $rows->resultColumn('relation_id');
				}
			}
			catch(DatabaseException $e){
			}
			var_dump($result); die();

			return $result;*/
		}

		public function loadDataFromDatabaseEntries($section, $entry_ids){
			try {
				$data = parent::loadDataFromDatabaseEntries($section, $entry_ids);
				$result = array();
				$ids = array();

				foreach ($data as $entry) {
					$ids[] = $entry->relation_id;
				}

				foreach ($this->{'related-fields'} as $related) {
					$rows = Symphony::Database()->query("
							SELECT `e`.*, r.entry_id AS `entry_id`, r.relation_id AS `relation_id`
							FROM `tbl_data_%s_%s` AS `e`
							LEFT OUTER JOIN `tbl_data_%s_%s` AS `r` ON (e.entry_id = r.relation_id)
							WHERE e.entry_id IN (%s)
							AND r.entry_id IN (%s)
						",
						array(
							$related[0], $related[1],
							$section, $this->{'element-name'},
							implode(',', $ids),
							implode(',', $entry_ids)
						)
					);
					
					foreach ($rows as $r) {
						$r->relation_field = $related;
						$result[] = $r;
					}
				}
			}
			
			catch (DatabaseException $e) {
				$result = array();
			}
			
			return $result;
		}

		public function validateData(MessageStack $errors, Entry $entry=NULL, $data=NULL){

			if ($this->required == 'yes' && empty($data)){
				$errors->append(
					null, (object)array(
					 	'message' => __("'%s' is a required field.", array($this->{'publish-label'})),
						'code' => self::ERROR_MISSING
					)
				);
				return self::STATUS_ERROR;
			}
			return self::STATUS_OK;
		}

		public function processData($data, Entry $entry=NULL){

			//if(isset($entry->data()->{$this->{'element-name'}})){
			//	$result = $entry->data()->{$this->{'element-name'}};
			//}

			//else {
				$result = NULL;
			//}

			if (!is_null($data)){
				$result = array();

				if(!is_array($data)) $data = array($data);

				foreach ($data as $id) {
					if ($id instanceof StdClass) {
						$result[] = $id;
					}

					else {
						$result[] = (object)array(
							'relation_id' => $id
						);
					}
				}
			}

			return $result;
		}

		public function setPropertiesFromPostData($data){
			if(isset($data['related-fields'])){

				$related_fields = array();

				if(!is_array($data['related-fields'])) $data['related-fields'] = (array)$data['related-fields'];

				foreach($data['related-fields'] as $item){
					$related_fields[$item] = preg_split('/::/', $item, 2, PREG_SPLIT_NO_EMPTY);;
				}

				$this->{'related-fields'} = $related_fields;
				unset($data['related-fields']);
			}

			return parent::setPropertiesFromPostData($data);
		}

		public function loadSettingsFromSimpleXMLObject(SimpleXMLElement $xml){

			$related_fields = array();
			if(isset($xml->{'related-fields'})){
				foreach($xml->{'related-fields'}->item as $item){
					$key = sprintf('%s::%s', (string)$item->attributes()->section, (string)$item->attributes()->field);
					$related_fields[$key] = array((string)$item->attributes()->section, (string)$item->attributes()->field);
				}
			}
			unset($xml->{'related-fields'});

			foreach($xml as $property_name => $property_value){
				$data[(string)$property_name] = (string)$property_value;
			}

			$this->{'related-fields'} = $related_fields;

			// Set field GUID:
			if (isset($xml->attributes()->guid) and trim((string)$xml->attributes()->guid) != '') {
				$data['guid'] = (string)$xml->attributes()->guid;
			}

			$this->setPropertiesFromPostData($data);
		}

		public function saveData(MessageStack $errors, Entry $entry, $data = null) {

			$table = sprintf('tbl_data_%s_%s', $entry->section, $this->{'element-name'});
			Symphony::Database()->delete($table, array($entry->id), '`entry_id` = %s');

			if(is_null($data)) return;

			foreach($data as $d){

				try{

					Symphony::Database()->insert(
						$table,
						array('relation_id' => $d->relation_id, 'id' => NULL, 'entry_id' => $entry->id)
					);

				}
				catch(DatabaseException $e){
					return self::STATUS_ERROR;
				}
				catch(Exception $e){
					return self::STATUS_ERROR;
				}
			}
			return self::STATUS_OK;
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(DOMElement $wrapper, $data, $encode = false, $mode = null, Entry $entry = null) {
			if (!isset($data)) return;
			if (!is_array($data) || empty($data)) $data = array($data);
			
			$xpath = new DOMXPath($wrapper->ownerDocument);
			$list = $wrapper->ownerDocument->createElement($this->{'element-name'});
			$list->ownerDocument->formatOutput = true;
			
			$groups = array();
			
			foreach ($data as $item) {
				if (!isset($item->relation_id) || is_null($item->relation_id)) continue;
				
				if (!isset($groups[$item->relation_id])) {
					$groups[$item->relation_id] = array();
				}
				
				$groups[$item->relation_id][] = $item;
			}
			
			foreach ($groups as $relations) {
				foreach ($relations as $relation) {
					list($section_handle, $field_handle) = $relation->relation_field;
					
					$item = $xpath->query('item[@id = ' . $relation->relation_id . ']', $list)->item(0);
					
					if (is_null($item)) {
						$section = Section::loadFromHandle($section_handle);
						$item = $wrapper->ownerDocument->createElement('item');
						$item->setAttribute('id', $relation->relation_id);
						$item->setAttribute('section-handle', $section_handle);
						$item->setAttribute('section-name', $section->name);
						
						$list->appendChild($item);
					}
					
					$related_field = $section->fetchFieldByHandle($field_handle);
					$related_field->appendFormattedElement($item, $relation);
				}
			}
			
			$wrapper->appendChild($list);
		}

		public function prepareTableValue($data, DOMElement $link = null){
			if (!is_array($data) || empty($data)) {
				return parent::prepareTableValue(null, $link);
			}
			
			$result = Administration::instance()->Page->createDocumentFragment();
			$schema = array();
			
			foreach ($this->{'related-fields'} as $key => $value) {
				list($section_handle, $field_handle) = $value;
				
				$schema[] = $field_handle;
			}
			
			foreach ($data as $index => $d) try {
				$entry = Entry::loadFromID($d->relation_id, $schema);
				
				if (!$entry instanceof Entry) continue;
				
				foreach ($this->{'related-fields'} as $key => $value) {
					list($section_handle, $field_handle) = $value;
					
					if ($section_handle != $entry->meta()->section) continue;
					
					$section = Section::loadFromHandle($section_handle);
					$field = $section->fetchFieldByHandle($field_handle);
					$value = $field->prepareTableValue($entry->data()->{$field_handle});
					
					if ($index > 0) {
						$result->appendChild(new DOMText(', '));
					}
					
					if ($link instanceof DOMElement) {
						if ($value instanceof DOMElement) {
							$result->appendChild($value);
						}
						
						else {
							$result->appendChild(new DOMText($value));
						}
					}
					
					else {
						$result->appendChild(Widget::anchor(
							$value, sprintf(
								'%s/publish/%s/edit/%d/',
								ADMIN_URL, $section_handle, $entry->meta()->id
							)
						));
					}
					
					break;
				}
			}
			
			catch (Exception $e) {
				
			}
			
			if (!$result->hasChildNodes()) {
				$result = parent::prepareTableValue(null, $link);
			}
			
			if ($link instanceof DOMElement) {
				$link->removeChildNodes();
				$link->appendChild($result);
				$result = $link;
			}
			
			return $result;

		}

		public function getParameterOutputValue($data, Entry $entry=NULL){
			if(!is_array($data)) $data = array($data);

			$result = array();
			if(!empty($data)) foreach($data as $d) {
				if(is_null($d->relation_id)) continue;

				$result[] = $d->relation_id;
			}

			return $result;
		}

		public function fetchAssociatedEntrySearchValue($data, $field_id=NULL, $parent_entry_id=NULL){
			// We dont care about $data, but instead $parent_entry_id
			if(!is_null($parent_entry_id)) return $parent_entry_id;

			if(!is_array($data)) return $data;

			$searchvalue = Symphony::Database()->fetchRow(0,
				sprintf("
					SELECT `entry_id` FROM `tbl_entries_data_%d`
					WHERE `handle` = '%s'
					LIMIT 1", $field_id, addslashes($data['handle']))
			);

			return $searchvalue['entry_id'];
		}

		public function fetchAssociatedEntryCount($value){
			return Symphony::Database()->fetchVar('count', 0, "SELECT count(*) AS `count` FROM `tbl_entries_data_".$this->{'id'}."` WHERE `relation_id` = '$value'");
		}

		public function fetchAssociatedEntryIDs($value){
			return Symphony::Database()->fetchCol('entry_id', "SELECT `entry_id` FROM `tbl_entries_data_".$this->{'id'}."` WHERE `relation_id` = '$value'");
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function getFilterTypes($data) {
			$standard = parent::getFilterTypes($data);
			$types = array();

			foreach ($standard as $current) if ($current[0] == 'is' or $current[0] == 'is-not') {
				$types[] = $current;
			}

			return $types;
		}

		public function buildFilterQuery($filter, &$joins, array &$where, Register $parameter_output) {
			$filter = $this->processFilter($filter);
			$filter_join = DataSource::FILTER_OR;
			$db = Symphony::Database();

			$values = DataSource::prepareFilterValue($filter->value, $parameter_output, $filter_join);

			if (!is_array($values)) $values = array();

			// Exact matches:
			if ($filter->type == 'is' or $filter->type == 'is-not') {
				$statements = array();

				if ($filter_join == DataSource::FILTER_OR) {
					$handle = $this->buildFilterJoin($joins);
				}

				foreach ($values as $index => $value) {
					if ($filter_join != DataSource::FILTER_OR) {
						$handle = $this->buildFilterJoin($joins);
					}

					$statements[] = $db->prepareQuery(
						"'%s' IN ({$handle}.relation_id)", array($value)
					);
				}

				if (empty($statements)) return true;

				if ($filter_join == DataSource::FILTER_OR) {
					$statement = "(\n\t" . implode("\n\tOR ", $statements) . "\n)";
				}

				else {
					$statement = "(\n\t" . implode("\n\tAND ", $statements) . "\n)";
				}

				if ($filter->type == 'is-not') {
					$statement = 'NOT ' . $statement;
				}

				$where[] = $statement;
			}
		}

	/*-------------------------------------------------------------------------
		Grouping:
	-------------------------------------------------------------------------*/

		public function groupRecords($records){
			if(!is_array($records) || empty($records)) return;

			$groups = array($this->{'element-name'} => array());

			foreach($records as $r){
				$data = $r->getData($this->{'id'});
				$value = $data['relation_id'];
				$primary_field = $this->__findPrimaryFieldValueFromRelationID($data['relation_id']);

				if(!isset($groups[$this->{'element-name'}][$value])){
					$groups[$this->{'element-name'}][$value] = array(
						'attr' => array(
							'link-id' => $data['relation_id'],
							'link-handle' => Lang::createHandle($primary_field['value'])),
						'records' => array(),
						'groups' => array()
					);
				}

				$groups[$this->{'element-name'}][$value]['records'][] = $r;
			}

			return $groups;
		}

	/*-------------------------------------------------------------------------
		Sorting:
	-------------------------------------------------------------------------*/

		public function buildSortingQuery(&$joins, &$order){
			$handle = $this->buildSortingJoin($joins);
			$order = "{$handle}.relation_id %1\$s";
		}
	}

	return 'fieldLink';