<?php

	Class fieldCheckbox extends Field {
		function __construct(){
			parent::__construct();
			$this->_name = __('Checkbox');
		}

		function canToggle(){
			return true;
		}

		function allowDatasourceOutputGrouping(){
			return true;
		}

		function groupRecords($records){

			if(!is_array($records) || empty($records)) return;

			$groups = array($this->properties()->element_name => array());

			foreach($records as $r){
				$data = $r->getData($this->properties()->id);

				$value = $data['value'];

				if(!isset($groups[$this->properties()->element_name][$handle])){
					$groups[$this->properties()->element_name][$handle] = array('attr' => array('value' => $value),
																		 'records' => array(), 'groups' => array());
				}

				$groups[$this->properties()->element_name][$value]['records'][] = $r;

			}

			return $groups;
		}

		function canFilter(){
			return true;
		}

		public function canImport(){
			return true;
		}

		function getToggleStates(){
			return array('yes' => __('Yes'), 'no' => __('No'));
		}

		function toggleFieldData($data, $newState){
			$data['value'] = $newState;
			return $data;
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){

			$status = self::STATUS_OK;

			return array(
				'value' => (strtolower($data) == 'yes' || strtolower($data) == 'on' ? 'yes' : 'no')
			);

		}

		function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
			$joins .= "LEFT OUTER JOIN `tbl_entries_data_".$this->properties()->id."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort = 'ORDER BY ' . (in_array(strtolower($order), array('random', 'rand')) ? 'RAND()' : "`ed`.`value` $order");
		}


		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->properties()->id;

			if ($andOperation) {
				foreach ($data as $value) {
					self::$key++;
					$value = $this->escape($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
							ON (e.id = t{$field_id}_{self::$key}.entry_id)
					";
					$where .= "
						AND (t{$field_id}_{self::$key}.value = '{$value})'
					";
				}

			} else {
				if (!is_array($data)) $data = array($data);

				foreach ($data as &$value) {
					$value = $this->escape($value);
				}

				self::$key++;
				$data = implode("', '", $data);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
						ON (e.id = t{$field_id}_{self::$key}.entry_id)
				";
				$where .= "
					AND (t{$field_id}_{self::$key}.value IN ('{$data}'))
				";
			}

			return true;
		}

		function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL){

			parent::displayDatasourceFilterPanel($wrapper, $data, $errors);

			$existing_options = array('yes', 'no');

			if(is_array($existing_options) && !empty($existing_options)){
				$optionlist = Symphony::Parent()->Page->createElement('ul');
				$optionlist->setAttribute('class', 'tags');

				foreach($existing_options as $option) $optionlist->appendChild(Symphony::Parent()->Page->createElement('li', $option));

				$wrapper->appendChild($optionlist);
			}

		}

		function displayPublishPanel(DOMElement $wrapper, $data=NULL, $flagWithError=NULL, $entry_id=NULL){

			if(!$data){
				## TODO: Don't rely on $_POST
				if(isset($_POST) && !empty($_POST)) $value = 'no';
				elseif($this->properties()->default_state == 'on') $value = 'yes';
				else $value = 'no';
			}

			else $value = ($data['value'] == 'yes' ? 'yes' : 'no');

			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->properties()->element_name.']', 'yes', 'checkbox', ($value == 'yes' ? array('checked' => 'checked') : array()));

			$label->appendChild($input);
			$label->appendChild(new DOMText(($this->properties()->description != NULL ? $this->properties()->description : $this->properties()->label)));

			$wrapper->appendChild($label);
		}

		function prepareTableValue($data, SymphonyDOMElement $link=NULL){
			return ($data['value'] == 'yes' ? __('Yes') : __('No'));
		}

		function isSortable(){
			return true;
		}

		function commit(){

			if(!parent::commit()) return false;

			$field_id = $this->properties()->id;
			$handle = $this->handle();

			if($field_id === false) return false;

			$fields = array(
				'field_id' => $field_id,
				'default_state' => ($this->properties()->default_state ? $this->properties()->default_state : 'off'),
				'description' => (trim($this->properties()->description) != '') ? $this->properties()->description : NULL
			);

			Symphony::Database()->delete('tbl_fields_' . $handle, array($field_id), "`field_id` = %d LIMIT 1");
			$field_id = Symphony::Database()->insert('tbl_fields_' . $handle, $fields);

			return ($field_id == 0 || !$field_id) ? false : true;
		}

		public function findDefaults(array &$fields){
			if(!isset($fields['default_state'])) $fields['default_state'] = 'off';
		}

		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			## Long Description
			$label = Widget::Label(__('Long Description'));
			$label->appendChild(Symphony::Parent()->Page->createElement('i', __('Optional')));
			$label->appendChild(Widget::Input('description', $this->properties()->description));
			$wrapper->appendChild($label);

			$options_list = Symphony::Parent()->Page->createElement('ul');
			$options_list->setAttribute('class', 'options-list');

			## Checkbox Default State
			$label = Widget::Label();
			$input = Widget::Input('default_state', 'on', 'checkbox');
			if($this->properties()->default_state == 'on') $input->setAttribute('checked', 'checked');
			$label->appendChild($input);
			$label->setValue(__('Checked by default'));

			$options_list->appendChild($label);

			$this->appendShowColumnCheckbox($options_list);

			$wrapper->appendChild($options_list);


		}

		public function createTable(){
			return Symphony::Database()->query(
				sprintf(
					"CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`entry_id` int(11) unsigned NOT NULL,
						`value` enum('yes','no') NOT NULL default '%s',
						PRIMARY KEY  (`id`),
						KEY `entry_id` (`entry_id`),
						KEY `value` (`value`)
					) TYPE=MyISAM;",
					$this->properties()->section,
					$this->properties()->element_name,
					($this->properties()->default_state == 'on' ? 'yes' : 'no')
				)
			);
		}

		public function getExampleFormMarkup(){
			$label = Widget::Label($this->properties()->label);
			$label->appendChild(Widget::Input('fields['.$this->properties()->element_name.']', NULL, 'checkbox', ($this->properties()->default_state == 'on' ? array('checked' => 'checked') : array())));

			return $label;
		}

	}

	return 'fieldCheckbox';