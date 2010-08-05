<?php

	Class fieldCheckbox extends Field {
		function __construct(){
			parent::__construct();
			$this->_name = __('Checkbox');
		}

		public function create(){
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
					$this->{'section'},
					$this->{'element-name'},
					($this->{'default-state'} == 'on' ? 'yes' : 'no')
				)
			);
		}

		public function canToggleData(){
			return ($this->{'required'} == 'no') ? true : false;
		}

		function allowDatasourceOutputGrouping(){
			return true;
		}

		function isSortable(){
			return true;
		}

		function canFilter(){
			return true;
		}

		public function canImport(){
			return true;
		}

		/*-------------------------------------------------------------------------
			Utilities:
		-------------------------------------------------------------------------*/

		public function getToggleStates(){
			return array('yes' => __('Yes'), 'no' => __('No'));
		}

		/*-------------------------------------------------------------------------
			Settings:
		-------------------------------------------------------------------------*/

		public function findDefaultSettings(&$fields){
			if(!isset($fields['default-state'])) $fields['default-state'] = 'off';
		}

		public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $messages) {
			parent::displaySettingsPanel($wrapper, $messages);

			$document = $wrapper->ownerDocument;

			$options_list = $document->createElement('ul');
			$options_list->setAttribute('class', 'options-list');

			$this->appendShowColumnCheckbox($options_list);
			$this->appendRequiredCheckbox($options_list);

			// Default State
			$label = Widget::Label(__('Checked by default'));
			$input = Widget::Input('default-state', 'on', 'checkbox');

			if ($this->{'default-state'} == 'on') {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$item = $document->createElement('li');
			$item->appendChild($label);
			$options_list->appendChild($item);

			$wrapper->appendChild($options_list);
		}

		/*-------------------------------------------------------------------------
			Publish:
		-------------------------------------------------------------------------*/

		public function prepareTableValue($data, DOMElement $link=NULL){
			return ($data->value == 'yes' ? __('Yes') : __('No'));
		}

		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry = null, $data = null) {
			if(is_null($entry->id) && $this->{'default-state'} == 'on') {
				$value = 'yes';
			}

			else if(is_null($data) && $this->{'required'} == 'yes') {
				$value = null;
 			}
			else if(is_null($data)) {
				## TODO: Don't rely on $_POST
				if(isset($_POST) && !empty($_POST)) $value = 'no';
				elseif($this->{'default-state'} == 'on') $value = 'yes';
				else $value = 'no';
			}

			else $value = ($data->value == 'yes' ? 'yes' : 'no');
			
			$input = Widget::Input('fields['.$this->{'element-name'}.']', 'no', 'hidden');
			$wrapper->appendChild($input);
			
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->{'element-name'}.']', 'yes', 'checkbox', ($value == 'yes' ? array('checked' => 'checked') : array()));

			$label->appendChild($input);
			$label->appendChild(new DOMText((isset($this->{'publish-label'}) && strlen(trim($this->{'publish-label'})) > 0 ? $this->{'publish-label'} : $this->{'name'})));

			if ($errors->valid()) {
				$label = Widget::wrapFormElementWithError($label, $errors->current()->message);
			}
			$wrapper->appendChild($label);
		}

		/*-------------------------------------------------------------------------
			Input:
		-------------------------------------------------------------------------*/

		public function processData($data, Entry $entry=NULL){
			$states = array('on', 'yes');
			
			if (isset($entry->data()->{$this->{'element-name'}})) {
				$result = $entry->data()->{$this->{'element-name'}};
			}
			
			else {
				$result = (object)array(
					'value'	=> null
				);
			}
			
			if (!is_null($data)) {
				if ($this->{'required'} == 'yes' && !in_array(strtolower($data), $states)) {
					$result->value = null;
				}
				
				else {
					$result->value = (in_array(strtolower($data), $states)) ? 'yes' : 'no';
				}
			}
			
			return $result;
		}

		/*-------------------------------------------------------------------------
			Filtering:
		-------------------------------------------------------------------------*/

		public function displayDatasourceFilterPanel(SymphonyDOMElement $wrapper, $data=NULL, MessageStack $errors=NULL){
			parent::displayDatasourceFilterPanel($wrapper, $data, $errors);
			
			$div = $wrapper->ownerDocument->createElement('div');
			
			$label = $wrapper->ownerDocument->xpath('.//label[last()]', $wrapper)->item(0);
			$label->wrapWith($div);
				
			$existing_options = array('yes', 'no');

			$optionlist = $wrapper->ownerDocument->createElement('ul');
			$optionlist->setAttribute('class', 'tags');

			foreach($existing_options as $option) $optionlist->appendChild($wrapper->ownerDocument->createElement('li', $option));

			$div->appendChild($optionlist);
		}

		public function buildFilterQuery($filter, &$joins, array &$where, Register $ParameterOutput=NULL){

			self::$key++;

			$value = DataSource::prepareFilterValue($filter['value'], $ParameterOutput, $operation_type);

			$joins .= sprintf('
				LEFT OUTER JOIN `tbl_data_%2$s_%3$s` AS t%1$s ON (e.id = t%1$s.entry_id)
			', self::$key, $this->section, $this->{'element-name'});

			if ($operation_type == DataSource::FILTER_AND) {
				$clause = NULL;
				foreach ($value as $v) {
					$clause .= sprintf(
						"(t%1\$s.value %2\$s '%3\$s') AND",
						self::$key,
						$filter['type'] == 'is-not' ? '<>' : '=',
						$v
					);
				}
				$where[] = sprintf("(%s)", preg_replace('/AND$/i', NULL, $clause));
			}

			else {
				$where[] = sprintf(
					"(t%1\$s.value %2\$s IN ('%3\$s'))",
					self::$key,
					$filter['type'] == 'is-not' ? 'NOT' : NULL,
					implode("', '", $value)
				);
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

				$value = $data->value;

				if(!isset($groups[$this->{'element-name'}][$handle])){
					$groups[$this->{'element-name'}][$handle] = array('attr' => array('value' => $value),
																		 'records' => array(), 'groups' => array());
				}

				$groups[$this->{'element-name'}][$value]['records'][] = $r;

			}

			return $groups;
		}

	}

	return 'fieldCheckbox';