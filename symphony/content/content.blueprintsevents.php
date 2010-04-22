<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	//require_once(TOOLKIT . '/class.eventmanager.php');
	//require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.event.php');
	
	Class contentBlueprintsEvents extends AdministrationPage{

		public function __viewIndex() {
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Events'))));

			$this->appendSubheading(__('Events') . $heading, Widget::Anchor(
				__('Create New'), Administration::instance()->getCurrentPageURL() . 'new/', array(
					'title' => __('Create a new event'),
					'class' => 'create button'
				)
			));

			$eTableHead = array(
				array(__('Name'), 'col'),
				array(__('Source'), 'col'),
				array(__('Author'), 'col')
			);

			$eTableBody = array();
			$colspan = count($eTableHead);
			
			$iterator = new EventIterator;
			
			if(!$iterator->valid()) {
				$eTableBody = array(Widget::TableRow(
					array(
						Widget::TableData(__('None found.'), array(
								'class' => 'inactive',
								'colspan' => $colspan
							)
						)
					), array(
						'class' => 'odd'
					)
				));
			}

			else{
			
				foreach($iterator as $pathname){
					
					$event = Event::load($pathname);
					
					//$instance = eventManager::instance()->create($event['handle']);
					//$section = SectionManager::instance()->fetch($instance->getSource());

					$view_mode = ($event->allowEditorToParse() == true ? 'edit' : 'info');
					$handle = preg_replace('/.php$/i', NULL, basename($event->parameters()->pathname));
					
					$col_name = Widget::TableData(
						Widget::Anchor($event->about()->name, URL . "/symphony/blueprints/events/{$view_mode}/{$handle}/", array(
							'title' => $event->parameters()->pathname
						))
					);
					$col_name->appendChild(Widget::Input("items[{$handle}]", null, 'checkbox'));

					// Source  
					if(is_null($event->parameters()->source)){
						$col_source = Widget::TableData(__('None'), 'inactive');
					}
					else{
						$section = Section::loadFromHandle($event->parameters()->source);

						$col_source = Widget::TableData(Widget::Anchor(
							$section->name,
							URL . '/symphony/blueprints/sections/edit/' . $section->handle . '/',
							array('title' => $section->handle)
						));
					}

					if (isset($event->about()->author->website)) {
						$col_author = Widget::TableData(Widget::Anchor(
							$event->about()->author->name,
							General::validateURL($event->about()->author->website)
						));
					}

					else if (isset($event->about()->author->email)) {
						$col_author = Widget::TableData(Widget::Anchor(
							$event->about()->author->name,
							'mailto:' . $event->about()->author->email
						));
					}

					else {
						$col_author = Widget::TableData($event->about()->author->name);
					}

					$eTableBody[] = Widget::TableRow(
						array($col_name, $col_source, $col_author)
					);
				}

			}
			
			$table = Widget::Table(
				Widget::TableHead($eTableHead), null, Widget::TableBody($eTableBody), array(
					'id' => 'events-list'
				)
			);
			$this->Form->appendChild($table);

			$tableActions = $this->createElement('div');
			$tableActions->setAttribute('class', 'actions');

			$options = array(
				array(null, false, __('With Selected...')),
				array('delete', false, __('Delete'))
			);

			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));

			$this->Form->appendChild($tableActions);
		}

		function __viewNew(){
			$this->__form();
		}

		function __viewEdit(){
			$this->__form();
		}

		function __viewInfo(){
			$this->__form(true);
		}

		function __form($readonly=false){

			if($this->errors instanceof MessageStack && $this->errors->length() > 0){
				$this->pageAlert(__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), Alert::ERROR);
			}

			if(isset($this->_context[2])){
				switch($this->_context[2]){

					case 'saved':
						$this->pageAlert(
							__(
								'Event updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Events</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									URL . '/symphony/blueprints/events/new/',
									URL . '/symphony/blueprints/events/'
								)
							),
							Alert::SUCCESS);
						break;

					case 'created':
						$this->pageAlert(
							__(
								'Event created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Events</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									URL . '/symphony/blueprints/events/new/',
									URL . '/symphony/blueprints/events/'
								)
							),
							Alert::SUCCESS);
						break;

				}
			}

			$isEditing = ($readonly ? true : false);
			$fields = array();

			if($this->_context[0] == 'edit' || $this->_context[0] == 'info'){
				$isEditing = true;

				$handle = $this->_context[1];

				$existing = Event::loadFromName($handle);

				$about = $existing->about();
				$about=(array)$about;
				$fields['name'] = $about['name'];
				$fields['source'] = $existing->parameters()->source;
				$fields['filters'] = $existing->eParamFILTERS;
				$fields['output_id_on_save'] = ($existing->eParamOUTPUT_ID_ON_SAVE === true ? 'yes' : 'no');

				if(isset($existing->eParamOVERRIDES) && !empty($existing->eParamOVERRIDES)){
					$fields['overrides'] = array(
						'field' => array(),
						'replacement' => array()
					);

					foreach($existing->eParamOVERRIDES as $field_name => $replacement_value){
						$fields['overrides']['field'][] = $field_name;
						$fields['overrides']['replacement'][] = $replacement_value;
					}

				}

				if(isset($existing->eParamDEFAULTS) && !empty($existing->eParamDEFAULTS)){
					$fields['defaults'] = array(
						'field' => array(),
						'replacement' => array()
					);

					foreach($existing->eParamDEFAULTS as $field_name => $replacement_value){
						$fields['defaults']['field'][] = $field_name;
						$fields['defaults']['replacement'][] = $replacement_value;
					}

				}

			}

			if(isset($_POST['fields'])) $fields = $_POST['fields'];

			$layout = new Layout; //('small', 'small', 'medium');
			
			$column_1 = $layout->createColumn(Layout::SMALL);
			$column_2 = $layout->createColumn(Layout::SMALL);
			$column_3 = $layout->createColumn(Layout::LARGE);
						
			$this->setTitle(__(($isEditing ? '%1$s &ndash; %2$s &ndash; %3$s' : '%1$s &ndash; %2$s'), array(__('Symphony'), __('Events'), $about['name'])));
			$this->appendSubheading(($isEditing ? $about['name'] : __('Untitled')));

			if(!$readonly):

				$fieldset = Widget::Fieldset(__('Essentials'));

				$label = Widget::Label(__('Name'));
				$label->appendChild(Widget::Input('fields[name]', General::sanitize($fields['name'])));

				if(isset($this->errors->name)){
					$fieldset->appendChild(Widget::wrapFormElementWithError($label, $this->errors->name));
				}
				else $fieldset->appendChild($label);

				$label = Widget::Label(__('Source'));

			    $options = array();

				foreach (new SectionIterator as $section) {
					$options[] = array($section->handle, ($fields['source'] == $section->handle), $section->name);
				}

				$label->appendChild(Widget::Select('fields[source]', $options, array('id' => 'event-context-selector')));
				$fieldset->appendChild($label);
				$column_1->appendChild($fieldset);

				$fieldset = Widget::Fieldset(__('Processing Options'));
				$label = Widget::Label(__('Filter Rules'));

				if(!is_array($fields['filters'])) $fields['filters'] = array($fields['filters']);

				$options = array(
					array('admin-only', in_array('admin-only', $fields['filters']), __('Admin Only')),
					array('send-email', in_array('send-email', $fields['filters']), __('Send Email')),
					array('expect-multiple', in_array('expect-multiple', $fields['filters']), __('Allow Multiple')),
				);

				###
				# Delegate: AppendEventFilter
				# Description: Allows adding of new filter rules to the Event filter 
				# rule select box. A reference to the $options array is provided, and selected filters
				ExtensionManager::instance()->notifyMembers(
					'AppendEventFilter', 
					'/blueprints/events/' . $this->_context[0] . '/', 
					array('selected' => $fields['filters'], 'options' => &$options)
				);

				$label->appendChild(Widget::Select('fields[filters][]', $options, array('multiple' => 'multiple')));
				$fieldset->appendChild($label);

				$label = Widget::Label();
				$input = Widget::Input('fields[output_id_on_save]', 'yes', 'checkbox');
				if(isset($fields['output_id_on_save']) && $fields['output_id_on_save'] == 'yes'){
					$input->setAttribute('checked', 'checked');
				}

				$label->appendChild($input);
				$label->setValue(__('Add entry ID to the parameter pool in the format of <code>$event-name-id</code> when saving is successful.'));
				$fieldset->appendChild($label);
				$column_2->appendChild($fieldset);
				

				$fieldset = Widget::Fieldset(__('Overrides &amp; Defaults'), '{$param}');

				//$div = $this->createElement('div');

				foreach(new SectionIterator as $s){
					$fieldset->appendChild(
						$this->__buildDefaultsAndOverridesDuplicator(
							$s,
							($fields['source'] == $s->id
								? array('overrides' => $fields['overrides'], 'defaults' => $fields['defaults'])
								: NULL
							)
						)
					);
				}
				
				//$fieldset->appendChild($div);
				$column_3->appendChild($fieldset);

				$layout->appendTo($this->Form);
				
			endif;

			/*
			// TO DO: Find new home for Documentation
			if($isEditing):
				$fieldset = Widget::Fieldset(__('Description'));

				$doc = $existing->documentation();
				$fieldset->appendChild($this->createElement('legend', __('Description')));
				$fieldset->setValue(PHP_EOL . General::tabsToSpaces($doc, 2));

				$this->Form->appendChild($fieldset);
			endif;
			*/

			if($readonly != true){
				
				$div = $this->createElement('div');
				$div->setAttribute('class', 'actions');
				$div->appendChild(Widget::Input('action[save]', ($isEditing ? __('Save Changes') : __('Create Event')), 'submit', array('accesskey' => 's')));

				if($isEditing){
					$div->appendChild(
						$this->createElement('button', __('Delete'), array(
							'name' => 'action[delete]',
							'class' => 'confirm delete',
							'title' => __('Delete this event')
						))
					);
				}

				$this->Form->appendChild($div);
			}

		}

		private function __buildDefaultsAndOverridesDuplicator(Section $section, array $items=NULL){

			$duplicator = $this->createElement('div');
			$duplicator->setAttribute('class', 'event-duplicator event-context-' . $section->handle);

			$templates = $this->createElement('ol');
			$templates->setAttribute('class', 'templates');

			$instances = $this->createElement('ol');
			$instances->setAttribute('class', 'instances');

			$ol = new XMLElement('ol');
			$ol->setAttribute('id', 'section-' . $section->handle);
		
			$item = $this->createElement('li');
			$span = $this->createElement('span', 'Override');
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
			
			
			$item = $this->createElement('li');
			$span = $this->createElement('span', 'Default Value');
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
				$field_names = $items['overrides']['field'];
				$replacement_values = $items['overrides']['replacement'];
				
				for($ii = 0; $ii < count($field_names); $ii++){
					$item = $this->createElement('li');
					$span = $this->createElement('span', 'Override');
					$span->setAttribute('class', 'name');
					$item->appendChild($span);

					$label = Widget::Label(__('Field'));
					$options = array(array('system:id', false, 'System ID'));

					foreach($section->fields as $f){
						$options[] = array(
							General::sanitize($f->{'element-name'}), 
							$f->{'element-name'} == $field_names[$ii], 
							General::sanitize($f->label)
						);
					}

					$label->appendChild(Widget::Select('fields[overrides][field][]', $options));
					$item->appendChild($label);

					$label = Widget::Label(__('Replacement'));
					$label->appendChild(Widget::Input('fields[overrides][replacement][]', General::sanitize($replacement_values[$ii])));
					$item->appendChild($label);
					$instances->appendChild($item);
				}
			}
			
			if(is_array($items['defaults'])){
				
				$field_names = $items['defaults']['field'];
				$replacement_values = $items['defaults']['replacement'];
				
				for($ii = 0; $ii < count($field_names); $ii++){
					$item = $this->createElement('li');
					$span = $this->createElement('span', 'Default Value');
					$span->setAttribute('class', 'name');
					$item->appendChild($span);

					$label = Widget::Label(__('Field'));
					$options = array(array('system:id', false, 'System ID'));

					foreach($section->fields as $f){
						$options[] = array(
							General::sanitize($f->{'element-name'}), 
							$f->{'element-name'} == $field_names[$ii], 
							General::sanitize($f->label)
						);
					}

					$label->appendChild(Widget::Select('fields[defaults][field][]', $options));
					$item->appendChild($label);

					$label = Widget::Label(__('Replacement'));
					$label->appendChild(Widget::Input('fields[defaults][replacement][]', General::sanitize($replacement_values[$ii])));
					$item->appendChild($label);
					$instances->appendChild($item);
				}
			}
			

				
			$duplicator->appendChild($templates);
			$duplicator->appendChild($instances);
			
			return $duplicator;
			

			//$fields = Symphony::Database()->fetch("SELECT `element_name`, `label` FROM `tbl_fields` WHERE `parent_section` = " . $section->get('id'));
