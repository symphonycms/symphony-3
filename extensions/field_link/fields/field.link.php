<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

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
				$section = Section::loadFromHandle($section_handle);
				$group = array('name' => $section->name, 'section' => $section->handle, 'values' => array());
				
				$join = NULL;
				$order = ' e.id DESC ';
				if(isset($section->{'publish-order-handle'}) && strlen($section->{'publish-order-handle'}) > 0) {
					
					$sort_field = $section->fetchFieldByHandle($section->{'publish-order-handle'});
					if($sort_field instanceof Field){
						$sort_field->buildSortingSQL($join, $order);

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
			
			// find the sections of the related fields
			$sections = Symphony::Database()->fetch("SELECT DISTINCT (s.id), s.name, f.id as `field_id`
				 								FROM `tbl_sections` AS `s`
												LEFT JOIN `tbl_fields` AS `f` ON `s`.id = `f`.parent_section
												WHERE `f`.id IN ('" . implode("','", $this->{'related-field-id'}) . "')
												ORDER BY s.sortorder ASC");

			if(is_array($sections) && !empty($sections)){
				foreach($sections as $section){

					$group = array('name' => $section['name'], 'section' => $section['id'], 'values' => array());

					// build a list of entry IDs with the correct sort order
					$entries = EntryManager::instance()->fetch(NULL, $section['id'], $limit, 0);

					$results = array();
					foreach($entries as $entry) $results[] = $entry->{'id'};

					// if a value is already selected, ensure it is added to the list (if it isn't in the available options)
					if(!is_null($existing_selection) && !empty($existing_selection)){
						foreach($existing_selection as $key => $entry_id){
							$x = $this->findFieldIDFromRelationID($entry_id);
							if($x == $section['field_id']) $results[] = $entry_id;
						}
					}

					if(is_array($results) && !empty($results)){
						foreach($results as $entry_id){
							$value = $this->__findPrimaryFieldValueFromRelationID($entry_id);
							$group['values'][$entry_id] = $value['value'];
						}
					}

					$values[] = $group;
				}
			}

			return $values;
		}

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
				if(!is_array($section->fields) || $section->handle == $document->_context[1]) continue;

				$fields = array();

				foreach($section->fields as $field) {
					if($field->canPrePopulate()) {
						$fields[] = array(
							$section->handle . '::' .$field->{'element-name'},
							(isset($this->{'related-fields'}["{$section->handle}::" . $field->{'element-name'}])),
							$field->label
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

			$label = Widget::Label($this->{'label'});
			$label->appendChild(Widget::Select($fieldname, $options, ($this->{'allow-multiple-selection'} == 'yes' ? array('multiple' => 'multiple') : NULL)));
			
			if ($errors->valid()) {
				$label = Widget::wrapFormElementWithError($label, $errors->current()->message);
			}
			
			$wrapper->appendChild($label);
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		public function loadDataFromDatabase(Entry $entry, $expect_multiple = false){
			return parent::loadDataFromDatabase($entry, $this->{'allow-multiple-selection'} == 'yes');
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

		public function validateData(MessageStack $errors, Entry $entry=NULL, $data=NULL){
			
			if ($this->required == 'yes' && empty($data)){
				$errors->append(
					null, (object)array(
					 	'message' => __("'%s' is a required field.", array($this->label)),
						'code' => self::ERROR_MISSING
					)
				);
				return self::STATUS_ERROR;
			}
			return self::STATUS_OK;
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			$status = self::STATUS_OK;
			if(!is_array($data)) return array('relation_id' => $data);

			if(empty($data)) return NULL;

			$result = array();

			foreach($data as $a => $value) {
			  $result['relation_id'][] = $data[$a];
			}

			return $result;
		}

		public function processData($data, Entry $entry=NULL){

			//if(isset($entry->data()->{$this->{'element-name'}})){
			//	$result = $entry->data()->{$this->{'element-name'}};
			//}
			
			//else {
				$result = NULL;
			//}

			if(!is_null($data)){
				if(!is_array($data)) $data = array($data);
				$result = array();
				foreach($data as $id){
					$result[] = (object)array(
						'relation_id' => $id
					);
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


	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

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

		public function appendFormattedElement(&$wrapper, $data, $encode=false){

			if(!is_array($data) || empty($data)) return;

			$list = $wrapper->ownerDocument->createElement($this->{'element-name'});

			foreach($data as $d){
				
				$entry = Entry::loadFromID($d->relation_id);

				foreach($this->{'related-fields'} as $key => $value){
					$item = $wrapper->ownerDocument->createElement('item');
					list($section_handle, $field_handle) = $value;
					
					if($section_handle != $entry->section) continue;
					
					$section = Section::loadFromHandle($entry->section);
					$related_field = $section->fetchFieldByHandle($field_handle);
					//var_dump($entry->data()->$field_handle); die();
					$related_field->appendFormattedElement($item, $entry->data()->$field_handle);
					
					$item->setAttribute('id', $d->relation_id);
					$item->setAttribute('section-handle', $section_handle);
					$item->setAttribute('section-name', $section->name);
					
//					var_dump($related_field); die();
					$list->appendChild($item);
				}
				/*
				die("hmm");
				$primary_field = $this->__findPrimaryFieldValueFromRelationID($relation_id);

				$value = $primary_field['value'];
				if ($encode) $value = General::sanitize($value);

				$item = new XMLElement('item');
				$item->setAttribute('id', $relation_id);
				$item->setAttribute('handle', Lang::createHandle($primary_field['value']));
				$item->setAttribute('section-handle', $primary_field['section_handle']);
				$item->setAttribute('section-name', General::sanitize($primary_field['section_name']));
				$item->setValue(General::sanitize($value));

				$list->appendChild($item);*/
			}

			$wrapper->appendChild($list);
		}

		public function prepareTableValue($data, DOMElement $link=NULL){
			$result = array();

			if(!is_array($data) || (is_array($data) && !isset($data['relation_id']))) return parent::prepareTableValue(NULL);

			if(!is_array($data['relation_id'])){
				$data['relation_id'] = array($data['relation_id']);
			}

			foreach($data['relation_id'] as $relation_id){
				if((int)$relation_id <= 0) continue;

				$primary_field = $this->__findPrimaryFieldValueFromRelationID($relation_id);

				if(!is_array($primary_field) || empty($primary_field)) continue;

				$result[$relation_id] = $primary_field;
			}

			if(!is_null($link)){
				$label = NULL;
				foreach($result as $item){
					$label .= ' ' . $item['value'];
				}
				$link->setValue(General::sanitize(trim($label)));
				return $link->generate();
			}

			$output = NULL;

			foreach($result as $relation_id => $item){
				$link = Widget::Anchor($item['value'], sprintf('%s/symphony/publish/%s/edit/%d/', URL, $item['section_handle'], $relation_id));
				$output .= $link->generate() . ' ';
			}

			return trim($output);
		}

		public function getParameterPoolValue($data){
			return $data['relation_id'];
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
		
		public function buildFilterQuery($filter, &$joins, &$where, Register $ParameterOutput=NULL){

			self::$key++;

			$value = DataSource::prepareFilterValue($filter['value'], $ParameterOutput, $filterOperationType);

			$joins .= sprintf('
				LEFT OUTER JOIN `tbl_data_%2$s_%3$s` AS t%1$s ON (e.id = t%1$s.entry_id)
			', self::$key, $this->section, $this->{'element-name'});

			if ($filterOperationType == DataSource::FILTER_AND) {
				foreach ($value as $v) {
					$where .= sprintf(
						" AND (t%1\$s.relation_id %2\$s '%3\$s') ",
						self::$key,
						$filter['type'] == 'is-not' ? '<>' : '=',
						$v
					);
				}

			}

			else {
				$where .= sprintf(
					" AND (t%1\$s.relation_id %2\$s IN ('%3\$s')) ",
					self::$key,
					$filter['type'] == 'is-not' ? 'NOT' : NULL,
					implode("', '", $value)
				);
			}

			return true;
		
			// OLD CODE ------

			$field_id = $this->{'id'};

			if(preg_match('/^sql:\s*/', $data[0], $matches)) {
				$data = trim(array_pop(explode(':', $data[0], 2)));

				if(strpos($data, "NOT NULL") !== false) {

					$joins .= " LEFT JOIN
									`tbl_entries_data_{$field_id}` AS `t{$field_id}`
								ON (`e`.`id` = `t{$field_id}`.entry_id)";
					$where .= " AND `t{$field_id}`.relation_id IS NOT NULL ";

				} else if(strpos($data, "NULL") !== false) {

					$joins .= " LEFT JOIN
									`tbl_entries_data_{$field_id}` AS `t{$field_id}`
								ON (`e`.`id` = `t{$field_id}`.entry_id)";
					$where .= " AND `t{$field_id}`.relation_id IS NULL ";

				}
			}

			else{
				foreach($data as $key => &$value) {
					// for now, I assume string values are the only possible handles.
					// of course, this is not entirely true, but I find it good enough.
					if(!is_numeric($value)){

						$related_field_id = $this->{'related-field-id'};

						if(is_array($related_field_id) && !empty($related_field_id)) {
							$return = Symphony::Database()->fetchCol("id", sprintf(
								"SELECT
									`entry_id` as `id`
								FROM
									`tbl_entries_data_%d`
								WHERE
									`handle` = '%s'
								LIMIT 1", $related_field_id[0], Lang::createHandle($value)
							));

							// Skipping returns wrong results when doing an AND operation, return 0 instead.
							if(empty($return)){
								$value = 0;
							}

							else{
								$value = $return[0];
							}
						}

					}
				}

				if($andOperation):
					foreach($data as $key => $bit){
						$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
						$where .= " AND `t$field_id$key`.relation_id = '$bit' ";
					}
				else:
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
					$where .= " AND `t$field_id`.relation_id IN ('".@implode("', '", $data)."') ";
				endif;

			}

			return true;
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

	/*-------------------------------------------------------------------------
		Junk:
	-------------------------------------------------------------------------*/

		public function set($field, $value){
			if($field == 'related-field-id' && !is_array($value)){
				$value = explode(',', $value);
			}
			$this->_fields[$field] = $value;
		}

		public function setArray($array){
			if(empty($array) || !is_array($array)) return;
			foreach($array as $field => $value) $this->{$field} = $value;
		}
	}
	
	return 'fieldLink';