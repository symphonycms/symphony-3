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
			
			$wrapper->appendChild($options_list);
			$wrapper->setAttribute('class', $wrapper->getAttribute('class') . ' field-join');
		}
		
	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/
		
		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry=NULL, $data=NULL) {
			$document = $wrapper->ownerDocument;
			$driver = Extension::load('field_join');
			$driver->addPublishHeaders($document);
			
			$sortorder = $this->{'sortorder'};
			$element_name = $this->{'element-name'};
			$classes = array();
			
			$field = $document->createElement('div');
			$field->setAttribute('class', 'label');
			
			$field->appendChild($document->createElement(
				'span', $this->{'publish-label'}
			));
			
			$sections = array();
			$options = array();
			
			if ($this->{'required'} != 'yes') $options[] = array(
				null, false, __('Choose a section...')
			);
			
			foreach ($this->{'joinable-sections'} as $handle) {
				$section = Section::loadFromHandle($handle);
				$sections[] = $section;
				$options[] = array(
					$handle, false, $section->name
				);
			}
			
			$select = Widget::Select("fields[{$element_name}]", $options);
			$select->addClass('context-switch');
			$field->appendChild($select);
			
			foreach ($sections as $section) {
				$context = $document->createElement('div');
				$context->addClass('context context-' . $section->handle);
				$fields = array();
				
				foreach ($section->fields as $index => $instance) {
					$fields[$instance->{'element-name'}] = $instance;
				}
				
				$this->displayJoinedPublishPanel($context, $section, $fields);
				$field->appendChild($context);
			}
			
			$wrapper->appendChild($field);
		}
		
		public function displayJoinedPublishPanel(SymphonyDOMElement $wrapper, Section $section, array $fields) {
			$document = $wrapper->ownerDocument;
			$layout = new Layout();
			$entry = new Entry();
			$errors = new MessageStack();
			
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
			$length = (integer)$this->{'text-length'};

			if(self::STATUS_OK != parent::validateData($errors, $entry, $data)) {
				return self::STATUS_ERROR;
			}

			if (!isset($data->value)) return self::STATUS_OK;

			if (!$this->applyValidationRules($data->value)) {
				$errors->append(
					null, (object)array(
					 	'message' => __("'%s' contains invalid data. Please check the contents.", array($this->{'publish-label'})),
						'code' => self::ERROR_INVALID
					)
				);

				return self::STATUS_ERROR;
			}

			if ($length > 0 and $length < strlen($data->value)) {
				$errors->append(
					null, (object)array(
					 	'message' => __("'%s' must be no longer than %s characters.", array(
							$this->{'publish-label'}, $length
						)),
						'code' => self::ERROR_INVALID
					)
				);

				return self::STATUS_ERROR;
			}

			return self::STATUS_OK;
		}

		public function processData($data, Entry $entry = null) {
			if (isset($entry->data()->{$this->{'element-name'}})) {
				$result = $entry->data()->{$this->{'element-name'}};
			}

			else {
				$result = (object)array(
					'handle'			=> null,
					'value'				=> null,
					'value_formatted'	=> null
				);
			}

			if (!is_null($data)) {
				$data = stripslashes($data);

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

