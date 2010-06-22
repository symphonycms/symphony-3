<?php
	
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	class FieldMemberPassword extends Field {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function __construct() {
			parent::__construct();
			
			$this->_name = 'Member Password';
		}
		
		public function create() {
			return Symphony::Database()->query(sprintf(
				"
					CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
						`entry_id` INT(11) UNSIGNED NOT NULL,
						`code` varchar(13) default NULL,
						`password` varchar(40) default NULL,
						`strength` decimal(4,1) NOT NULL,
						`length` tinyint(4) NOT NULL,
						PRIMARY KEY (`id`),
						KEY `entry_id` (`entry_id`),
						KEY `password` (`password`)
					)
				",
				$this->{'section'},
				$this->{'element-name'}
			));
		}
		
		public function allowDatasourceParamOutput() {
			return true;
		}
		
		public function canFilter() {
			return true;
		}
		
		public function canPrePopulate() {
			return true;
		}
		
		public function isSortable() {
			return true;
		}
		
	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/
		
		public function findDefaultSettings(&$fields) {
			$fields['length'] = '6';
		}
		
		public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $errors) {
			parent::displaySettingsPanel($wrapper, $errors);
			
			$document = $wrapper->ownerDocument;
			
		/*---------------------------------------------------------------------
			Length
		---------------------------------------------------------------------*/
			
			$label = Widget::Label(__('Length'));
			$label->appendChild($document->createElement('em', __('Minimum password length')));
			$input = Widget::Input('length', $this->{'length'});
			$label->appendChild($input);
			
			if ($errors->{'length'}) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'length'});
			}
			
			$wrapper->appendChild($label);
			
		/*---------------------------------------------------------------------
			Options
		---------------------------------------------------------------------*/
			
			$options_list = $document->createElement('ul');
			$options_list->setAttribute('class', 'options-list');
			
			$this->appendShowColumnCheckbox($options_list);
			
			$wrapper->appendChild($options_list);
		}
		
	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/
		
		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry=NULL, $data=NULL) {
			$driver = Extension::load('members');
			$document = $wrapper->ownerDocument;
			$sortorder = $this->{'sortorder'};
			$element_name = $this->{'element-name'};
			
			// Include CSS:
			$document->insertNodeIntoHead($document->createStylesheetElement(
				URL . '/extensions/members/assets/publish.css'
			));
			$document->insertNodeIntoHead($document->createScriptElement(
				URL . '/extensions/members/assets/publish.js'
			));
			
			$name = (
				isset($this->{'publish-label'}) && strlen(trim($this->{'publish-label'})) > 0 
					? $this->{'publish-label'} 
					: $this->name
			);
			
			$label = $document->createElement('div');
			$label->addClass('label');
			$label->setValue($name);
			
			$group = $document->createElement('div');
			$group->addClass('group');
			
			if ($entry->id and $data->code) {
				$input = Widget::Input(
					"fields[$element_name][validate][optional]",
					$driver->createToken($data->code, 'validate'), 'hidden'
				);
				$wrapper->appendChild($input);
				$input = Widget::Input(
					"fields[$element_name][change][optional]",
					$driver->createToken($data->code, 'change'), 'hidden'
				);
				$wrapper->appendChild($input);
			}
			
			// Enter password:
			$div = $document->createElement('div');
			$input = Widget::Input("fields[$element_name][change][password]", (
				isset($data->change->password)
					? $data->change->password
					: null
			));
			$input->setAttribute('placeholder', __('Enter new password…'));
			$div->appendChild($input);
			
			if ($errors->valid() and isset($errors->{'change-password'})) {
				$div = Widget::wrapFormElementWithError($div, $errors->{'change-password'}->message);
			}
			
			$group->appendChild($div);
			
			// Confirm password:
			$div = $document->createElement('div');
			$input = Widget::Input("fields[$element_name][change][confirm]", (
				isset($data->change->confirm)
					? $data->change->confirm
					: null
			));
			$input->setAttribute('placeholder', __('Confirm new password…'));
			$div->appendChild($input);
			
			if ($errors->valid() and isset($errors->{'change-confirm'})) {
				$div = Widget::wrapFormElementWithError($div, $errors->{'change-confirm'}->message);
			}
			
			$group->appendChild($div);
			$label->appendChild($group);
			
			if ($errors->valid()) {
				$label = Widget::wrapFormElementWithError($label, null);
			}
			
			$wrapper->appendChild($label);
		}
		
		public function prepareTableValue(StdClass $data = null, DOMElement $link = null) {
			$template = '';
			
			if ($data->strength <= Extension_Members::PASSWORD_WEAK) $template = 'Weak (%d)';
			else if ($data->strength <= Extension_Members::PASSWORD_GOOD) $template = 'Good (%d)';
			else if ($data->strength <= Extension_Members::PASSWORD_STRONG) $template = 'Strong (%d)';
			
			$data = (object)array(
				'value'	=> __($template, array($data->strength))
			);
			
			return parent::prepareTableValue($data, $link);
		}
		
	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/
		
		public function processData($data, Entry $entry = null) {
			$driver = Extension::load('members');
			
			if (isset($entry->data()->{$this->{'element-name'}})) {
				$result = $entry->data()->{$this->{'element-name'}};
			}
			
			else {
				$result = (object)array(
					'code'		=> null,
					'password'	=> null,
					'strength'	=> 0,
					'length'	=> 0
				);
			}
			
			$result->change = (object)array(
				'optional'		=> false,
				'password'		=> null,
				'confirm'		=> null
			);
			$result->validate = (object)array(
				'optional'		=> false,
				'password'		=> null
			);
			
			// Result is change optional?
			if (
				isset($data['change']['optional'], $result->code)
				and $result->code == $driver->extractToken($data['change']['optional'], 'change')
			) {
				$result->change->optional = true;
			}
			
			// Result is validate optional?
			if (
				isset($data['validate']['optional'], $result->code)
				and $result->code == $driver->extractToken($data['validate']['optional'], 'validate')
			) {
				$result->validate->optional = true;
			}
			
			else if (!isset($result->code)) {
				$result->validate->optional = true;
			}
			
			// Make sure a code is set:
			if (!isset($result->code) or is_null($result->code) or $result->code == '') {
				$result->code = uniqid();
			}
			
			// Change password:
			if (is_array($data) and isset($data['change']['password'], $data['change']['confirm']) and $data['change']['password'] and $data['change']['confirm']) {
				$result->change->password = $data['change']['password'];
				$result->change->confirm = $data['change']['confirm'];
				$result->length = strlen($data['change']['password']);
				$result->strength = $driver->checkPasswordStrength($data['change']['password']);
			}
			
			// Validate password:
			if (is_array($data) and isset($data['validate']['password']) and $data['validate']['password']) {
				$result->validate->password = $data['validate']['password'];
			}
			
			// If the change password data is not empty, treat it as required:
			if ($result->change->password != '' or $result->change->confirm != '') {
				$result->change->optional = false;
			}
			
			// If the validate password data is not empty, treat it as required:
			if ($result->validate->password != '') {
				$result->validate->optional = false;
			}
			
			return $result;
		}
		
		public function validateData(MessageStack $errors, Entry $entry, $data = null) {
			$change = $data->change;
			$validate = $data->validate;
			$status = self::STATUS_OK;
			
			// Incorrect validation password:
			if (!$validate->optional and $data->password != sha1($validate->password)) {
				$errors->append(
					'validate-password', (object)array(
					 	'message' => __("Please enter your current password."),
						'code' => self::ERROR_INVALID
					)
				);
				
				$status = self::STATUS_ERROR;
			}
			
			// Missing new password:
			if (!$change->optional and $change->password == '') {
				$errors->append(
					'change-password', (object)array(
					 	'message' => __("Please enter a new password."),
						'code' => self::ERROR_MISSING
					)
				);
				
				$status = self::STATUS_ERROR;
			}
			
			// Missing new password confirmation:
			if (!$change->optional and $change->confirm == '') {
				$errors->append(
					'change-confirm', (object)array(
					 	'message' => __("Please confirm your new password."),
						'code' => self::ERROR_MISSING
					)
				);
				
				$status = self::STATUS_ERROR;
			}
			
			// New passwords do not match:
			if (!$change->optional and $change->password != $change->confirm) {
				$errors->append(
					'change-password', (object)array(
					 	'message' => __("The passwords you entered to not match."),
						'code' => self::ERROR_INVALID
					)
				);
				
				$status = self::STATUS_ERROR;
			}
			
			return $status;
		}
		
		public function saveData(MessageStack $errors, Entry $entry, $data = null) {
			// Don't save if we don't need to:
			if (isset($data->optional) and $data->optional) {
				return self::STATUS_OK;
			}
			
			$data->entry_id = $entry->id;
			
			if (!isset($data->id)) $data->id = null;
			
			// Set new password value:
			if (isset($data->change->password) and $data->change->password) {
				$data->password = sha1($data->change->password);
			}
			
			// Remove validation data:
			unset($data->change, $data->validate);
			
			try {
				Symphony::Database()->insert(
					sprintf('tbl_data_%s_%s', $entry->section, $this->{'element-name'}),
					(array)$data,
					Database::UPDATE_ON_DUPLICATE
				);
				
				return self::STATUS_OK;
			}
			
			catch (Exception $e) {
				return self::STATUS_ERROR;
			}
		}
		
	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/
		
		public function appendFormattedElement(&$wrapper, $data, $mode = null) {
			$driver = Extension::load('members');
			$document = $wrapper->ownerDocument;
			
			$result = $document->createElement($this->{'element-name'});
			$result->setAttribute('length', $data->length);
			$result->setAttribute('strength', $data->strength);
			
			$activate = $document->createElement('activate');
			$activate->setValue($driver->createToken($data->code, 'activate'));
			$result->appendChild($activate);
			
			$change = $document->createElement('change');
			$change->setValue($driver->createToken($data->code, 'change'));
			$result->appendChild($change);
			
			$login = $document->createElement('login');
			$login->setValue($driver->createToken($data->code, 'login'));
			$result->appendChild($login);
			
			$validate = $document->createElement('validate');
			$validate->setValue($driver->createToken($data->code, 'validate'));
			$result->appendChild($validate);
			
			$value = $document->createElement('value');
			$value->setValue($data->password);
			$result->appendChild($value);
			
			$wrapper->appendChild($result);
		}
		
		public function getParameterOutputValue(StdClass $data, Entry $entry = null) {
			return $data->handle;
		}
		
	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/
		
		public function buildFilterQuery($filter, &$joins, array &$where, Register $parameter_output) {
			$driver = Extension::load('members');
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
						"{$handle}.password = '%s' OR {$handle}.code = '%s'",
						array(sha1($value), $driver->extractToken($value, 'login'))
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
			
			return true;
		}
	}

	return 'FieldMemberPassword';