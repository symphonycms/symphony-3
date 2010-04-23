<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	class FieldTextBox extends Field {
		const DISABLE_PROPOGATION = 1;

		protected $_sizes = array();
		protected $_driver = null;
		
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function __construct() {
			parent::__construct();

			$this->_name = 'Text Box';
			$this->_driver = ExtensionManager::instance()->create('field_textbox');

			// Set defaults:
			$this->{'size'} = 'medium';

			$this->_sizes = array(
				array('single', false, __('Single Line')),
				array('small', false, __('Small Box')),
				array('medium', false, __('Medium Box')),
				array('large', false, __('Large Box')),
				array('huge', false, __('Huge Box'))
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
			$fields['text-length'] = 0;
			$fields['text-handle'] = 'yes';
			$fields['text-cdata'] = 'no';
		}

		public function validateSettings(MessageStack $messages, $checkForDuplicates = true) {
			if (trim((string)$this->{'text-length'}) == '') {
				$messages->append('text-length', __('This is a required field.'));
			}

			if (trim((string)$this->{'column-length'}) == '') {
				$messages->append('column-length', __('This is a required field.'));
			}

			return parent::validateSettings($messages, $checkForDuplicates);
		}

		public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $messages) {
			parent::displaySettingsPanel($wrapper, $messages);

			$this->_driver->addSettingsHeaders($this->_engine->Page);
			$document = $wrapper->ownerDocument;

		/*---------------------------------------------------------------------
			Expression
		---------------------------------------------------------------------*/

			$group = $document->createElement('div');
			$group->setAttribute('class', 'group');

			$values = $this->_sizes;

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
			$label->appendChild($document->createElement('i', __('Number of characters')));
			$input = Widget::Input('text-length', $this->{'text-length'});
			$label->appendChild($input);

			if ($messages->{'text-length'}) {
				$label = Widget::wrapFormElementWithError($label, $messages->{'text-length'});
			}

			$group->appendChild($label);

		/*---------------------------------------------------------------------
			Show characters
		---------------------------------------------------------------------*/

			$label = Widget::Label(__('Preview'));
			$label->appendChild($document->createElement('i', __('Number of characters')));
			$input = Widget::Input('column-length', $this->{'column-length'});
			$label->appendChild($input);

			if ($messages->{'column-length'}) {
				$label = Widget::wrapFormElementWithError($label, $messages->{'column-length'});
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

		/*
		public function commit($propogate = null) {
			if (!parent::commit()) return false;

			if ($propogate == self::DISABLE_PROPOGATION) return true;

			$field_id = $this->{'id'};
			$handle = $this->handle();

			if ($field_id === false) return false;

			$fields = array(
				'field_id'			=> $field_id,
				'column-length'		=> max((integer)$this->{'text-length'}, 25),
				'text-size'			=> $this->{'text-size'},
				'text-formatter'	=> $this->{'text-formatter'},
				'text-validator'	=> $this->{'text-validator'},
				'text-length'		=> max((integer)$this->{'text-length'}, 0),
				'text-cdata'		=> $this->{'text-cdata'},
				'text-handle'		=> $this->{'text-handle'}
			);

			Symphony::Database()->delete('tbl_fields_' . $handle, array($field_id), "`field_id` = %d LIMIT 1");
			$field_id = Symphony::Database()->insert('tbl_fields_' . $handle, $fields);

			return ($field_id == 0 || !$field_id) ? false : true;
		}
		*/

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry=NULL, $data=NULL) {
			$this->_driver->addPublishHeaders($wrapper->ownerDocument);

			$sortorder = $this->{'sortorder'};
			$element_name = $this->{'element-name'};
			$classes = array();

			$label = Widget::Label($this->{'label'});
			$optional = '';

			if ($this->{'required'} != 'yes') {
				if ((integer)$this->{'text-length'} > 0) {
					$optional = __('$1 of $2 remaining &ndash; Optional');
				}

				else {
					$optional = __('Optional');
				}
			}

			else if ((integer)$this->{'text-length'} > 0) {
				$optional = __('$1 of $2 remaining');
			}

			if ($optional) {
				$label->appendChild($wrapper->ownerDocument->createElement('i', $optional));
			}

			// Input box:
			if ($this->{'text-size'} == 'single') {
				$input = Widget::Input(
					"fields[$element_name]", General::sanitize($data->value)
				);

				###
				# Delegate: ModifyTextBoxInlineFieldPublishWidget
				# Description: Allows developers modify the textbox before it is rendered in the publish forms
				$delegate = 'ModifyTextBoxInlineFieldPublishWidget';
			}

			// Text Box:
			else {
				$input = Widget::Textarea(
					"fields[$element_name]", General::sanitize($data->value), array('rows' => 20, 'cols' => 50)
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
				$error = $errors->current();
				$label = Widget::wrapFormElementWithError($label, $error['message']);
			}

			$wrapper->appendChild($label);
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		public function validateData(MessageStack &$errors, Entry $entry, $data = null) {
			$length = (integer)$this->{'text-length'};

			if ($this->{'required'} == 'yes' and strlen(trim($data->value)) == 0) {
				$errors->append(
					$this->{'element-name'},
					(object)array(
					 	'message' => __("'%s' is a required field.", array($this->label)),
						'code' => self::ERROR_MISSING
					)
				);

				return self::STATUS_ERROR;
			}

			if (!isset($data->value)) return self::STATUS_OK;

			if (!$this->applyValidationRules($data->value)) {
				$errors->append(
					$this->{'element-name'},
					(object)array(
					 	'message' => __("'%s' contains invalid data. Please check the contents.", array($this->label)),
						'code' => self::ERROR_INVALID
					)
				);

				return self::STATUS_ERROR;
			}

			if ($length > 0 and $length < strlen($data->value)) {
				$errors->append(
					$this->{'element-name'},
					(object)array(
					 	'message' => __("'%s' must be no longer than %s characters.", array(
							$this->{'label'},
							$length
						)),
						'code' => self::ERROR_INVALID
					)
				);

				return self::STATUS_ERROR;
			}

/*
			Does this still need porting?

			if (!General::validateXML($this->applyFormatting($data), $errors, false, new XsltProcess)) {
				$message = __(
					"'%1\$s' contains invalid XML. The following error was returned: <code>%2\$s</code>", array(
						$this->{'label'},
						$errors[0]['message']
					)
				);

				return self::ERROR_INVALID;
			}
*/
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
					'word_count' => null
				);
			}

			if(!is_null($data)){
				$result->handle = Lang::createHandle($data);
				$result->value = $data;
				$result->value_formatted = $this->applyFormatting($data);
				$result->word_count = General::countWords($data);
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
				$value = trim($data['value']);
			}

			else {
				$mode = 'formatted';
				$value = trim($data['value_formatted']);
			}

			if ($mode == 'unformatted' or $this->{'text-cdata'} == 'yes') {
				$value = '<![CDATA[' . $value . ']]>';
			}

			// TODO: Remove this for 2.1 release.
			else if ($encode) {
				$value = General::sanitize($value);
			}

			else {
				$value = $this->repairEntities($value);
			}

			$attributes = array(
				'mode'			=> $mode,
				'handle'		=> $data['handle'],
				'word-count'	=> $data['word_count']
			);

			if ($this->{'text-handle'} != 'yes') {
				unset($attributes['handle']);
			}

			$wrapper->appendChild(new XMLElement(
				$this->{'element-name'}, $value, $attributes
			));
		}

		public function prepareTableValue(StdClass $data, DOMElement $link = null) {
			if (empty($data) or strlen(trim($data->value)) == 0) return;

			$max_length = (integer)$this->{'column-length'};
			$max_length = ($max_length ? $max_length : 75);

			$value = strip_tags($data->value);

			if ($max_length < strlen($value)) {
				$lines = explode("\n", wordwrap($value, $max_length - 1, "\n"));
				$value = array_shift($lines);
				$value = rtrim($value, "\n\t !?.,:;");
				$value .= '&#x2026;';
			}

			if ($max_length > 75) {
				$value = wordwrap($value, 75, '<br />');
			}

			if(!is_null($link)) {
				$link->setValue(General::sanitize($value));
				return $link;
			}

			return $value;
		}

		public function getParameterPoolValue($data) {
			return $data['handle'];
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function displayDatasourceFilterPanel(SymphonyDOMElement $wrapper, $data = null, $errors = null, $prefix = null, $postfix = null) {
			//$this->_driver->addFilteringHeaders($wrapper->ownerDocument);
			$field_id = $this->{'id'};
			$document = $wrapper->ownerDocument;

			$wrapper->setAttribute('class', trim($wrapper->getAttribute('class') . ' field-textbox'));

			$name = $document->createElement('span', $this->label);
			$name->setAttribute('class', 'name');
			$name->appendChild($document->createElement('i', $this->name()));
			$wrapper->appendChild($name);

			$prefix = ($prefix ? "[{$prefix}]" : '');
			$postfix = ($postfix ? "[{$postfix}]" : '');

			$label = Widget::Label('Value');
			$label->appendChild(Widget::Input(
				"fields[filter]{$prefix}[{$field_id}]{$postfix}",
				($data ? General::sanitize($data) : null)
			));
			$wrapper->appendChild($label);

			/*
			$filters = array(
				array(
					'name'				=> 'boolean',
					'filter'			=> 'boolean:',
					'help'				=> __('Find values that match the given query. Can use operators <code>and</code> and <code>not</code>.')
				),
				array(
					'name'				=> 'not-boolean',
					'filter'			=> 'not-boolean:',
					'help'				=> __('Find values that do not match the given query. Can use operators <code>and</code> and <code>not</code>.')
				),

				array(
					'name'				=> 'regexp',
					'filter'			=> 'regexp:',
					'help'				=> __('Find values that match the given <a href="%s">MySQL regular expressions</a>.', array(
						'http://dev.mysql.com/doc/mysql/en/Regexp.html'
					))
				),
				array(
					'name'				=> 'not-regexp',
					'filter'			=> 'not-regexp:',
					'help'				=> __('Find values that do not match the given <a href="%s">MySQL regular expressions</a>.', array(
						'http://dev.mysql.com/doc/mysql/en/Regexp.html'
					))
				),

				array(
					'name'				=> 'contains',
					'filter'			=> 'contains:',
					'help'				=> __('Find values that contain the given string.')
				),
				array(
					'name'				=> 'not-contains',
					'filter'			=> 'not-contains:',
					'help'				=> __('Find values that do not contain the given string.')
				),

				array(
					'name'				=> 'starts-with',
					'filter'			=> 'starts-with:',
					'help'				=> __('Find values that start with the given string.')
				),
				array(
					'name'				=> 'not-starts-with',
					'filter'			=> 'not-starts-with:',
					'help'				=> __('Find values that do not start with the given string.')
				),

				array(
					'name'				=> 'ends-with',
					'filter'			=> 'ends-with:',
					'help'				=> __('Find values that end with the given string.')
				),
				array(
					'name'				=> 'not-ends-with',
					'filter'			=> 'not-ends-with:',
					'help'				=> __('Find values that do not end with the given string.')
				)
			);

			$list = new XMLElement('ul');

			foreach ($filters as $value) {
				$item = new XMLElement('li', $value['name']);
				$item->setAttribute('title', $value['filter']);
				$item->setAttribute('alt', General::sanitize($value['help']));
				$list->appendChild($item);
			}

			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('Find values that are an exact match for the given string.'));

			$wrapper->appendChild($list);
			$wrapper->appendChild($help);
			*/
		}

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->{'id'};

			if (preg_match('/^(not-)?regexp:\s*/', $data[0], $matches)) {
				$data = trim(array_pop(explode(':', $data[0], 2)));
				$negate = ($matches[1] == '' ? '' : 'NOT');

				$data = $this->cleanValue($data);
				$this->_key++;
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND {$negate}(
						t{$field_id}_{$this->_key}.handle REGEXP '{$data}'
						OR t{$field_id}_{$this->_key}.value REGEXP '{$data}'
					)
				";
			}

			else if (preg_match('/^(not-)?boolean:\s*/', $data[0], $matches)) {
				$data = trim(array_pop(explode(':', implode(' + ', $data), 2)));
				$negate = ($matches[1] == '' ? '' : 'NOT');

				if ($data == '') return true;

				// Negative match?
				if (preg_match('/^not(\W)/i', $data)) {
					$mode = '-';

				} else {
					$mode = '+';
				}

				// Replace ' and ' with ' +':
				$data = preg_replace('/(\W)and(\W)/i', '\\1+\\2', $data);
				$data = preg_replace('/(^)and(\W)|(\W)and($)/i', '\\2\\3', $data);
				$data = preg_replace('/(\W)not(\W)/i', '\\1-\\2', $data);
				$data = preg_replace('/(^)not(\W)|(\W)not($)/i', '\\2\\3', $data);
				$data = preg_replace('/([\+\-])\s*/', '\\1', $mode . $data);

				$data = $this->cleanValue($data);
				$this->_key++;
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND {$negate}(MATCH (t{$field_id}_{$this->_key}.value) AGAINST ('{$data}' IN BOOLEAN MODE))
				";
			}

			else if (preg_match('/^(not-)?((starts|ends)-with|contains):\s*/', $data[0], $matches)) {
				$data = trim(array_pop(explode(':', $data[0], 2)));
				$negate = ($matches[1] == '' ? '' : 'NOT');
				$data = $this->cleanValue($data);

				if ($matches[2] == 'ends-with') $data = "%{$data}";
				if ($matches[2] == 'starts-with') $data = "{$data}%";
				if ($matches[2] == 'contains') $data = "%{$data}%";

				$this->_key++;
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND {$negate}(
						t{$field_id}_{$this->_key}.handle LIKE '{$data}'
						OR t{$field_id}_{$this->_key}.value LIKE '{$data}'
					)
				";
			}

			else if ($andOperation) {
				foreach ($data as $value) {
					$this->_key++;
					$value = $this->cleanValue($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
							ON (e.id = t{$field_id}_{$this->_key}.entry_id)
					";
					$where .= "
						AND (
							t{$field_id}_{$this->_key}.handle = '{$value}'
							OR t{$field_id}_{$this->_key}.value = '{$value}'
						)
					";
				}
			}

			else {
				if (!is_array($data)) $data = array($data);

				foreach ($data as &$value) {
					$value = $this->cleanValue($value);
				}

				$this->_key++;
				$data = implode("', '", $data);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND (
						t{$field_id}_{$this->_key}.handle IN ('{$data}')
						OR t{$field_id}_{$this->_key}.value IN ('{$data}')
					)
				";
			}

			return true;
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

