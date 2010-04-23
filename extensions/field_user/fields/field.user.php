<?php

	Class fieldUser extends Field {
		function __construct(){
			parent::__construct();
			$this->_name = __('User');
		}

		public function isSortable(){
			return ($this->{'allow-multiple-selection'} == 'yes' ? false : true);
		}

		public function buildSortingSQL(&$joins, &$order, $direction = 'ASC'){
			$joins .= "
						LEFT OUTER JOIN
							`tbl_data_%s_%s` AS d ON (e.`id` = d.`entry_id`)
						JOIN
							`tbl_users` AS u ON (d.`user_id` = u.`id`)
						";
			$order = sprintf('ORDER BY u.`first_name` %1$s , u.`last_name` %1$s', $direction);
		}

		public function canFilter(){
			return true;
		}

		public function canImport(){
			return true;
		}

		public function canToggleData(){
			return ($this->{'allow-multiple-selection'} == 'yes' ? false : true);
		}

		public function allowDatasourceOutputGrouping(){
			## Grouping follows the same rule as toggling.
			return $this->canToggle();
		}

		public function getToggleStates(){

		    $users = UserManager::fetch();

			$states = array();
			foreach($users as $u){
				$states[$u->id] = $u->getFullName();
			}

			return $states;
		}

		public function toggleEntryData(StdClass $data, $value, Entry $entry=NULL){
			$data['user_id'] = $newState;
			return $data;
		}

/*
		Deprecated

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){

			$status = self::STATUS_OK;

			if(!is_array($data) && !is_null($data)) return array('user_id' => $data);

			if(empty($data)) return NULL;

			$result = array();
			foreach($data as $id) $result['user_id'][] = $id;

			return $result;
		}

*/
		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $error, Entry $entry = null, $data = null) {

			if(!is_array($data)){
				$data = array($data);
			}

			$selected = array();
			foreach($data as $d){
				if(!($d instanceof StdClass) || !isset($d->user_id)) continue;
				$selected[] = $d->user_id;
			}

			//$callback = Administration::instance()->getPageCallback();

			if ($this->{'default-to-current-user'} == 'yes' && is_null($data)) {
				$selected[] = Administration::instance()->User->id;
			}

		    $users = UserManager::fetch();

			$options = array();

			if($this->{'required'} == 'yes') {
				$options[] = array(null, false);
			}

			foreach($users as $u){
				$options[] = array($u->id, in_array($u->id, $selected), General::sanitize($u->getFullName()));
			}

			$fieldname = 'fields['.$this->{'element-name'}.']';
			if($this->{'allow-multiple-selection'} == 'yes') $fieldname .= '[]';

			$label = Widget::Label($this->label);
			$label->appendChild(Widget::Select($fieldname, $options,
				($this->{'allow-multiple-selection'} == 'yes') ? array('multiple' => 'multiple') : array()
			));

			if (!is_null($error)) {
				$label = Widget::wrapFormElementWithError($label, $error['message']);
			}

			$wrapper->appendChild($label);
		}

		public function prepareTableValue($data, DOMElement $link=NULL){

			if(!is_array($data)){
				$data = array($data);
			}

			$values = array();
			foreach($data as $d){
				$values[] = $d->user_id;
			}

			$fragment = Symphony::Parent()->Page->createDocumentFragment();

			foreach($values as $user_id){
				if(is_null($user_id)) continue;

				$user = new User($user_id);

				if($user instanceof User){
					if($fragment->hasChildNodes()) $fragment->appendChild(new DOMText(', '));

					if(is_null($link)){
						$fragment->appendChild(
							Widget::Anchor(
								General::sanitize($user->getFullName()),
								ADMIN_URL . '/system/users/edit/' . $user->get('id') . '/'
							)
						);
					}

					else {
						$link->setValue($user->getFullName());
						$fragment->appendChild($link);
					}
				}
			}

			return (!$fragment->hasChildNodes()) ? __('None') : $fragment;
		}

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->id;

			if (self::isFilterRegex($data[0])) {
				self::$key++;
				$pattern = str_replace('regexp:', '', $this->escape($data[0]));
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
						ON (e.id = t{$field_id}_{self::$key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{self::$key}.user_id REGEXP '{$pattern}'
				";

			} elseif ($andOperation) {
				foreach ($data as $value) {
					self::$key++;
					$value = $this->escape($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
							ON (e.id = t{$field_id}_{self::$key}.entry_id)
					";
					$where .= "
						AND t{$field_id}_{self::$key}.user_id = '{$value}'
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
					AND t{$field_id}_{self::$key}.user_id IN ('{$data}')
				";
			}

			return true;
		}

/*
		Deprecated

		public function commit(){

			if(!parent::commit()) return false;

			$field_id = $this->id;
			$handle = $this->handle();

			if($field_id === false) return false;

			$fields = array(
				'field_id' => $field_id,
				'allow-multiple-selection' => ($this->{'allow-multiple-selection'} ? $this->{'allow-multiple-selection'} : 'no'),
				'default-to-current-user' => ($this->{'default-to-current-user'} ? $this->{'default-to-current-user'} : 'no')
			);

			Symphony::Database()->delete('tbl_fields_' . $handle, array($field_id), "`field_id` = %d LIMIT 1");
			$field_id = Symphony::Database()->insert('tbl_fields_' . $handle, $fields);

			return ($field_id == 0 || !$field_id) ? false : true;
		}
*/
		public function appendFormattedElement(&$wrapper, $data, $encode=false){
	        if(!is_array($data['user_id'])) $data['user_id'] = array($data['user_id']);

	        $list = $wrapper->ownerDocument->createElement($this->{'element-name'});
	        foreach($data['user_id'] as $user_id){
	            $user = new User($user_id);
	            $list->appendChild(
					$wrapper->ownerDocument->createElement('item', $user->getFullName(), array(
						'id' => $user->id,
						'username' => $user->username
					))
				);
	        }
	        $wrapper->appendChild($list);
	    }

		public function findDefaults(&$fields){
			if(!isset($fields['allow-multiple-selection'])) $fields['allow-multiple-selection'] = 'no';
		}

		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			$document = $wrapper->ownerDocument;

			$options_list = $document->createElement('ul');
			$options_list->setAttribute('class', 'options-list');

			$this->appendShowColumnCheckbox($options_list);
			$this->appendRequiredCheckbox($options_list);

			## Allow multiple selection
			$label = Widget::Label(__('Allow selection of multiple users'));
			$input = Widget::Input('allow-multiple-selection', 'yes', 'checkbox');
			if($this->{'allow-multiple-selection'} == 'yes') $input->setAttribute('checked', 'checked');

			$label->prependChild($input);
			$item = $document->createElement('li');
			$item->appendChild($label);
			$options_list->appendChild($item);

			## Default to current logged in user
			$label = Widget::Label(__('Select current user by default'));
			$input = Widget::Input('default-to-current-user', 'yes', 'checkbox');
			if($this->{'default-to-current-user'} == 'yes') $input->setAttribute('checked', 'checked');

			$label->prependChild($input);
			$item = $document->createElement('li');
			$item->appendChild($label);
			$options_list->appendChild($item);

			$wrapper->appendChild($options_list);

		}

		public function processFormData($data, Entry $entry=NULL){

			$result = (object)array(
				'user_id' => NULL
			);

			$result->user_id = $data;

			return $result;
		}

		public function validateData(MessageStack $errors, Entry $entry = null, $data = null) {

			if(!is_array($data)) {
				$data = array($data);
			}

			if ($this->required == 'yes' && (!isset($data[0]->user_id) || strlen(trim($data[0]->user_id)) == 0)){
				$errors->append(
					$this->{'element-name'},
					(object)array(
					 	'message' => __("'%s' is a required field.", array($this->label)),
						'code' => self::ERROR_MISSING
					)
				);
				return self::STATUS_ERROR;
			}
			return self::STATUS_OK;
		}

		public function saveData(MessageStack $errors, Entry $entry, $data = null) {
			// Since we are dealing with multiple
			// values, must purge the existing data first
			Symphony::Database()->delete(
				sprintf('tbl_data_%s_%s', $entry->section, $this->{'element-name'}),
				array($entry->id),
				"`entry_id` = %s"
			);

			if(!is_array($data)){
				$data = array($data);
			}
			foreach($data as $d){
				parent::saveData($d, $errors, $entry);
			}
			return Field::STATUS_OK;
		}

		public function loadDataFromDatabase(Entry $entry, $expect_multiple = false){
			return parent::loadDataFromDatabase($entry, true);
		}

		public function create(){
			return Symphony::Database()->query(
				sprintf(
					'CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`entry_id` int(11) unsigned NOT NULL,
						`user_id` int(11) unsigned NOT NULL,
						PRIMARY KEY  (`id`),
						KEY `entry_id` (`entry_id`),
						KEY `user_id` (`user_id`)
					)',
					$this->section,
					$this->{'element-name'}
				)
			);
		}

		public function getExampleFormMarkup(){

		    $users = UserManager::fetch();

			$options = array();

			foreach($users as $u){
				$options[] = array($u->id, NULL, $u->getFullName());
			}

			$fieldname = 'fields['.$this->{'element-name'}.']';
			if($this->{'allow-multiple-selection'} == 'yes') $fieldname .= '[]';

			$attr = array();

			if($this->{'allow-multiple-selection'} == 'yes') $attr['multiple'] = 'multiple';

			$label = Widget::Label($this->label);
			$label->appendChild(Widget::Select($fieldname, $options, $attr));

			return $label;
		}


	}

	return 'fieldUser';