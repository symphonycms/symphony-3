<?php
	
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	class FieldMemberName extends Field {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function __construct() {
			parent::__construct();
			
			$this->_name = 'Member Name';
		}
		
		public function create() {
			return Symphony::Database()->query(sprintf(
				"
					CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
						`entry_id` INT(11) UNSIGNED NOT NULL,
						`handle` VARCHAR(255) DEFAULT NULL,
						`value` TEXT DEFAULT NULL,
						`value_formatted` TEXT DEFAULT NULL,
						PRIMARY KEY (`id`),
						KEY `entry_id` (`entry_id`),
						FULLTEXT KEY `value` (`value`),
						FULLTEXT KEY `value_formatted` (`value_formatted`)
					)
				",
				$this->{'section'},
				$this->{'element-name'}
			));
		}
		
		public function allowDatasourceOutputGrouping() {
			return true;
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
			$fields['text-handle'] = 'yes';
		}
		
		public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $errors) {
			parent::displaySettingsPanel($wrapper, $errors);
			
			$document = $wrapper->ownerDocument;
			
		/*---------------------------------------------------------------------
			Text Formatter
		---------------------------------------------------------------------*/
			
			$this->appendFormatterSelect(
				$wrapper, $this->{'text-formatter'}, 'text-formatter'
			);
			
		/*---------------------------------------------------------------------
			Validator
		---------------------------------------------------------------------*/
			
			$this->appendValidationSelect(
				$wrapper, $this->{'text-validator'}, 'text-validator'
			);

		/*---------------------------------------------------------------------
			Options
		---------------------------------------------------------------------*/
			
			$options_list = $document->createElement('ul');
			$options_list->setAttribute('class', 'options-list');
			
			$this->appendShowColumnCheckbox($options_list);
			$this->appendRequiredCheckbox($options_list);
			
			$label = Widget::Label(__('Output with handles'));
			$input = Widget::Input('text-handle', 'yes', 'checkbox');
			
			if ($this->{'text-handle'} == 'yes') {
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
		
		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry=NULL, $data=NULL) {
			$document = $wrapper->ownerDocument;
			$sortorder = $this->{'sortorder'};
			$element_name = $this->{'element-name'};
			$classes = array();
			
			$label = Widget::Label(
				(isset($this->{'publish-label'}) && strlen(trim($this->{'publish-label'})) > 0 
					? $this->{'publish-label'} 
					: $this->name)
			);
			
			if ($optional) $label->appendChild(
				$wrapper->ownerDocument->createElement('em', __('Optional'))
			);
			
			// Input box:
			$input = Widget::Input("fields[$element_name]", $data->value);
			
			// Add classes:
			if ($this->{'text-formatter'} != 'none') {
				$classes[] = $this->{'text-formatter'};
			}
			
			$input->setAttribute('class', implode(' ', $classes));
			$input->setAttribute('length', (integer)$this->{'text-length'});
			$label->appendChild($input);
			
			if ($errors->valid()) {
				$label = Widget::wrapFormElementWithError($label, $errors->current()->message);
			}
			
			$wrapper->appendChild($label);
		}
		
	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/
		
		public function validateData(MessageStack $errors, Entry $entry, $data = null) {
			if (!isset($data->value) or strlen(trim($data->value)) == 0) {
				$errors->append(
					null, (object)array(
					 	'message' => __("'%s' is a required field.", array($this->{'name'})),
						'code' => self::ERROR_MISSING
					)
				);
				
				return self::STATUS_ERROR;
			}
			
			if (!$this->applyValidationRules($data->value)) {
				$errors->append(
					null, (object)array(
					 	'message' => __("'%s' contains invalid data. Please check the contents.", array($this->{'publish-label'})),
						'code' => self::ERROR_INVALID
					)
				);
				
				return self::STATUS_ERROR;
			}
			
			return self::STATUS_OK;
		}
		
		public function applyFormatting($value) {
			if (isset($this->{'text-formatter'}) and $this->{'text-formatter'} != TextFormatter::NONE) {
				try {
					$formatter = TextFormatter::loadFromHandle($this->{'text-formatter'});
					$result = $formatter->run($value);
					$result = preg_replace('/&(?![a-z]{0,4}\w{2,3};|#[x0-9a-f]{2,6};)/i', '&amp;', $result);
					
					return $result;
				}
				
				catch (Exception $e) {
					// Problem loading the formatter
					// TODO: Decide is we should be handling this better.
				}
			}
			
			return General::sanitize($value);
		}
		
		public function applyValidationRules($data) {
			$rule = $this->{'text-validator'};
			
			return ($rule ? General::validateString($data, $rule) : true);
		}
		
		public function processData($data, Entry $entry = null) {
			$driver = Extension::load('members');
			
			if (isset($entry->data()->{$this->{'element-name'}})) {
				$result = $entry->data()->{$this->{'element-name'}};
			}
			
			else {
				$result = (object)array(
					'handle'			=> null,
					'value'				=> null,
					'value_formatted'	=> null,
				);
			}
			
			if (!is_null($data)) {
				$data = stripslashes($data);
				
				$result->handle = $driver->createHandle($this, $entry, $data);
				$result->value = $data;
				$result->value_formatted = $this->applyFormatting($data);
			}
			
			return $result;
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/
		
		public function fetchIncludableElements() {
			return array(
				$this->{'element-name'} . ': formatted',
				$this->{'element-name'} . ': unformatted'
			);
		}
		
		public function appendFormattedElement(&$wrapper, $data, $mode = null) {
			$driver = Extension::load('members');
			
			if ($mode == 'unformatted') {
				$value = trim($data->value);
			}
			
			else {
				$mode = 'formatted';
				$value = trim($data->value_formatted);
			}
			
			$result = $wrapper->ownerDocument->createElement($this->{'element-name'});
			
			if ($mode == 'unformatted') {
				$value = $wrapper->ownerDocument->createCDATASection($value);
				$result->appendChild($value);
			}
			
			else if ($value) {
				$value = $driver->repairEntities($value);
				$fragment = $wrapper->ownerDocument->createDocumentFragment();
				$fragment->appendXML($value);
				$result->appendChild($fragment);
			}
			
			$attributes = array(
				'mode'			=> $mode,
				'handle'		=> $data->handle,
			);
			
			if ($this->{'text-handle'} != 'yes') {
				unset($attributes['handle']);
			}
			
			foreach ($attributes as $name => $value) {
				$result->setAttribute($name, $value);
			}
			
			$wrapper->appendChild($result);
		}
		
		public function getParameterOutputValue(StdClass $data, Entry $entry = null) {
			return $data->handle;
		}
		
	/*-------------------------------------------------------------------------
		Grouping:
	-------------------------------------------------------------------------*/

		public function groupRecords($records) {
			if (!is_array($records) or empty($records)) return;
			
			$groups = array(
				$this->{'element-name'} => array()
			);
			
			foreach ($records as $record) {
				$data = $record->getData($this->{'id'});
				
				$value = $data['value_formatted'];
				$handle = $data['handle'];
				$element = $this->{'element-name'};
				
				if (!isset($groups[$element][$handle])) {
					$groups[$element][$handle] = array(
						'attr'		=> array(
							'handle'	=> $handle
						),
						'records'	=> array(),
						'groups'	=> array()
					);
				}
				
				$groups[$element][$handle]['records'][] = $record;
			}
			
			return $groups;
		}
	}

	return 'FieldMemberName';