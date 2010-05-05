<?php

	require_once 'lib/class.event.php';

	Class Extension_Event_Sections extends Extension {
		public function about() {
			return array(
				'name'			=> 'Sections',
				'version'		=> '1.0.0',
				'release-date'	=> '2010-05-05',
				'type'			=> array(
					'Event', 'Core'
				),
				'author'		=> array(
					'name'			=> 'Symphony Team',
					'website'		=> 'http://symphony-cms.com/',
					'email'			=> 'team@symphony-cms.com'
				),
				'description'	=> 'Create events to save to Symphony sections.'
			);
		}
		
		public function prepare(array $data = null, Event $event = null) {
			if (is_null($event)) {
				$event = new SectionsEvent;
			}
			
			if (!is_null($data)) {
				$event->about()->name = $data['name'];
	
				$event->about()->author->name = Administration::instance()->User->getFullName();
				$event->about()->author->email = Administration::instance()->User->email;
	
				$event->parameters()->section = $data['section'];
	
				if(isset($data['output-id-on-save']) && $data['output-id-on-save'] == 'yes'){
					$event->parameters()->{'output-id-on-save'} = true;
				}
	
				if(isset($data['filters']) && is_array($data['filters']) || !empty($data['filters'])){
					$event->parameters()->filters = $data['filters'];
				}
	
				if(isset($data['defaults']) && is_array($data['defaults']) || !empty($data['defaults'])){
					$defaults = array();
					foreach($data['defaults']['field'] as $index => $field){
						$defaults[$field] = $data['defaults']['replacement'][$index];
					}
					$event->parameters()->defaults = $defaults;
				}
	
				if(isset($data['overrides']) && is_array($data['overrides']) || !empty($data['overrides'])){
					$overrides = array();
					foreach($data['overrides']['field'] as $index => $field){
						$overrides[$field] = $data['overrides']['replacement'][$index];
					}
					$event->parameters()->overrides = $overrides;
				}
			}
			
			return $event;
		}
		
		public function view(Event $event, SymphonyDOMElement $wrapper, MessageStack $errors) {
			$page = Administration::instance()->Page;
			$layout = new Layout;

			$column_1 = $layout->createColumn(Layout::SMALL);
			$column_2 = $layout->createColumn(Layout::SMALL);
			$column_3 = $layout->createColumn(Layout::LARGE);

			$fieldset = Widget::Fieldset(__('Essentials'));

			$label = Widget::Label(__('Name'));
			$label->appendChild(Widget::Input('fields[name]', General::sanitize($event->about()->name)));

			if(isset($errors->{'about::name'})){
				$fieldset->appendChild(Widget::wrapFormElementWithError($label, $errors->{'about::name'}));
			}
			else $fieldset->appendChild($label);

			$label = Widget::Label(__('Section'));

		    $options = array();

			foreach (new SectionIterator as $section) {
				$options[] = array($section->handle, ($event->parameters()->section == $section->handle), $section->name);
			}

			$label->appendChild(Widget::Select('fields[section]', $options, array('id' => 'event-context-selector')));
			$fieldset->appendChild($label);
			$column_1->appendChild($fieldset);

			$fieldset = Widget::Fieldset(__('Processing Options'));
			$label = Widget::Label(__('Filter Rules'));

			$filters = $event->parameters()->filters;
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
			ExtensionManager::instance()->notifyMembers(
				'AppendEventFilter',
				'/blueprints/events/' . $event->about()->name . '/', // TODO: This line is wrong, should be event handle.
				array(
					'selected'	=> $fields['filters'],
					'options'	=> &$options
				)
			);

			$label->appendChild(Widget::Select('fields[filters][]', $options, array('multiple' => 'multiple')));
			$fieldset->appendChild($label);

			$label = Widget::Label();
			$input = Widget::Input('fields[output-id-on-save]', 'yes', 'checkbox');
			if($event->parameters()->{'output-id-on-save'} == true){
				$input->setAttribute('checked', 'checked');
			}

			$label->appendChild($input);
			$label->appendChild(new DOMText(__('Add entry ID to the parameter pool in the format of $event-name-id when saving is successful.')));
			$fieldset->appendChild($label);
			$column_2->appendChild($fieldset);

			$fieldset = Widget::Fieldset(__('Overrides & Defaults'), '{$param}');

			foreach(new SectionIterator as $section){
				$this->appendDuplicator(
					$fieldset, $section,
					($event->parameters()->section == $section->handle
						? array(
							'overrides' => $event->parameters()->overrides,
							'defaults' => $event->parameters()->defaults
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
			$duplicator = $document->createElement('div');
			$duplicator->setAttribute('class', 'event-duplicator event-context-' . $section->handle);

			$templates = $document->createElement('ol');
			$templates->setAttribute('class', 'templates');

			$instances = $document->createElement('ol');
			$instances->setAttribute('class', 'instances');

			$ol = $document->createElement('ol');
			$ol->setAttribute('id', 'section-' . $section->handle);

			$item = $document->createElement('li');
			$span = $document->createElement('span', 'Override');
			$span->setAttribute('class', 'name');
			$item->appendChild($span);

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

			$templates->appendChild($item);


			$item = $document->createElement('li');
			$span = $document->createElement('span', 'Default Value');
			$span->setAttribute('class', 'name');
			$item->appendChild($span);

			$label = Widget::Label(__('Field'));
			$options = array(array('system:id', false, 'System ID'));

			foreach($section->fields as $f){
				$options[] = array(General::sanitize($f->{'element-name'}), false, General::sanitize($f->label));
			}

			$label->appendChild(Widget::Select('fields[defaults][field][]', $options));
			$item->appendChild($label);

			$label = Widget::Label(__('Replacement'));
			$label->appendChild(Widget::Input('fields[defaults][replacement][]'));
			$item->appendChild($label);

			$templates->appendChild($item);

			if(is_array($items['overrides'])){
				//$field_names = $items['overrides']['field'];
				//$replacement_values = $items['overrides']['replacement'];

				//for($ii = 0; $ii < count($field_names); $ii++){
				foreach($items['overrides'] as $field_name => $replacement){
					$item = $document->createElement('li');
					$span = $document->createElement('span', 'Override');
					$span->setAttribute('class', 'name');
					$item->appendChild($span);

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

				//$field_names = $items['defaults']['field'];
				//$replacement_values = $items['defaults']['replacement'];

				//for($ii = 0; $ii < count($field_names); $ii++){
				foreach($items['defaults'] as $field_name => $replacement){
					$item = $document->createElement('li');
					$span = $document->createElement('span', 'Default Value');
					$span->setAttribute('class', 'name');
					$item->appendChild($span);

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



			$duplicator->appendChild($templates);
			$duplicator->appendChild($instances);
			$wrapper->appendChild($duplicator);
		}
	}