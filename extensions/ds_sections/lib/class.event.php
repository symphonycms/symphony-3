<?php

	require_once LIB . '/class.duplicator.php';
	require_once LIB . '/class.entry.php';
	require_once LIB . '/class.event.php';

	Class SectionsEvent extends Event {
		public function __construct(){
			// Set Default Values
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
				'root-element' => null,
				'section' => null,
				'filters' => array(),
				'overrides' => array(),
				'defaults' => array(),
				'output-id-on-save' => false
			);
		}

		public function getType() {
			return 'SectionsEvent';
		}

		public function getTemplate(){
			return EXTENSIONS . '/ds_sections/templates/template.event.php';
		}

		public function prepareDestinationColumnValue(){
			$section = Section::loadFromHandle($this->_parameters->section);

			if ($section instanceof Section) {
				return Widget::TableData(
					Widget::Anchor($section->name, ADMIN_URL . '/blueprints/sections/edit/' . $section->handle . '/', array(
						'title' => $section->handle
					))
				);
			}

			else {
				return Widget::TableData(__('None'), array(
					'class' => 'inactive'
				));
			}
		}
		
	/*-----------------------------------------------------------------------*/
		
		public function prepare(array $data = null) {
			if (!is_null($data)) {
				$this->about()->name = $data['name'];
	
				$this->about()->author->name = Administration::instance()->User->getFullName();
				$this->about()->author->email = Administration::instance()->User->email;
	
				$this->parameters()->section = $data['section'];
	
				if(isset($data['output-id-on-save']) && $data['output-id-on-save'] == 'yes'){
					$this->parameters()->{'output-id-on-save'} = true;
				}
	
				if(isset($data['filters']) && is_array($data['filters']) || !empty($data['filters'])){
					$this->parameters()->filters = $data['filters'];
				}
	
				if(isset($data['defaults']) && is_array($data['defaults']) || !empty($data['defaults'])){
					$defaults = array();
					foreach($data['defaults']['field'] as $index => $field){
						$defaults[$field] = $data['defaults']['replacement'][$index];
					}
					$this->parameters()->defaults = $defaults;
				}
	
				if(isset($data['overrides']) && is_array($data['overrides']) || !empty($data['overrides'])){
					$overrides = array();
					foreach($data['overrides']['field'] as $index => $field){
						$overrides[$field] = $data['overrides']['replacement'][$index];
					}
					$this->parameters()->overrides = $overrides;
				}
			}
		}
		
		public function view(SymphonyDOMElement $wrapper, MessageStack $errors) {
			$page = Administration::instance()->Page;
			$page->insertNodeIntoHead($page->createScriptElement(URL . '/extensions/ds_sections/assets/view.js'));
			$layout = new Layout;

			$column_1 = $layout->createColumn(Layout::SMALL);
			$column_2 = $layout->createColumn(Layout::SMALL);
			$column_3 = $layout->createColumn(Layout::LARGE);

			$fieldset = Widget::Fieldset(__('Essentials'));

			$label = Widget::Label(__('Name'));
			$label->appendChild(Widget::Input('fields[name]', General::sanitize($this->about()->name)));

			if(isset($errors->{'about::name'})){
				$fieldset->appendChild(Widget::wrapFormElementWithError($label, $errors->{'about::name'}));
			}
			else $fieldset->appendChild($label);

			$label = Widget::Label(__('Section'));

		    $options = array();

			foreach (new SectionIterator as $section) {
				$options[] = array($section->handle, ($this->parameters()->section == $section->handle), $section->name);
			}

			$label->appendChild(Widget::Select('fields[section]', $options, array('id' => 'context')));
			$fieldset->appendChild($label);
			$column_1->appendChild($fieldset);

			$fieldset = Widget::Fieldset(__('Processing Options'));
			$label = Widget::Label(__('Filter Rules'));

			$filters = $this->parameters()->filters;
			if(!is_array($filters)) $filters = array();

			$options = array(
				array('admin-only', in_array('admin-only', $filters), __('Admin Only')),
				array('send-email', in_array('send-email', $filters), __('Send Email')),
				array('expect-multiple', in_array('expect-multiple', $filters), __('Allow Multiple')),
			);

			###
			# Delegate: AppendEventFilter
			# Description: Allows adding of new filter rules to the Event filter
			# rule select box. A reference to the $options array is provided, and selected filters
			Extension::notify(
				'AppendEventFilter',
				'/blueprints/events/',
				array(
					'selected'	=> $fields['filters'],
					'options'	=> &$options
				)
			);

			$label->appendChild(Widget::Select('fields[filters][]', $options, array('multiple' => 'multiple')));
			$fieldset->appendChild($label);

			$label = Widget::Label();
			$input = Widget::Input('fields[output-id-on-save]', 'yes', 'checkbox');
			if($this->parameters()->{'output-id-on-save'} == true){
				$input->setAttribute('checked', 'checked');
			}

			$label->appendChild($input);
			$label->appendChild(new DOMText(__('Add entry ID to the parameter pool in the format of $this-name-id when saving is successful.')));
			$fieldset->appendChild($label);
			$column_2->appendChild($fieldset);

			$fieldset = Widget::Fieldset(__('Overrides & Defaults'), '{$param}');

			foreach(new SectionIterator as $section){
				$this->appendDuplicator(
					$fieldset, $section,
					($this->parameters()->section == $section->handle
						? array(
							'overrides' => $this->parameters()->overrides,
							'defaults' => $this->parameters()->defaults
						)
						: NULL
					)
				);
			}

			$column_3->appendChild($fieldset);
			$layout->appendTo($wrapper);
		}
		
		protected function appendDuplicator(SymphonyDOMElement $wrapper, Section $section, array $items = null) {
			$document = $wrapper->ownerDocument;
			$duplicator = new Duplicator(__('Add Item'));
			$duplicator->addClass('context context-' . $section->handle);

			$item = $duplicator->createTemplate(__('Override'));
			$label = Widget::Label(__('Field'));
			$options = array(array('system:id', false, 'System ID'));

			foreach($section->fields as $f){
				$options[] = array(General::sanitize($f->{'element-name'}), false, General::sanitize($f->label));
			}

			$label->appendChild(Widget::Select('fields[overrides][field][]', $options));
			$item->appendChild($label);

			$label = Widget::Label(__('Replacement'));
			$label->appendChild(Widget::Input('fields[overrides][replacement][]'));
			$item->appendChild($label);

			$item = $duplicator->createTemplate(__('Default Value'));
			$label = Widget::Label(__('Field'));
			$options = array(array('system:id', false, 'System ID'));

			foreach ($section->fields as $f) {
				$options[] = array(General::sanitize($f->{'element-name'}), false, General::sanitize($f->label));
			}

			$label->appendChild(Widget::Select('fields[defaults][field][]', $options));
			$item->appendChild($label);

			$label = Widget::Label(__('Replacement'));
			$label->appendChild(Widget::Input('fields[defaults][replacement][]'));
			$item->appendChild($label);

			if(is_array($items['overrides'])){
				foreach($items['overrides'] as $field_name => $replacement){
					$tab = $duplicator->createTab(__('Override'));
					$item = $duplicator->createInstance();
					$label = Widget::Label(__('Field'));
					$options = array(array('system:id', false, 'System ID'));

					foreach($section->fields as $f){
						$options[] = array(
							General::sanitize($f->{'element-name'}),
							$f->{'element-name'} == $field_name,
							General::sanitize($f->label)
						);
					}

					$label->appendChild(Widget::Select('fields[overrides][field][]', $options));
					$item->appendChild($label);

					$label = Widget::Label(__('Replacement'));
					$label->appendChild(Widget::Input('fields[overrides][replacement][]', General::sanitize($replacement)));
					$item->appendChild($label);
					$instances->appendChild($item);
				}
			}

			if(is_array($items['defaults'])){
				foreach($items['defaults'] as $field_name => $replacement){
					$tab = $duplicator->createTab(__('Default Value'));
					$item = $duplicator->createInstance();
					$label = Widget::Label(__('Field'));
					$options = array(array('system:id', false, 'System ID'));

					foreach($section->fields as $f){
						$options[] = array(
							General::sanitize($f->{'element-name'}),
							$f->{'element-name'} == $field_name,
							General::sanitize($f->label)
						);
					}

					$label->appendChild(Widget::Select('fields[defaults][field][]', $options));
					$item->appendChild($label);

					$label = Widget::Label(__('Replacement'));
					$label->appendChild(Widget::Input('fields[defaults][replacement][]', General::sanitize($replacement)));
					$item->appendChild($label);
					$instances->appendChild($item);
				}
			}
			
			$duplicator->appendTo($wrapper);
		}
		
	/*-----------------------------------------------------------------------*/
		
		public function canTrigger(array $data) {
			if (!isset($data['action'][$this->parameters()->{'root-element'}])) return false;
			
			return true;
		}
		
		public function trigger(Register $ParameterOutput, array $postdata){
			$result = new XMLDocument;
			$result->appendChild($result->createElement($this->parameters()->{'root-element'}));

			$root = $result->documentElement;

			if(isset($postdata['id'])){
				$entry = Entry::loadFromID($postdata['id']);
				$type = 'edit';
			}
			else{
				$entry = new Entry;
				$entry->section = $this->parameters()->{'section'};
				if(isset(Frontend::instance()->User) && Frontend::instance()->User instanceof User){
					$entry->user_id = Frontend::instance()->User->id;
				}
				else{
					$entry->user_id = (int)Symphony::Database()->query("SELECT `id` FROM `tbl_users` ORDER BY `id` ASC LIMIT 1")->current()->id;
				}
				$type = 'create';
			}

			if(isset($postdata['fields']) && is_array($postdata['fields']) && !empty($postdata['fields'])){
				$entry->setFieldDataFromFormArray($postdata['fields']);
			}

			$root->setAttribute('type', $type);

			###
			# Delegate: EntryPreCreate
			# Description: Just prior to creation of an Entry. Entry object provided
			Extension::notify(
				'EntryPreCreate', '/frontend/',
				array('entry' => &$entry)
			);

			$errors = new MessageStack;
			$status = Entry::save($entry, $errors);
			
			if($status == Entry::STATUS_OK){
				###
				# Delegate: EntryPostCreate
				# Description: Creation of an Entry. New Entry object is provided.
				Extension::notify(
					'EntryPostCreate', '/frontend/',
					array('entry' => $entry)
				);

				if($this->parameters()->{'output-id-on-save'} == true){
					$ParameterOutput->{sprintf('event-%s-id', $this->parameters()->{'root-element'})} = $entry->id;
				}

				$root->setAttribute('result', 'success');

				$root->appendChild($result->createElement(
					'message',
					__("Entry %s successfully.", array(($type == 'edit' ? __('edited') : __('created'))))
				));

			}
			else{
				$root->setAttribute('result', 'error');
				$root->appendChild($result->createElement(
					'message', __('Entry encountered errors when saving.')
				));
				
				if(!isset($postdata['fields']) || !is_array($postdata['fields'])) {
					$postdata['fields'] = array();
				}
				
				$element = $result->createElement('values');
				$this->appendValues($element, $postdata['fields']);
				$root->appendChild($element);
				
				$element = $result->createElement('errors');
				$this->appendMessages($element, $errors);
				$root->appendChild($element);
			}

			return $result;
		}
		
		protected function appendValues(DOMElement $wrapper, array $values) {
			$document = $wrapper->ownerDocument;
			
			foreach ($values as $key => $value) {
				if (is_numeric($key)) {
					$element = $document->createElement('item');
				}
				
				else {
					$element = $document->createElement($key);
				}
				
				if (is_array($value) and !empty($value)) {
					$this->appendValues($element, $value);
				}
				
				else {
					$element->setValue((string)$value);
				}
				
				$wrapper->appendChild($element);
			}
		}
		
		protected function appendMessages(DOMElement $wrapper, MessageStack $messages) {
			$document = $wrapper->ownerDocument;
			
			foreach ($messages as $key => $value) {
				if (is_numeric($key)) {
					$element = $document->createElement('item');
				}
				
				else {
					$element = $document->createElement($key);
				}
				
				if ($value instanceof $messages and $value->valid()) {
					$this->appendMessages($element, $value);
				}
				
				else if ($value instanceof STDClass) {
					$element->setValue($value->message);
					$element->setAttribute('type', $value->code);
				}
				
				else {
					continue;
				}
				
				$wrapper->appendChild($element);
			}
		}
	}
	
?>