/*
			$duplicator = $this->createElement('div', NULL, array('id' => 'event-context-' . $section->handle));
			$h3 = $this->createElement('h3', __('Fields'));
			$h3->setAttribute('class', 'label');
			$duplicator->appendChild($h3);

			$ol = $this->createElement('ol');
			$ol->setAttribute('class', 'events-duplicator');

			$options = array(
				array('', false, __('None')),
			);


			if(is_array($items['overrides'])){

				$field_names = $items['overrides']['field'];
				$replacement_values = $items['overrides']['replacement'];

				for($ii = 0; $ii < count($field_names); $ii++){

					$li = $this->createElement('li');
					$li->appendChild($this->createElement('h4', __('Override')));

					$group = $this->createElement('div');
					$group->setAttribute('class', 'group');

					$label = Widget::Label(__('Element Name'));
					$options = array(array('system:id', false, 'System ID'));

					foreach($section->fields as $f){
						$options[] = array(General::sanitize($f['element_name']), $f['element_name'] == $field_names[$ii], General::sanitize($f['label']));
					}

					$label->appendChild(Widget::Select('fields[overrides][field][]', $options));
					$group->appendChild($label);

					$label = Widget::Label(__('Replacement'));
					$label->appendChild(Widget::Input('fields[overrides][replacement][]', General::sanitize($replacement_values[$ii])));
					$group->appendChild($label);

					$li->appendChild($group);
					$ol->appendChild($li);

				}
			}

			if(is_array($items['defaults'])){

				$field_names = $items['defaults']['field'];
				$replacement_values = $items['defaults']['replacement'];

				for($ii = 0; $ii < count($field_names); $ii++){

					$li = $this->createElement('li');
					$li->appendChild($this->createElement('h4', __('Default Value')));

					$group = $this->createElement('div');
					$group->setAttribute('class', 'group');

					$label = Widget::Label(__('Element Name'));
					$options = array(array('system:id', false, 'System ID'));

					foreach($section->fields as $f){
						$options[] = array(General::sanitize($f['element_name']), $f['element_name'] == $field_names[$ii], General::sanitize($f['label']));
					}

					$label->appendChild(Widget::Select('fields[defaults][field][]', $options));
					$group->appendChild($label);


					$label = Widget::Label(__('Replacement'));
					$label->appendChild(Widget::Input('fields[defaults][replacement][]', General::sanitize($replacement_values[$ii])));
					$group->appendChild($label);

					$li->appendChild($group);
					$ol->appendChild($li);

				}
			}

			$li = $this->createElement('li');
			$li->setAttribute('class', 'template');
			$li->appendChild($this->createElement('h4', __('Override')));

			$group = $this->createElement('div');
			$group->setAttribute('class', 'group');

			$label = Widget::Label(__('Field'));
			$options = array(array('system:id', false, 'System ID'));

			foreach($section->fields as $f){
				$options[] = array(General::sanitize($f->{'element-name'}), false, General::sanitize($f->label));
			}

			$label->appendChild(Widget::Select('fields[overrides][field][]', $options));
			$group->appendChild($label);

			$label = Widget::Label(__('Replacement'));
			$label->appendChild(Widget::Input('fields[overrides][replacement][]'));
			$group->appendChild($label);

			$li->appendChild($group);
			$ol->appendChild($li);


			$li = $this->createElement('li');
			$li->setAttribute('class', 'template');
			$li->appendChild($this->createElement('h4', __('Default Value')));

			$group = $this->createElement('div');
			$group->setAttribute('class', 'group');

			$label = Widget::Label(__('Field'));
			$options = array(array('system:id', false, 'System ID'));

			foreach($section->fields as $f){
				$options[] = array(General::sanitize($f->{'element-name'}), false, General::sanitize($f->label));
			}

			$label->appendChild(Widget::Select('fields[defaults][field][]', $options));
			$group->appendChild($label);

			$label = Widget::Label(__('Replacement'));
			$label->appendChild(Widget::Input('fields[defaults][replacement][]'));
			$group->appendChild($label);

			$li->appendChild($group);
			$ol->appendChild($li);

			$duplicator->appendChild($ol);

			return $duplicator;
			*/
		}

		function __actionNew(){
			if(array_key_exists('save', $_POST['action'])) return $this->__formAction();
		}

		function __actionEdit(){
			if(array_key_exists('save', $_POST['action'])) return $this->__formAction();
			elseif(array_key_exists('delete', $_POST['action'])){

				## TODO: Fix Me
				###
				# Delegate: Delete
				# Description: Prior to deleting the event file. Target file path is provided.
				#ExtensionManager::instance()->notifyMembers('Delete', getCurrentPage(), array("file" => EVENTS . "/event." . $_REQUEST['file'] . ".php"));

		    	if(!General::deleteFile(EVENTS . '/event.' . $this->_context[1] . '.php'))
					$this->pageAlert(__('Failed to delete <code>%s</code>. Please check permissions.', array($this->_context[1])), Alert::ERROR);

		    	else redirect(URL . '/symphony/blueprints/components/');

			}
		}

		function __formAction(){
			$post = General::getPostData();
			var_dump($post['fields']); die();
			
			$this->_errors = array();

			if(trim($fields['name']) == '') $this->_errors['name'] = __('This is a required field');

			$classname = Lang::createHandle($fields['name'], '_', false, true, array('@^[^a-z]+@i' => '', '/[^\w-\.]/i' => ''));
			$rootelement = str_replace('_', '-', $classname);

			$file = EVENTS . '/event.' . $classname . '.php';

			$isDuplicate = false;
			$queueForDeletion = NULL;

			if($this->_context[0] == 'new' && is_file($file)) $isDuplicate = true;
			elseif($this->_context[0] == 'edit'){
				$existing_handle = $this->_context[1];
				if($classname != $existing_handle && is_file($file)) $isDuplicate = true;
				elseif($classname != $existing_handle) $queueForDeletion = EVENTS . '/event.' . $existing_handle . '.php';
			}

			##Duplicate
			if($isDuplicate) $this->_errors['name'] = __('An Event with the name <code>%s</code> name already exists', array($classname));

			if(empty($this->_errors)){

				$multiple = @in_array('expect-multiple', $fields['filters']);

				$eventShell = file_get_contents(TEMPLATES . '/event.tpl');

				$about = array(
					'name' => $fields['name'],
					'version' => '1.0',
					'release date' => DateTimeObj::getGMT('c'),
					'author name' => Administration::instance()->User->getFullName(),
					'author website' => URL,
					'author email' => Administration::instance()->User->email,
					'trigger condition' => $rootelement
				);

				$source = $fields['source'];

				$filter = NULL;
				$elements = NULL;

				$eventShell = self::__injectAboutInformation($eventShell, $about);

				if(isset($fields['filters']) && is_array($fields['filters']) && !empty($fields['filters'])){
					$eventShell = self::__injectArrayValues($eventShell, 'FILTERS', $fields['filters']);
				}

				$eventShell = self::__injectOverridesAndDefaults(
					$eventShell,
					(isset($fields['overrides']) && is_array($fields['overrides']) && !empty($fields['overrides']) ? $fields['overrides'] : NULL),
					(isset($fields['defaults']) && is_array($fields['defaults']) && !empty($fields['defaults']) ? $fields['defaults'] : NULL)
				);

				$documentation = NULL;
				$documentation_parts = array();

				$documentation_parts[] = $this->createElement('h3', __('Success and Failure XML Examples'));
				$documentation_parts[] = $this->createElement('p', __('When saved successfully, the following XML will be returned:'));

				if($multiple){
					$code = $this->createElement($rootelement);
					$entry = $this->createElement('entry', NULL, array('index' => '0', 'result' => 'success' , 'type' => 'create | edit'));
					$entry->appendChild($this->createElement('message', __('Entry [created | edited] successfully.')));

					$code->appendChild($entry);
				}

				else{
					$code = $this->createElement($rootelement, NULL, array('result' => 'success' , 'type' => 'create | edit'));
					$code->appendChild($this->createElement('message', __('Entry [created | edited] successfully.')));
				}


				$documentation_parts[] = self::processDocumentationCode($code);

				###


				$documentation_parts[] = $this->createElement('p', __('When an error occurs during saving, due to either missing or invalid fields, the following XML will be returned') . ($multiple ? __(' (<b>Notice that it is possible to get mixtures of success and failure messages when using the "Allow Multiple" option</b>)') : NULL) . ':');

				if($multiple){
					$code = $this->createElement($rootelement);

					$entry = $this->createElement('entry', NULL, array('index' => '0', 'result' => 'error'));
					$entry->appendChild($this->createElement('message', __('Entry encountered errors when saving.')));
					$entry->appendChild($this->createElement('field-name', NULL, array('type' => 'invalid | missing')));
					$code->appendChild($entry);

					$entry = $this->createElement('entry', NULL, array('index' => '1', 'result' => 'success' , 'type' => 'create | edit'));
					$entry->appendChild($this->createElement('message', __('Entry [created | edited] successfully.')));
					$code->appendChild($entry);
				}

				else{
					$code = $this->createElement($rootelement, NULL, array('result' => 'error'));
					$code->appendChild($this->createElement('message', __('Entry encountered errors when saving.')));
					$code->appendChild($this->createElement('field-name', NULL, array('type' => 'invalid | missing')));
				}


				$code->setValue('...', false);
				$documentation_parts[] = self::processDocumentationCode($code);

				###


				if(is_array($fields['filters']) && !empty($fields['filters'])){
					$documentation_parts[] = $this->createElement('p', __('The following is an example of what is returned if any filters fail:'));

					$code = $this->createElement($rootelement, NULL, array('result' => 'error'));
					$code->appendChild($this->createElement('message', __('Entry encountered errors when saving.')));
					$code->appendChild($this->createElement('filter', NULL, array('name' => 'admin-only', 'status' => 'failed')));
					$code->appendChild($this->createElement('filter', __('Recipient username was invalid'), array('name' => 'send-email', 'status' => 'failed')));
					$code->setValue('...', false);
					$documentation_parts[] = self::processDocumentationCode($code);
				}

				###

				$documentation_parts[] = $this->createElement('h3', __('Example Front-end Form Markup'));

				$documentation_parts[] = $this->createElement('p', __('This is an example of the form markup you can use on your frontend:'));
				$container = $this->createElement('form', NULL, array('method' => 'post', 'action' => '', 'enctype' => 'multipart/form-data'));
				$container->appendChild(Widget::Input('MAX_FILE_SIZE', Symphony::Configuration()->get('max_upload_size', 'admin'), 'hidden'));

				$section = SectionManager::instance()->fetch($fields['source']);
				$markup = NULL;
				foreach($section->fetchFields() as $f){
					if ($f->getExampleFormMarkup() instanceof XMLElement)
						$container->appendChild($f->getExampleFormMarkup());
				}
				$container->appendChild(Widget::Input('action['.$rootelement.']', __('Submit'), 'submit'));

				$code = $container->generate(true);

				$documentation_parts[] = self::processDocumentationCode(($multiple ? str_replace('fields[', 'fields[0][', $code) : $code));


				$documentation_parts[] = $this->createElement('p', __('To edit an existing entry, include the entry ID value of the entry in the form. This is best as a hidden field like so:'));
				$documentation_parts[] = self::processDocumentationCode(Widget::Input('id' . ($multiple ? '[0]' : NULL), 23, 'hidden'));


				$documentation_parts[] = $this->createElement('p', __('To redirect to a different location upon a successful save, include the redirect location in the form. This is best as a hidden field like so, where the value is the URL to redirect to:'));
				$documentation_parts[] = self::processDocumentationCode(Widget::Input('redirect', URL.'/success/', 'hidden'));

				if(@in_array('send-email', $fields['filters'])){

					$documentation_parts[] = $this->createElement('h3', __('Send Email Filter'));

					$documentation_parts[] = $this->createElement('p', __('The send email filter, upon the event successfully saving the entry, takes input from the form and send an email to the desired recipient. <b>This filter currently does not work with the "Allow Multiple" option.</b> The following are the recognised fields:'));

					$documentation_parts[] = self::processDocumentationCode(
						'send-email[sender-email] // '.__('Optional').PHP_EOL.
						'send-email[sender-name] // '.__('Optional').PHP_EOL.
						'send-email[subject] // '.__('Optional').PHP_EOL.
						'send-email[body]'.PHP_EOL.
						'send-email[recipient] // '.__('list of comma separated usernames.'));

					$documentation_parts[] = $this->createElement('p', __('All of these fields can be set dynamically using the exact field name of another field in the form as shown below in the example form:'));

			        $documentation_parts[] = self::processDocumentationCode('<form action="" method="post">
	<fieldset>
		<label>'.__('Name').' <input type="text" name="fields[author]" value="" /></label>
		<label>'.__('Email').' <input type="text" name="fields[email]" value="" /></label>
		<label>'.__('Message').' <textarea name="fields[message]" rows="5" cols="21"></textarea></label>
		<input name="send-email[sender-email]" value="fields[email]" type="hidden" />
		<input name="send-email[sender-name]" value="fields[author]" type="hidden" />
		<input name="send-email[subject]" value="You are being contacted" type="hidden" />
		<input name="send-email[body]" value="fields[message]" type="hidden" />
		<input name="send-email[recipient]" value="fred" type="hidden" />
		<input id="submit" type="submit" name="action[save-contact-form]" value="Send" />
	</fieldset>
</form>');

				}

				###
				# Delegate: AppendEventFilterDocumentation
				# Description: Allows adding documentation for new filters. A reference to the $documentation array is provided, along with selected filters
				ExtensionManager::instance()->notifyMembers(
					'AppendEventFilterDocumentation',
					'/blueprints/events/' . $this->_context[0] . '/',
					array('selected' => $fields['filters'], 'documentation' => &$documentation_parts)
				);

				$documentation = join(PHP_EOL, array_map(create_function('$x', 'return rtrim($x->generate(true, 4));'), $documentation_parts));
				$documentation = str_replace('\'', '\\\'', $documentation);

				$pattern = array(
					'<!-- CLASS NAME -->',
					'<!-- SOURCE -->',
					'<!-- DOCUMENTATION -->',
					'<!-- ROOT ELEMENT -->',
					'<!-- OUTPUT ID ON SAVE -->'
				);

				$replacements = array(
					$classname,
					$source,
					General::tabsToSpaces($documentation, 2),
					$rootelement,
					(isset($fields['output_id_on_save']) && $fields['output_id_on_save'] == 'yes' ? 'true' : 'false')
				);

				$eventShell = str_replace($pattern, $replacements, $eventShell);


				## Remove left over placeholders
				$eventShell = preg_replace(array('/<!--[\w ]++-->/'), '', $eventShell);
				header('Content-Type: text/plain');

				##Write the file
				if(!is_writable(dirname($file)) || !$write = General::writeFile($file, $eventShell, Symphony::Configuration()->get('write_mode', 'file')))
					$this->pageAlert(__('Failed to write Event to <code>%s</code>. Please check permissions.', array(EVENTS)), Alert::ERROR);

				##Write Successful, add record to the database
				else{

					// TODO: Remove Events from Views

					/*
					if($queueForDeletion){
						General::deleteFile($queueForDeletion);

						$sql = "SELECT * FROM `tbl_pages` WHERE `events` REGEXP '[[:<:]]".$existing_handle."[[:>:]]' ";
						$resul = Symphony::Database()->query($sql);

						if(is_array($pages) && !empty($pages)){
							foreach($pages as $page){

								$page['events'] = preg_replace('/\b'.$existing_handle.'\b/i', $classname, $page['events']);

								Symphony::Database()->update('tbl_pages', $page, array($page['id']), "`id` = '".$page['id']."'");
							}
						}

					}
					*/

					### TODO: Fix me
					###
					# Delegate: Create
					# Description: After saving the event, the file path is provided and an array
					#              of variables set by the editor
					#ExtensionManager::instance()->notifyMembers('Create', getCurrentPage(), array('file' => $file, 'defines' => $defines, 'var' => $var));

	                redirect(URL . '/symphony/blueprints/events/edit/'.$classname.'/'.($this->_context[0] == 'new' ? 'created' : 'saved') . '/');

				}
			}
		}

		public static function processDocumentationCode($code){
			return $this->createElement('pre', '<code>' . str_replace('<', '&lt;', str_replace('&', '&amp;', trim((is_object($code) ? $code->generate(true) : $code)))) . '</code>', array('class' => 'XML'));
		}


		private static function __injectArrayValues($shell, $variable, array $elements){
			return str_replace('<!-- '.strtoupper($variable).' -->',  "'" . implode("'," . PHP_EOL . "\t\t\t'", $elements) . "'", $shell);
		}

		private static function __injectOverridesAndDefaults($shell, array $overrides=NULL, array $defaults=NULL){

			/*
			Array
			(
			    [field] => Array
			        (
			            [0] => id
			        )

			    [replacement] => Array
			        (
			            [0] => 43
			        )

			)
			Array
			(
			    [field] => Array
			        (
			            [0] => title
			            [1] => published
			        )

			    [replacement] => Array
			        (
			            [0] => I am {$title}
			            [1] => no
			        )

			)
			*/

			if(!is_null($overrides)){
				$values = array();
				foreach($overrides['field'] as $index => $handle){
					if(strlen(trim($handle)) == 0) continue;
					$values[] = sprintf("%s' => '%s", addslashes($handle), addslashes($overrides['replacement'][$index]));
				}

				$shell = self::__injectArrayValues($shell, 'OVERRIDES', $values);
			}

			if(!is_null($defaults)){
				$values = array();
				foreach($defaults['field'] as $index => $handle){
					if(strlen(trim($handle)) == 0) continue;
					$values[] = sprintf("%s' => '%s", addslashes($handle), addslashes($defaults['replacement'][$index]));
				}

				$shell = self::__injectArrayValues($shell, 'DEFAULTS', $values);
			}

			return $shell;
		}

		private static function __injectAboutInformation($shell, array $details){
			foreach($details as $key => $val){
				$shell = str_replace('<!-- ' . strtoupper($key) . ' -->', addslashes($val), $shell);
			}

			return $shell;
		}

		protected function __actionDelete($events, $redirect) {
			$success = true;

			if(!is_array($events)) $events = array($events);

			foreach ($events as $event) {
				if(!General::deleteFile(EVENTS . '/event.' . $event . '.php'))
					$this->pageAlert(__('Failed to delete <code>%s</code>. Please check permissions.', array($this->_context[1])), Alert::ERROR);
			}

			if($success) redirect($redirect);
		}

		public function __actionIndex() {
			$checked = array_keys($_POST['items']);

			if(is_array($checked) && !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						$this->__actionDelete($checked, URL . '/symphony/blueprints/events/');
						break;
				}
			}
		}


	}

