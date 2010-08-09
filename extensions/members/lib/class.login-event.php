<?php
	
	require_once LIB . '/class.event.php';
	
	class Member_Login_Event extends Event {
		protected $driver;
		protected $cookie;
		
		public function __construct() {
			$this->driver = Extension::load('members');
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
				'root-element'		=> null,
				'section'			=> null,
				'overrides'			=> array(),
				'defaults'			=> array()
			);
		}
		
		public function getType() {
			return 'Members_Login_Event';
		}
		
		public function getTemplate() {
			return EXTENSIONS . '/members/templates/template.login-event.php';
		}
		
	/*-----------------------------------------------------------------------*/
		
		public function prepare(array $data = null) {
			if (is_null($data)) return;
			
			$this->parameters()->{'section'} = null;
			$this->parameters()->{'create-cookie'} = false;
			$this->parameters()->{'overrides'} = array();
			$this->parameters()->{'defaults'} = array();
			
			// Essentials:
			$this->about()->name = $data['name'];
			$this->about()->author->name = Administration::instance()->User->getFullName();
			$this->about()->author->email = Administration::instance()->User->email;
			
			$this->parameters()->section = $data['section'];
			
			if (isset($data['create-cookie']) && $data['create-cookie'] == 'yes') {
				$this->parameters()->{'create-cookie'} = true;
			}
			
			if (isset($data['defaults']) && is_array($data['defaults']) || !empty($data['defaults'])) {
				$defaults = array();
				
				foreach ($data['defaults']['field'] as $index => $field) {
					$defaults[$field] = $data['defaults']['replacement'][$index];
				}
				
				$this->parameters()->defaults = $defaults;
			}
			
			if (isset($data['overrides']) && is_array($data['overrides']) || !empty($data['overrides'])) {
				$overrides = array();
				
				foreach ($data['overrides']['field'] as $index => $field) {
					$overrides[$field] = $data['overrides']['replacement'][$index];
				}
				
				$this->parameters()->overrides = $overrides;
			}
		}
		
		public function view(SymphonyDOMElement $wrapper, MessageStack $errors) {
			$page = Administration::instance()->Page;
			$page->insertNodeIntoHead($page->createScriptElement(URL . '/extensions/bbcww_api_client/assets/view.js'));
			$layout = new Layout;

			$left = $layout->createColumn(Layout::SMALL);
			$right = $layout->createColumn(Layout::LARGE);

			$fieldset = Widget::Fieldset(__('Essentials'));

			$label = Widget::Label(__('Name'));
			$label->appendChild(Widget::Input('fields[name]', General::sanitize($this->about()->name)));

			if(isset($errors->{'about::name'})){
				$fieldset->appendChild(Widget::wrapFormElementWithError($label, $errors->{'about::name'}));
			}
			else $fieldset->appendChild($label);

			$field_groups = $options = array();
			
			foreach ($this->driver->getMemberSections() as $section) {
				$source = $section->{'api-source'};
				$field_groups[$section->handle] = array(
					'fields'	=> $section->fields,
					'section'	=> $section
				);
				
				if (!isset($options[$source])) {
					$options[$source] = array(
						'label'		=> ucwords(strtr($source, '-', ' ')),
						'options'	=> array()
					);
				}
				
				$options[$source]['options'][] = array(
					$section->handle,
					($this->parameters()->section == $section->handle),
					$section->name
				);
			}
			
			$label = Widget::Label(__('Section'));
			$label->appendChild(Widget::Select('fields[section]', $options, array('id' => 'context')));
			$fieldset->appendChild($label);
			
			$label = Widget::Label();
			$input = Widget::Input('fields[create-cookie]', 'yes', 'checkbox');
			
			if ($this->parameters()->{'create-cookie'} == true) {
				$input->setAttribute('checked', 'checked');
			}
			
			$label->appendChild($input);
			$label->appendChild(new DOMText(__('Keep user logged in with a cookie?')));
			$fieldset->appendChild($label);
			$left->appendChild($fieldset);
			
			$fieldset = Widget::Fieldset(__('Overrides & Defaults'), '{$param}');

			foreach ($this->driver->getMemberSections() as $section) {
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

			$right->appendChild($fieldset);
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
				$options[] = array(General::sanitize($f->{'element-name'}), false, General::sanitize($f->{'publish-label'}));
			}

			$label->appendChild(Widget::Select('fields[overrides][field][]', $options));
			$item->appendChild($label);

			$label = Widget::Label(__('Replacement'));
			$label->appendChild(Widget::Input('fields[overrides][replacement][]'));
			$item->appendChild($label);
			
			$item = $duplicator->createTemplate(__('Default Value'));
			$label = Widget::Label(__('Field'));
			$options = array(array('system:id', false, 'System ID'));

			foreach($section->fields as $f){
				$options[] = array(General::sanitize($f->{'element-name'}), false, General::sanitize($f->{'publish-label'}));
			}

			$label->appendChild(Widget::Select('fields[defaults][field][]', $options));
			$item->appendChild($label);

			$label = Widget::Label(__('Replacement'));
			$label->appendChild(Widget::Input('fields[defaults][replacement][]'));
			$item->appendChild($label);

			if (is_array($items['overrides'])) {
				foreach ($items['overrides'] as $field_name => $replacement) {
					$item = $duplicator->createInstance(__('Override'));
					$label = Widget::Label(__('Field'));
					$options = array(array('system:id', false, __('System ID')));
					
					foreach ($section->fields as $f) {
						$options[] = array(
							General::sanitize($f->{'element-name'}),
							$f->{'element-name'} == $field_name,
							General::sanitize($f->{'publish-label'})
						);
					}
					
					$label->appendChild(Widget::Select('fields[overrides][field][]', $options));
					$item->appendChild($label);
					
					$label = Widget::Label(__('Replacement'));
					$label->appendChild(Widget::Input('fields[overrides][replacement][]', General::sanitize($replacement)));
					$item->appendChild($label);
				}
			}
			
			if (is_array($items['defaults'])) {
				foreach($items['defaults'] as $field_name => $replacement) {
					$item = $duplicator->createInstance(__('Default Value'));
					$label = Widget::Label(__('Field'));
					$options = array(array('system:id', false, __('System ID')));
					
					foreach ($section->fields as $f) {
						$options[] = array(
							General::sanitize($f->{'element-name'}),
							$f->{'element-name'} == $field_name,
							General::sanitize($f->{'publish-label'})
						);
					}
					
					$label->appendChild(Widget::Select('fields[defaults][field][]', $options));
					$item->appendChild($label);
					
					$label = Widget::Label(__('Replacement'));
					$label->appendChild(Widget::Input('fields[defaults][replacement][]', General::sanitize($replacement)));
					$item->appendChild($label);
				}
			}
			
			$duplicator->appendTo($wrapper);
		}
		
	/*-----------------------------------------------------------------------*/
		
		public function canTrigger(array $data) {
			$this->cookie = new Cookie($this->parameters()->{'section'});
			
			// Cookie data:
			if ($this->cookie->get('email') and $this->cookie->get('login')) return true;
			
			// Post data:
			if (!isset($data['action'][$this->parameters()->{'root-element'}])) return false;
			
			return true;
		}
		
		public function trigger(Register $parameter_output, array $data) {
			$errors = new MessageStack();
			$result = new XMLDocument();
			$result->appendChild($result->createElement(
				$this->parameters()->{'root-element'}
			));
			$result->formatOutput = true;
			$root = $result->documentElement;
			
			try {
				$status = $this->login($errors, $parameter_output, $data);
				$root->setAttribute('result', 'success');
			}
			
			catch (Exception $error) {
				$root->setAttribute('result', 'error');
				$root->appendChild($result->createElement(
					'message', $error->getMessage()
				));
				
				if ($errors->valid()) {
					$element = $result->createElement('errors');
					$this->appendMessages($element, $errors);
					$root->appendChild($element);
				}
				
				//echo '<pre>', htmlentities($result->saveXML()), '</pre>'; exit;
			}
			
			return $result;
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
		
		protected function login(MessageStack $errors, Register $parameter_output, array $data) {
			$section = Section::loadFromHandle($this->parameters()->{'section'});
			$wheres = array(); $joins = null;
			
			// Find fields:
			foreach ($section->fields as $field) {
				if ($field instanceof FieldMemberName) {
					$field_name = $field;
					$handle_name = $field->{'element-name'};
				}
				
				else if ($field instanceof FieldMemberEmail) {
					$field_email = $field;
					$handle_email = $field->{'element-name'};
				}
				
				else if ($field instanceof FieldMemberPassword) {
					$field_password = $field;
					$handle_password = $field->{'element-name'};
				}
			}
			
			if (!isset($field_email) or !isset($field_password)) {
				throw new Exception(__('Section does not contain required fields.'));
			}
			
			if (!isset($this->cookie)) {
				$this->cookie = new Cookie($this->parameters()->{'section'});
			}
			
			// Simulate data from cookie:
			if (empty($data) and $this->cookie->get('email') and $this->cookie->get('login')) {
				$fields = array(
					$field_email->{'element-name'}		=> $this->cookie->get('email'),
					$field_password->{'element-name'}	=> $this->cookie->get('login')
				);
			}
			
			else {
				$this->cookie->set('email', null);
				$this->cookie->set('login', null);
				$fields = $data['fields'];
			}
			
			// Apply default values:
			foreach ($this->parameters()->{'defaults'} as $name => $value) {
				if (!isset($fields[$name])) {
					$fields[$name] = $value;
				}
				
				else if (is_string($fields[$name]) and $fields[$name] == '') {
					$fields[$name] = $value;
				}
				
				else if (is_array($fields[$name]) and empty($fields[$name])) {
					$fields[$name] = array($value);
				}
			}
			
			// Apply override values:
			foreach ($this->parameters()->{'overrides'} as $name => $value) {
				if (is_array($fields[$name])) {
					$fields[$name] = array($value);
				}
				
				else {
					$fields[$name] = $value;
				}
			}
			
			// Find values:
			if (isset($field_name)) {
				$value_name = (
					(isset($fields[$handle_name]) and strlen($fields[$handle_name]) > 0)
						? $fields[$handle_name]
						: null
				);
			}
			
			if (isset($field_email)) {
				$value_email = (
					(isset($fields[$handle_email]) and strlen($fields[$handle_email]) > 0)
						? $fields[$handle_email]
						: null
				);
			}
			
			if (isset($field_password)) {
				$value_password = (
					(isset($fields[$handle_password]) and strlen($fields[$handle_password]) > 0)
						? $fields[$handle_password]
						: null
				);
			}
			
			if ((is_null($value_email) and is_null($value_name)) or is_null($value_password)) {
				throw new Exception(__('Missing login credentials.'));
			}
			
			// Build query:
			$where_password = array();
			$value_password = array(
				'value'		=> $value_password,
				'type'		=> 'is'
			);
			$field_password->buildFilterQuery($value_password, $joins, $where_password, $parameter_output);
			
			if (isset($field_email) and !is_null($value_email)) {
				$where_email = $where_password;
				$value_email = array(
					'value'		=> $value_email,
					'type'		=> 'is'
				);
				$field_email->buildFilterQuery($value_email, $joins, $where_email, $parameter_output);
				
				$wheres[] = '(' . implode("\nAND ", $where_email) . ')';
			}
			
			if (isset($field_name) and !is_null($value_name)) {
				$where_name = $where_password;
				$value_name = array(
					'value'		=> $value_name,
					'type'		=> 'is'
				);
				$field_name->buildFilterQuery($value_name, $joins, $where_name, $parameter_output);
				
				$wheres[] = '(' . implode("\nAND ", $where_name) . ')';
			}
			
			array_unshift($wheres, null);
			$wheres = implode("\nOR ", $wheres);
			
			$query = "
SELECT DISTINCT
	e.*
FROM
	`tbl_entries` AS e{$joins}
WHERE
	FALSE{$wheres}
			";
			
			//echo '<pre>', htmlentities($query), '</pre>'; exit;
			
			// Find entry:
			$result = Symphony::Database()->query($query, array(), 'EntryResult');
			
			if (!$result->valid()) {
				throw new Exception(__('Invalid login credentials.'));
			}
			
			$entry = $result->current();
			$email = $entry->data()->{$handle_email}->value;
			$code = $entry->data()->{$handle_password}->code;
			$login = $this->driver->createToken($code, 'login');
			
			if ($this->parameters()->{'create-cookie'} == true) {
				$this->cookie->set('email', $email);
				$this->cookie->set('login', $login);
			}
			
			$event_name =  $this->parameters()->{'root-element'};
			$parameter_output->{"event-{$event_name}.system.id"} = $entry->id;
			$parameter_output->{"event-{$event_name}.member.email"} = $email;
			$parameter_output->{"event-{$event_name}.member.login"} = $login;
			
			// Remove login fields:
			unset($fields[$handle_name], $fields[$handle_email], $fields[$handle_password]);
			
			// Set password as optional:
			$fields[$handle_password] = array(
				'validate'	=> array(
					'optional'	=> $this->driver->createToken($code, 'validate')
				),
				'change'	=> array(
					'optional'	=> $this->driver->createToken($code, 'change')
				)
			);
			
			// Update fields:
			$entry->setFieldDataFromFormArray($fields);
			
			###
			# Delegate: EntryPreCreate
			# Description: Just prior to creation of an Entry. Entry object provided
			Extension::notify(
				'EntryPreCreate', '/frontend/',
				array('entry' => &$entry)
			);
			
			$status = Entry::save($entry, $errors);
			
			if ($status != Entry::STATUS_OK) {
				throw new Exception(__('Entry encountered errors when saving.'));
			}
			
			###
			# Delegate: EntryPostCreate
			# Description: Creation of an Entry. New Entry object is provided.
			Extension::notify(
				'EntryPostCreate', '/frontend/',
				array('entry' => $entry)
			);
		}
	}
	
?>