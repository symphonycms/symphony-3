<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	class FieldTextBox extends Field {
		const DISABLE_PROPOGATION = 1;

		protected $sizes = array();
		protected $filters = array();

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct() {
			parent::__construct();

			$this->_name = 'Text Box';

			// Set defaults:
			$this->{'size'} = 'medium';

			$this->sizes = array(
				array('single', false, __('Single Line')),
				array('small', false, __('Small Box')),
				array('medium', false, __('Medium Box')),
				array('large', false, __('Large Box')),
				array('huge', false, __('Huge Box'))
			);

			$this->filters = array(
				'is'				=> 'Is',
				'is-not'			=> 'Is not',
				'contains'			=> 'Contains',
				'does-not-contain'	=> 'Does not contain',
				'boolean-search'	=> 'Boolean search',
				'regex-search'		=> 'Regex search'
			);
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
						`word_count` INT(11) UNSIGNED DEFAULT NULL,
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
		Utilities:
	-------------------------------------------------------------------------*/

		public function createHandle($value, $entry_id) {
			$handle = Lang::createHandle(strip_tags(html_entity_decode($value)));

			if ($this->isHandleLocked($handle, $entry_id)) {
				if ($this->isHandleFresh($handle, $value, $entry_id)) {
					return $this->getCurrentHandle($entry_id);
				}

				else {
					$count = 2;

 					while ($this->isHandleLocked("{$handle}-{$count}", $entry_id)) $count++;

					return "{$handle}-{$count}";
				}
			}

			return $handle;
		}

		public function getCurrentHandle($entry_id) {
			return Symphony::Database()->fetchVar('handle', 0, sprintf(
				"
					SELECT
						f.handle
					FROM
						`tbl_entries_data_%s` AS f
					WHERE
						f.entry_id = '%s'
					LIMIT 1
				",
				$this->{'id'}, $entry_id
			));
		}

		public function isHandleLocked($handle, $entry_id) {
			return (boolean)Symphony::Database()->query(
				"
					SELECT
						f.id
					FROM
						`tbl_entries_data_%s` AS f
					WHERE
						f.handle = '%s'
						%s
					LIMIT 1
				",
				array(
					$this->{'id'}, $handle,
					(!is_null($entry_id) ? "AND f.entry_id != '{$entry_id}'" : '')
				)
			);
		}

		public function isHandleFresh($handle, $value, $entry_id) {
			return (boolean)Symphony::Database()->fetchVar('id', 0, sprintf(
				"
					SELECT
						f.id
					FROM
						`tbl_entries_data_%s` AS f
					WHERE
						f.entry_id = '%s'
						AND f.value = '%s'
					LIMIT 1
				",
				$this->{'id'}, $entry_id,
				$this->cleanValue(General::sanitize($value))
			));
		}

		protected function repairEntities($value) {
			return preg_replace('/&(?!(#[0-9]+|#x[0-9a-f]+|amp|lt|gt);)/i', '&amp;', trim($value));
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function findDefaultSettings(&$fields) {
			$fields['column-length'] = 75;
			$fields['text-size'] = 'medium';
			$fields['text-length'] = 'none';
			$fields['text-handle'] = 'yes';
			$fields['text-cdata'] = 'no';
		}

		public function validateSettings(MessageStack $errors, $checkForDuplicates = true) {
/*			if (trim((string)$this->{'text-length'}) == '') {
				$errors->append('text-length', __('This is a required field.'));
			}*/

			if (trim((string)$this->{'column-length'}) == '') {
				$errors->append('column-length', __('This is a required field.'));
			}

			return parent::validateSettings($errors, $checkForDuplicates);
		}

		public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $errors) {
			parent::displaySettingsPanel($wrapper, $errors);

			$document = $wrapper->ownerDocument;
			$driver = ExtensionManager::instance()->create('field_textbox');
			$driver->addSettingsHeaders($document);

		/*---------------------------------------------------------------------
			Expression
		---------------------------------------------------------------------*/

			$group = $document->createElement('div');
			$group->setAttribute('class', 'group');

			$values = $this->sizes;

			foreach ($values as &$value) {
				$value[1] = $value[0] == $this->{'text-size'};
			}

			$label = Widget::Label('Size');
			$label->appendChild(Widget::Select('text-size', $values));

			$group->appendChild($label);

		/*---------------------------------------------------------------------
			Text Formatter
		---------------------------------------------------------------------*/

			$this->appendFormatterSelect(
				$group, $this->{'text-formatter'}, 'text-formatter'
			);
			$wrapper->appendChild($group);

		/*---------------------------------------------------------------------
			Validator
		---------------------------------------------------------------------*/

			$this->appendValidationSelect(
				$wrapper, $this->{'text-validator'}, 'text-validator'
			);

		/*---------------------------------------------------------------------
			Limiting
		---------------------------------------------------------------------*/

			$group = $document->createElement('div');
			$group->setAttribute('class', 'group');

			$label = Widget::Label(__('Limit'));
			$label->appendChild($document->createElement('em', __('Number of characters')));
			$input = Widget::Input('text-length', $this->{'text-length'});
			$label->appendChild($input);

			if ($errors->{'text-length'}) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'text-length'});
			}

			$group->appendChild($label);

		/*---------------------------------------------------------------------
			Show characters
		---------------------------------------------------------------------*/

			$label = Widget::Label(__('Preview'));
			$label->appendChild($document->createElement('em', __('Number of characters')));
			$input = Widget::Input('column-length', $this->{'column-length'});
			$label->appendChild($input);

			if ($errors->{'column-length'}) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'column-length'});
			}

			$group->appendChild($label);
			$wrapper->appendChild($group);

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

			$label = Widget::Label(__('Output as CDATA'));
			$input = Widget::Input("text-cdata", 'yes', 'checkbox');

			if ($this->{'text-cdata'} == 'yes') {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$item = $document->createElement('li');
			$item->appendChild($label);
			$options_list->appendChild($item);

			$wrapper->appendChild($options_list);
			$wrapper->setAttribute('class', $wrapper->getAttribute('class') . ' field-textbox');
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry=NULL, $data=NULL) {
			$document = $wrapper->ownerDocument;
			$driver = ExtensionManager::instance()->create('field_textbox');
			$driver->addPublishHeaders($document);

			$sortorder = $this->{'sortorder'};
			$element_name = $this->{'element-name'};
			$classes = array();

			$label = Widget::Label($this->{'label'});
			$optional = '';

			if ($this->{'required'} != 'yes') {
				if ((integer)$this->{'text-length'} > 0) {
					$optional = $document->createDocumentFragment();
					$optional->appendChild($document->createTextNode(__('$1 of $2 remaining') . ' '));
					$optional->appendChild($document->createEntityReference('ndash'));
					$optional->appendChild($document->createTextNode(' ' . __('Optional')));
				}

				else {
					$optional = __('Optional');
				}
			}

			else if ((integer)$this->{'text-length'} > 0) {
				$optional = __('$1 of $2 remaining');
			}

			if ($optional) {
				$label->appendChild($wrapper->ownerDocument->createElement('em', $optional));
			}

			// Input box:
			if ($this->{'text-size'} == 'single') {
				$input = Widget::Input(
					"fields[$element_name]", $data->value
				);

				###
				# Delegate: ModifyTextBoxInlineFieldPublishWidget
				# Description: Allows developers modify the textbox before it is rendered in the publish forms
				$delegate = 'ModifyTextBoxInlineFieldPublishWidget';
			}

			// Text Box:
			else {
				$input = Widget::Textarea(
					"fields[$element_name]", $data->value, array('rows' => 20, 'cols' => 50)
				);

				###
				# Delegate: ModifyTextBoxFullFieldPublishWidget
				# Description: Allows developers modify the textbox before it is rendered in the publish forms
				$delegate = 'ModifyTextBoxFullFieldPublishWidget';
			}

			// Add classes:
			$classes[] = 'size-' . $this->{'text-size'};

			if ($this->{'text-formatter'} != 'none') {
				$classes[] = $this->{'text-formatter'};
			}

			$input->setAttribute('class', implode(' ', $classes));
			$input->setAttribute('length', (integer)$this->{'text-length'});

			ExtensionManager::instance()->notifyMembers(
				$delegate, '/backend/',
				array(
					'field'		=> &$this,
					'label'		=> &$label,
					'input'		=> &$input
				)
			);

			if (is_null($label)) return;

			$label->appendChild($input);

			if ($errors->valid()) {
				$label = Widget::wrapFormElementWithError($label, $errors->current()->message);
			}

			$wrapper->appendChild($label);
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		public function validateData(MessageStack &$errors, Entry $entry, $data = null) {
			$length = (integer)$this->{'text-length'};

			if(self::STATUS_OK != parent::validateData($errors, $entry, $data)) {
				return self::STATUS_ERROR;
			}

			if (!isset($data->value)) return self::STATUS_OK;

			if (!$this->applyValidationRules($data->value)) {
				$errors->append(
					null, (object)array(
					 	'message' => __("'%s' contains invalid data. Please check the contents.", array($this->label)),
						'code' => self::ERROR_INVALID
					)
				);

				return self::STATUS_ERROR;
			}

			if ($length > 0 and $length < strlen($data->value)) {
				$errors->append(
					null, (object)array(
					 	'message' => __("'%s' must be no longer than %s characters.", array(
							$this->{'label'},
							$length
						)),
						'code' => self::ERROR_INVALID
					)
				);

				return self::STATUS_ERROR;
			}

			return self::STATUS_OK;
		}

		public function applyFormatting($value) {
			if (isset($this->{'text-formatter'}) && $this->{'text-formatter'} != TextFormatter::NONE) {

				try{
					$formatter = TextFormatter::loadFromHandle($this->{'text-formatter'});
					$result = $formatter->run($value);
					$result = preg_replace('/&(?![a-z]{0,4}\w{2,3};|#[x0-9a-f]{2,6};)/i', '&amp;', $result);
					return $result;
				}
				catch(Exception $e){
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

		// TODO: Fix the createHandle function
		public function processFormData($data, Entry $entry=NULL){

			if(isset($entry->data()->{$this->{'element-name'}})){
				$result = $entry->data()->{$this->{'element-name'}};
			}

			else {
				$result = (object)array(
					'handle' => null,
					'value' => null,
					'value_formatted' => null,
				);
			}

			if(!is_null($data)){
				$result->handle = Lang::createHandle($data);
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

		public function appendFormattedElement(&$wrapper, $data, $encode = false, $mode = null) {

			if ($mode == 'unformatted') {
				$value = trim($data->value);
			}

			else {
				$mode = 'formatted';
				$value = trim($data->value_formatted);
			}

			$result = $wrapper->ownerDocument->createElement($this->{'element-name'});

			if ($mode == 'unformatted' or $this->{'text-cdata'} == 'yes') {
				$value = $wrapper->ownerDocument->createCDATASection($value);
				$result->appendChild($value);
			}

			else if ($value) {
				$value = $this->repairEntities($value);
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

			foreach($attributes as $name => $value){
				$result->setAttribute($name, $value);
			}

			$wrapper->appendChild($result);
		}
		
		public function getParameterPoolValue($data) {
			return $data['handle'];
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/
		
		public function fetchFilterTypes($data) {
			$filters = parent::fetchFilterTypes($data);
			$filters[] = array(
				'boolean-search', $data->type == 'boolean-search', 'Boolean Search'
			);
			
			return $filters;
		}
		
		public function buildFilterQuery($filter, &$joins, &$where, Register $parameter_output) {
			$filter = $this->processFilter($filter);
			$filter_join = DataSource::FILTER_OR;
			$db = Symphony::Database();
			
			// Boolean searches:
			if ($filter->type == 'boolean-search') {
				$handle = $this->buildFilterJoin($joins);
				$value = trim($filter->value);
				$mode = (preg_match('/^not(\W)/i', $value) ? '-' : '+');
				
				// Replace ' and ' with ' +':
				$value = preg_replace('/(\W)and(\W)/i', '\\1+\\2', $value);
				$value = preg_replace('/(^)and(\W)|(\W)and($)/i', '\\2\\3', $value);
				$value = preg_replace('/(\W)not(\W)/i', '\\1-\\2', $value);
				$value = preg_replace('/(^)not(\W)|(\W)not($)/i', '\\2\\3', $value);
				$value = preg_replace('/([\+\-])\s*/', '\\1', $mode . $value);
				
				$statement = $db->prepareQuery("MATCH ({$handle}.value) AGAINST ('%s' IN BOOLEAN MODE)", array($value));
				
				$where .= "AND (\n\t" . $statement . "\n)";
				
				return true;
			}
			
			return parent::buildFilterQuery($filter, $joins, $where, $parameter_output);
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

	return 'FieldTextBox';

