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

			// Long Description
			$label = Widget::Label(__('Long Description'));
			$label->appendChild($document->createElement('em', __('Optional')));
			$label->appendChild(Widget::Input('description', $this->{'description'}));
			$wrapper->appendChild($label);

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

			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->{'element-name'}.']', 'yes', 'checkbox', ($value == 'yes' ? array('checked' => 'checked') : array()));

			$label->appendChild($input);
			$label->appendChild(new DOMText(($this->{'description'} != NULL ? $this->{'description'} : $this->{'label'})));

			if ($errors->valid()) {
				$label = Widget::wrapFormElementWithError($label, $errors->current()->message);
			}
			$wrapper->appendChild($label);
		}

		/*-------------------------------------------------------------------------
			Input:
		-------------------------------------------------------------------------*/

		public function processFormData($data, Entry $entry=NULL){
			$states = array('on', 'yes');

			if($this->{'required'} == 'yes' && !in_array(strtolower($data), $states)) {
				$data = null;
			}
			else $data = (in_array(strtolower($data), $states)) ? 'yes' : 'no';

   			return parent::processFormData($data, $entry);
		}

		/*-------------------------------------------------------------------------
			Filtering:
		-------------------------------------------------------------------------*/

		public function displayDatasourceFilterPanel(SymphonyDOMElement $wrapper, $data=NULL, MessageStack $errors=NULL){
			$document = $wrapper->ownerDocument;

			$name = $document->createElement('span', $this->label);
			$name->setAttribute('class', 'name');
			$name->appendChild($document->createElement('em', $this->name()));
			$wrapper->appendChild($name);

			$group = $document->createElement('div');
			$group->setAttribute('class', 'group');

			$label = Widget::Label(__('Type'));
			$label->setAttribute('class', 'small');
			$label->appendChild(Widget::Select(
				sprintf('fields[filters][%s][type]', $this->{'element-name'}),
				array(
					array('is', false, 'Is'),
					array('is-not', $data['type'] == 'is-not', 'Is not')
				)
			));
			$group->appendChild($label);

			$div = $document->createElement('div');

			$label = Widget::Label(__('Value'));
			$label->appendChild(Widget::Input(
				sprintf('fields[filters][%s][value]', $this->{'element-name'}),
				$data['value']
			));

			$existing_options = array('yes', 'no');

			$optionlist = $document->createElement('ul');
			$optionlist->setAttribute('class', 'tags');

			foreach($existing_options as $option) $optionlist->appendChild($document->createElement('li', $option));

			$div->appendChild($label);
			$div->appendChild($optionlist);

			$group->appendChild($div);

			$wrapper->appendChild($group);

		}

		public function buildDSRetrivalSQL($filter, &$joins, &$where, Register $ParameterOutput=NULL){

			self::$key++;

			$value = DataSource::prepareFilterValue($filter['value'], $ParameterOutput, $operation_type);

			$joins .= sprintf('
				LEFT OUTER JOIN `tbl_data_%2$s_%3$s` AS t%1$s ON (e.id = t%1$s.entry_id)
			', self::$key, $this->section, $this->{'element-name'});

			if ($operation_type == DataSource::FILTER_AND) {
				foreach ($value as $v) {
					$where .= sprintf(
						" AND (t%1\$s.value %2\$s '%3\$s') ",
						self::$key,
						$filter['type'] == 'is-not' ? '<>' : '=',
						$v
					);
				}

			}

			else {
				$where .= sprintf(
					" AND (t%1\$s.value %2\$s IN ('%3\$s')) ",
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