<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	class FieldJoin extends Field {
		const DISABLE_PROPOGATION = 1;

		protected $filters = array();

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct() {
			parent::__construct();
			
			$this->_name = 'Join';
			$this->filters = array(
				'is'				=> 'Is',
				'is-not'			=> 'Is not'
			);
		}
		
		public function create() {
			return Symphony::Database()->query(sprintf(
				"
					CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
						`entry_id` INT(11) UNSIGNED NOT NULL,
						`joined_id` INT(11) UNSIGNED NOT NULL,
						PRIMARY KEY (`id`),
						KEY `entry_id` (`entry_id`),
						KEY `joined_id` (`joined_id`)
					) ENGINE=MyISAM;
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
		
		public function toDoc() {
			$temp = $this->{'joinable-sections'};
			$this->{'joinable-sections'} = array();
			
			$doc = parent::toDoc();
			$doc->formatOutput = true;
			$parent = $doc->xpath('/field/joinable-sections')->item(0);
			
			$this->{'joinable-sections'} = $temp;
			
			foreach ($this->{'joinable-sections'} as $section) {
				$item = $doc->createElement('item');
				$item->setValue($section);
				$parent->appendChild($item);
			}
			
			return $doc;
	    }
		
	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/
		
		public function loadSettingsFromSimpleXMLObject(SimpleXMLElement $xml) {
			if (isset($xml->{'joinable-sections'})) {
				$joinable = array();
				
				foreach ($xml->{'joinable-sections'}->item as $item) {
					$joinable[] = (string)$item;
				}
				
				unset($xml->{'joinable-sections'});
				
				$this->{'joinable-sections'} = $joinable;
			}
			
			parent::loadSettingsFromSimpleXMLObject($xml);
		}
		
		public function findDefaultSettings(&$fields) {
			$fields['joinable-sections'] = array();
			$fields['show-header'] = 'yes';
		}
		
		public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $errors) {
			parent::displaySettingsPanel($wrapper, $errors);
			
			$document = $wrapper->ownerDocument;
			$driver = Extension::load('field_join');
			//$driver->addSettingsHeaders($document);
			
		/*---------------------------------------------------------------------
			Joinable Sections
		---------------------------------------------------------------------*/
			
			$options = array();
			
			foreach (new SectionIterator as $section) {
				if ($section->handle == $this->section) continue;
				
				$options[] = array(
					$section->handle,
					in_array($section->handle, $this->{'joinable-sections'}),
					$section->name
				);
			}
			
			$label = Widget::Label(__('Joinable Sections'));
			$select = Widget::Select('joinable-sections][', $options);
			$select->setAttribute('multiple', 'multiple');
			$label->appendChild($select);
			
			$wrapper->appendChild($label);
			
		/*---------------------------------------------------------------------
			Options
		---------------------------------------------------------------------*/
			
			$options_list = $document->createElement('ul');
			$options_list->setAttribute('class', 'options-list');
			
			$this->appendShowColumnCheckbox($options_list);
			$this->appendRequiredCheckbox($options_list);

			$label = Widget::Label(__('Show header'));
			$input = Widget::Input('show-header', 'yes', 'checkbox');

			if ($this->{'show-header'} == 'yes') {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$item = $document->createElement('li');
			$item->appendChild($label);
			$options_list->appendChild($item);
			
			$wrapper->appendChild($options_list);
			$wrapper->setAttribute('class', $wrapper->getAttribute('class') . ' field-join');
		}
		
	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/
		
		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry = null, $data = null) {
			$joined = null;
			$document = $wrapper->ownerDocument;
			$xpath = new DOMXPath($document);
			$driver = Extension::load('field_join');
			$driver->addPublishHeaders($document);
			
			$sortorder = $this->{'sortorder'};
			$element_name = $this->{'element-name'};
			
			$wrapper->setAttribute('data-label', $this->{'publish-label'});
			$wrapper->setAttribute('data-show-header', $this->{'show-header'});
			
			if ($this->{'required'} != 'yes') {
				$wrapper->setAttribute('data-optional', __('Choose a section...'));
			}
			
			//var_dump($data); exit;
			
			if (isset($data->joined_id)) {
				$joined = Entry::loadFromId($data->joined_id);
			}
			
			// Use existing entry:
			if ($joined instanceof Entry) {
				$section = Section::loadFromHandle($joined->section);
				$context = $document->createElement('div');
				$context->setAttribute('data-handle', $section->handle);
				$context->setAttribute('data-name', $section->name);
				$context->addClass('context');
				$fields = array();
				
				foreach ($section->fields as $index => $instance) {
					$fields[$instance->{'element-name'}] = $instance;
				}
				
				$this->displayJoinedPublishPanel($context, $section, $fields, $errors, $joined);
				
				// Replace field names:
				foreach ($xpath->query('.//*[starts-with(@name, "fields[")]', $context) as $node) {
					$name = $node->getAttribute('name');
					$name = sprintf(
						'fields[%s][%s]%s',
						$this->{'element-name'},
						$joined->id,
						preg_replace('%^fields%', null, $name)
					);
					$node->setAttribute('name', $name);
				}
				
				$wrapper->appendChild($context);
			}
			
			// Build contexts:
			else foreach ($this->{'joinable-sections'} as $handle) {
				try {
					$section = Section::loadFromHandle($handle);
				}
				
				catch (Exception $e) {
					continue;
				}
				
				$context = $document->createElement('div');
				$context->setAttribute('data-handle', $section->handle);
				$context->setAttribute('data-name', $section->name);
				$context->addClass('context');
				$fields = $values = array();
				$joined = new Entry();
				//var_dump($data);
				if (isset($data[$section->handle])) {
					$values = $data[$section->handle];
				}
				
				foreach ($section->fields as $index => $instance) {
					$handle = $instance->{'element-name'};
					$fields[$handle] = $instance;
					
					if (isset($values[$handle])) {
						$joined->data()->{$handle} = $values[$handle];
					}
				}
				
				$this->displayJoinedPublishPanel($context, $section, $fields, $errors, $joined);
				
				// Replace field names:
				foreach ($xpath->query('.//*[starts-with(@name, "fields[")]', $context) as $node) {
					$name = $node->getAttribute('name');
					$name = sprintf(
						'fields[%s][%s]%s',
						$this->{'element-name'},
						$section->handle,
						preg_replace('%^fields%', null, $name)
					);
					$node->setAttribute('name', $name);
				}
				
				$wrapper->appendChild($context);
			}
		}
		
		public function displayJoinedPublishPanel(SymphonyDOMElement $wrapper, Section $section, array $fields, MessageStack $errors, Entry $entry) {
			$document = $wrapper->ownerDocument;
			$layout = new Layout();
			
			foreach ($section->layout as $data) {
				$column = $layout->createColumn($data->size);
				
				foreach ($data->fieldsets as $data) {
					$fieldset = $document->createElement('fieldset');
					
					if (isset($data->collapsed) && $data->collapsed == 'yes') {
						$fieldset->setAttribute('class', 'collapsed');
					}
					
					if (isset($data->name) && strlen(trim($data->name)) > 0) {
						$fieldset->appendChild(
							$document->createElement('h3', $data->name)
						);
					}
					
					foreach ($data->fields as $handle) {
						$field = $fields[$handle];
						
						if (!$field instanceof Field) continue;
						
						$div = $document->createElement('div', NULL, array(
								'class' => trim(sprintf('field field-%s %s',
									$field->handle(),
									($field->required == 'yes' ? 'required' : '')
								))
							)
						);
						
						$field->displayPublishPanel(
							$div,
							(isset($errors->{$field->{'element-name'}})
								? $errors->{$field->{'element-name'}}
								: new MessageStack),
							$entry,
							$entry->data()->{$field->{'element-name'}}
						);
	
						$fieldset->appendChild($div);
					}
	
					$column->appendChild($fieldset);
				}
			}
			
			$layout->appendTo($wrapper);
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		public function validateData(MessageStack $errors, Entry $entry, $data = null) {
			$status = self::STATUS_OK;
			
			foreach ($data as $key => $fields) {
				if (is_numeric($key)) {
					$joined = Entry::loadFromId($key);
					$section = Section::loadFromHandle($joined->section);
				}
				
				else {
					$section = Section::loadFromHandle($key);
				}
				
				foreach ($fields as $field_handle => $value) {
					$field = $section->fetchFieldByHandle($field_handle);
					$field_errors = new MessageStack();
					$field_status = $field->validateData($field_errors, $entry, $value);
					$errors->append($field_handle, $field_errors);
					
					if ($field_status != self::STATUS_OK) {
						$status = self::STATUS_ERROR;
					}
				}
			}
			
			return $status;
		}

		public function processData($data, Entry $entry = null) {
			$result = array();
			
			if (!is_array($data)) return $result;
			
			foreach ($data as $key => $fields) {
				if (is_numeric($key)) {
					$joined = Entry::loadFromId($key);
					$section = Section::loadFromHandle($joined->section);
				}
				
				else {
					$section = Section::loadFromHandle($key);
					$result[$key] = array();
				}
				
				foreach ($fields as $field_handle => $value) {
					$field = $section->fetchFieldByHandle($field_handle);
					$result[$key][$field_handle] = $field->processData($value, $entry);
				}
				
				break;
			}
			
			return $result;
		}
		
		public function saveData(MessageStack $errors, Entry $entry, $data = null) {
			$key = key($data);
			$fields = current($data);
			
			if (is_numeric($key)) {
				$joined = Entry::loadFromId($key);
				$section_handle = $joined->section;
				Entry::delete($key);
			}
			
			else {
				$section_handle = $key;
			}
			
			$joined = new Entry();
			$joined->section = $section_handle;
			
			// Find the current user ID or just any ID at all:
			if (isset(Administration::instance()->User) && Administration::instance()->User instanceof User) {
				$joined->user_id = Administration::instance()->User->id;
			}
			
			else if (isset(Frontend::instance()->User) && Frontend::instance()->User instanceof User) {
				$joined->user_id = Frontend::instance()->User->id;
			}
			
			else {
				$joined->user_id = (integer)Symphony::Database()->query(
					"SELECT `id` FROM `tbl_users` ORDER BY `id` ASC LIMIT 1"
				)->current()->id;
			}
			
			// Set entry data:
			foreach ($fields as $field_handle => $value) {
				$joined->data()->{$field_handle} = $value;
			}
			
			//echo '<pre>'; var_dump($section_handle); exit;
			
			$status = Entry::save($joined, $errors);
			
			if ($status != Entry::STATUS_OK) return $status;
			
			$row = (object)array(
				'entry_id'		=> $entry->id,
				'joined_id'		=> $joined->id
			);
			
			try {
				Symphony::Database()->insert(
					sprintf('tbl_data_%s_%s', $entry->section, $this->{'element-name'}),
					(array)$row,
					Database::UPDATE_ON_DUPLICATE
				);
				
				return self::STATUS_OK;
			}
			
			catch (DatabaseException $e) {
				$errors->append(
					null, (object)array(
					 	'message' => $e->getMessage(),
						'code' => $e->getDatabaseErrorCode()
					)
				);
			}
			
			catch (Exception $e) {
				$errors->append(
					null, (object)array(
					 	'message' => $e->getMessage(),
						'code' => $e->getCode()
					)
				);
			}
			
			return self::STATUS_ERROR;
		}
		
	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function fetchIncludableElements() {
			return array(
				array('handle' => $this->{'element-name'} . ': formatted', 'name' => $this->name, 'mode' => "Formatted"),
				array('handle' => $this->{'element-name'} . ': unformatted', 'name' => $this->name, 'mode' => "Unformatted")
			);
		}

		public function appendFormattedElement(DOMElement $wrapper, $data, $encode=false, $mode=NULL, Entry $entry=NULL) {
			if ($mode == 'unformatted') {
				$value = trim($data->value);
			}

			else {
				$mode = 'formatted';
				$value = trim($data->value_formatted);
			}

			if(is_null($value) || empty($value)) return;

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

		public function getParameterOutputValue($data, Entry $entry = null) {
			return $data->handle;
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function getFilterTypes($data) {
			$filters = parent::getFilterTypes($data);
			$filters[] = array(
				'boolean-search', $data->type == 'boolean-search', 'Boolean Search'
			);

			return $filters;
		}

		public function buildFilterQuery($filter, &$joins, array &$where, Register $parameter_output) {
			$filter = $this->processFilter($filter);
			$filter_join = DataSource::FILTER_OR;
			$db = Symphony::Database();

			// Boolean searches:
			if ($filter->type == 'boolean-search') {
				$handle = $this->buildFilterJoin($joins);
				$value = DataSource::replaceParametersInString(
					trim($filter->value), $parameter_output
				);
				$mode = (preg_match('/^not(\W)/i', $value) ? '-' : '+');

				// Replace ' and ' with ' +':
				$value = preg_replace('/(\W)and(\W)/i', '\\1+\\2', $value);
				$value = preg_replace('/(^)and(\W)|(\W)and($)/i', '\\2\\3', $value);
				$value = preg_replace('/(\W)not(\W)/i', '\\1-\\2', $value);
				$value = preg_replace('/(^)not(\W)|(\W)not($)/i', '\\2\\3', $value);
				$value = preg_replace('/([\+\-])\s*/', '\\1', $mode . $value);

				$statement = $db->prepareQuery("MATCH ({$handle}.value) AGAINST ('%s' IN BOOLEAN MODE)", array($value));

				$where[] = "(\n\t{$statement}\n)";

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

	return 'FieldJoin';

