<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.messagestack.php');
 	require_once(TOOLKIT . '/class.section.php');
	//require_once(TOOLKIT . '/class.entrymanager.php');

	Class contentBlueprintsSections extends AdministrationPage{

		private $section;

		public function __viewIndex(){
			// This is the 'correct' way to append a string containing an entity
			$title = $this->createElement('title');
			$title->appendChild($this->createTextNode(__('Symphony') . ' '));
			$title->appendChild($this->createEntityReference('ndash'));
			$title->appendChild($this->createTextNode(' ' . __('Sections')));
			$this->insertNodeIntoHead($title);

			$this->appendSubheading(__('Sections'), Widget::Anchor(
				__('Create New'), Administration::instance()->getCurrentPageURL().'new/', array(
					'title' => __('Create a new section'),
					'class' => 'create button'
				)
			));

		    $sections = new SectionIterator;

			$aTableHead = array(
				array(__('Name'), 'col'),
				array(__('Entries'), 'col'),
				array(__('Navigation Group'), 'col'),
			);

			$aTableBody = array();
			$colspan = count($aTableHead);

			if($sections->length() <= 0){
				$aTableBody = array(Widget::TableRow(
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

			else {
				foreach ($sections as $s) {
					$entry_count = 0;
					$result = Symphony::Database()->query(
						"
							SELECT
								count(*) AS `count`
							FROM
								`tbl_entries` AS e
							WHERE
								e.section = '%s'
						",
						array($s->handle)
					);

					if ($result->valid()) {
						$entry_count = (integer)$result->current()->count;
					}

					// Setup each cell
					$td1 = Widget::TableData(
						Widget::Anchor($s->name, Administration::instance()->getCurrentPageURL() . "edit/{$s->handle}/", array(
						'class' => 'content'
						))
					);
					$td2 = Widget::TableData(Widget::Anchor((string)$entry_count, ADMIN_URL . "/publish/{$s->handle}/"));
					$td3 = Widget::TableData($s->{'navigation-group'});
					$td3->appendChild(Widget::Input("items[{$s->handle}]", 'on', 'checkbox'));

					// Add a row to the body array, assigning each cell to the row
					$aTableBody[] = Widget::TableRow(array($td1, $td2, $td3));
				}
			}

			$table = Widget::Table(
				Widget::TableHead($aTableHead), NULL, Widget::TableBody($aTableBody)
			);
			$table->setAttribute('id', 'sections-list');

			$this->Form->appendChild($table);

			$tableActions = $this->createElement('div');
			$tableActions->setAttribute('class', 'actions');

			$options = array(
				array(NULL, false, __('With Selected...')),
				array('delete', false, __('Delete'), 'confirm'),
				array('delete-entries', false, __('Delete Entries'), 'confirm')
			);

			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));

			$this->Form->appendChild($tableActions);
		}

		private function __save(array $essentials = null, array $fields = null, array $layout = null, Section $section = null){
			if (is_null($section)) {
				$section = new Section;
				$section->path = SECTIONS;
			}
			
			$this->section = $section;
			$this->errors = new MessageStack;
			$old_handle = false;
			
			if (is_array($essentials)) {
				if ($essentials['name'] !== $this->section->name) {
					$old_handle = $this->section->handle;
				}
				
				$this->section->name = $essentials['name'];
				$this->section->{'navigation-group'} = $essentials['navigation-group'];
				$this->section->{'hidden-from-publish-menu'} = (
					isset($essentials['hidden-from-publish-menu'])
					&& $essentials['hidden-from-publish-menu'] == 'yes'
						? 'yes'
						: 'no'
				);
			}
			
			// Resave fields:
			if (!is_null($fields)) {
				$this->section->removeAllFields();

				if (is_array($fields) and !empty($fields)) {
					foreach ($fields as $field) {
						$this->section->appendField($field['type'], $field);
					}
				}
			}

			// Resave layout:
			if (!is_null($layout)) {
				foreach ($layout as &$column) {
					$column = (object)$column;

					if (is_array($column->fieldsets)) foreach ($column->fieldsets as &$fieldset) {
						$fieldset = (object)$fieldset;
					}
				}

				$this->section->layout = $layout;
			}

			try {
				Section::save($this->section, $this->errors);
				
				// Rename section:
				if ($old_handle !== false) {
					Section::rename($this->section, $old_handle);
				}
				
				else {
					Section::synchronise($this->section);
				}
				
				return true;
			}

			catch (SectionException $e) {
				switch($e->getCode()){
					case Section::ERROR_MISSING_OR_INVALID_FIELDS:
						$this->pageAlert(__('Could not save the layout, there are errors in your field configuration.'), Alert::ERROR);
						break;
					case Section::ERROR_FAILED_TO_WRITE:
						$this->pageAlert($e->getMessage(), Alert::ERROR);
						break;
				}
			}

			catch (Exception $e) {
				$this->pageAlert(__('An unknown error has occurred. %s', array($e->getMessage())), Alert::ERROR);
			}

			return false;
		}

		public function __actionIndex() {
			$checked = array_keys($_POST['items']);

			if(is_array($checked) && !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						$this->__actionDelete($checked, ADMIN_URL . '/blueprints/sections/');
						break;

					case 'delete-entries':
						// TODO: Call EntryManager to delete Entrys where Section Handle = this one
						break;
				}
			}
		}

		public function __actionLayout() {
			$context = $this->_context;
			array_shift($context);

			$section_pathname = implode('/', $context);

			if (isset($_POST['action']['save'])) {
				$layout = (isset($_POST['layout']) ? $_POST['layout'] : null);
				$section = Section::load(SECTIONS . '/' . $this->_context[1] . '.xml');

				if ($this->__save(null, null, $layout, $section) == true) {
					redirect(ADMIN_URL . "/blueprints/sections/layout/{$this->section->handle}/:saved/");
				}
			}
		}

		public function __actionNew(){
			if(isset($_POST['action']['save'])){
				if($this->__save($_POST['essentials'], (isset($_POST['fields']) ? $_POST['fields'] : null)) == true){
					redirect(ADMIN_URL . "/blueprints/sections/edit/{$this->section->handle}/:created/");
				}
			}
		}

		public function __actionEdit(){
			$context = $this->_context;
			array_shift($context);

			$section_pathname = implode('/', $context);

			if(array_key_exists('delete', $_POST['action'])) {
				$this->__actionDelete(array($section_pathname), ADMIN_URL . '/blueprints/sections/');
			}

			else if (array_key_exists('save', $_POST['action'])) {
				$essentials = $_POST['essentials'];
				$fields = (isset($_POST['fields']) ? $_POST['fields'] : null);
				$section = Section::load(SECTIONS . '/' . $this->_context[1] . '.xml');

				if ($this->__save($essentials, $fields, null, $section) == true) {
					redirect(ADMIN_URL . "/blueprints/sections/edit/{$this->section->handle}/:saved/");
				}
			}
		}

		public function __actionDelete(array $sections, $redirect) {
			$success = true;

			foreach($sections as $handle){
				try{
					Section::delete(Section::loadFromHandle($handle));
				}
				catch(SectionException $e){
					$success = false;
					$this->pageAlert($e->getMessage(), Alert::ERROR);
				}
				catch(Exception $e){
					$success = false;
					$this->pageAlert(__('An unknown error has occurred. %s', array($e->getMessage())), Alert::ERROR);
				}
			}

			if($success) redirect($redirect);
		}

		private static function __loadExistingSection($handle){
			try{
				return Section::load(SECTIONS . "/{$handle}.xml");
			}
			catch(SectionException $e){

				switch($e->getCode()){
					case Section::ERROR_SECTION_NOT_FOUND:
						throw new SymphonyErrorPage(
							__('The section you requested to edit does not exist.'),
							__('Section not found'), NULL,
							array('HTTP/1.0 404 Not Found')
						);
						break;

					default:
					case Section::ERROR_FAILED_TO_LOAD:
						throw new SymphonyErrorPage(
							__('The section you requested could not be loaded. Please check it is readable.'),
							__('Failed to load section')
						);
						break;
				}
			}
			catch(Exception $e){
				throw new SymphonyErrorPage(
					sprintf(__("An unknown error has occurred. %s"), $e->getMessage()),
					__('Unknown Error'), NULL,
					array('HTTP/1.0 500 Internal Server Error')
				);
			}
		}

		public function appendViewOptions() {
			$context = $this->_context;
			array_shift($context);
			$view_pathname = implode('/', $context);
			$view_options = array(
				__('Configuration')			=>	ADMIN_URL . '/blueprints/sections/edit/' . $view_pathname . '/',
				__('Layout')				=>	ADMIN_URL . '/blueprints/sections/layout/' . $view_pathname . '/',
			);

			parent::appendViewOptions($view_options);
		}

		public function appendColumn(SymphonyDOMElement $wrapper, $size = Layout::LARGE, $fieldsets = array(), &$fields = null) {
			$document = $wrapper->ownerDocument;
			$column = $document->createElement('li');
			$column->setAttribute('class', 'column size-' . $size);

			if (!empty($fieldsets)) foreach ($fieldsets as $data) {
				$fieldset = $document->createElement('fieldset');
				$header = $document->createElement('h3');
				$list = $document->createElement('ol');
				$list->setAttribute('class', 'fields');

				$input = Widget::Input('name', $data->name);

				$header->appendChild($input);
				$fieldset->appendChild($header);

				if (!empty($data->fields)) foreach ($data->fields as $data) {
					if (!isset($fields[$data])) continue;

					$field = $fields[$data];
					unset($fields[$data]);

					$this->appendField($list, $field);
				}

				$fieldset->appendChild($list);
				$column->appendChild($fieldset);
			}

			$wrapper->appendChild($column);
		}

		public function appendField(SymphonyDOMElement $wrapper, Field $field) {
			$document = $wrapper->ownerDocument;
			$item = $document->createElement('li');
			$item->setAttribute('class', 'field');

			$name = $document->createElement('span', $field->label);
			$name->setAttribute('class', 'name');
			$name->appendChild($document->createElement('i', $field->name()));
			$item->appendChild($name);

			$input = Widget::Input('name', $field->{'element-name'}, 'hidden');
			$item->appendChild($input);

			$wrapper->appendChild($item);
		}

		public function __viewLayout() {
			$existing = self::__loadExistingSection($this->_context[1]);

			if(!($this->section instanceof Section)){
				$this->section = $existing;
			}

			$this->__layout($existing);
		}

		public function __viewNew(){
			if(!($this->section instanceof Section)){
				$this->section = new Section;
			}

			$this->__form();
		}

		public function __viewEdit(){
			$existing = self::__loadExistingSection($this->_context[1]);

			if(!($this->section instanceof Section)){
				$this->section = $existing;
			}

			$this->__form($existing);
		}

		private function __layout(Section $existing = null) {
			// Status message:
			$callback = Administration::instance()->getPageCallback();
			if(isset($callback['flag']) && !is_null($callback['flag'])){
				switch($callback['flag']){
					case 'saved':
						$this->pageAlert(
							__(
								'Section updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Sections</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/blueprints/sections/new/',
									ADMIN_URL . '/blueprints/sections/',
								)
							),
							Alert::SUCCESS
						);
						break;
				}
			}

			$layout = new Layout();
			$content = $layout->createColumn(Layout::LARGE);
			$fieldset = Widget::Fieldset(__('Layout'));

			if(!($this->section instanceof Section)){
				$this->section = new Section;
			}

			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Sections'))));
			$this->appendSubheading(($existing instanceof Section ? $existing->name : __('Untitled')));

			$this->appendViewOptions();

			$widget = $this->createElement('div');
			$widget->setAttribute('id', 'section-layout');

			$layouts = $this->createElement('ol');
			$layouts->setAttribute('class', 'layouts');

			$templates = $this->createElement('ol');
			$templates->setAttribute('class', 'templates');

			$columns = $this->createElement('ol');
			$columns->setAttribute('class', 'columns');

			// Load fields:
			$fields = $this->section->fields;

			foreach ($fields as $index => $field) {
				$name = $field->{'element-name'};
				$fields[$name] = $field;

				unset($fields[$index]);
			}

			// Layouts:
			$layout_options = array(
				array(Layout::LARGE),
				array(Layout::LARGE, Layout::LARGE),
				array(Layout::LARGE, Layout::SMALL),
				array(Layout::SMALL, Layout::LARGE),
				array(Layout::LARGE, Layout::LARGE, Layout::LARGE),
				array(Layout::LARGE, Layout::LARGE, Layout::LARGE, Layout::LARGE)
			);

			foreach ($layout_options as $layout_columns) {
				$item = $this->createElement('li');
				$div = $this->createElement('div');

				foreach ($layout_columns as $index => $size) {
					$element = $this->createElement('div');
					$element->setAttribute('class', 'column size-' . $size);

					$span = $this->createElement('span', chr(97 + $index));
					$element->appendChild($span);

					$div->appendChild($element);
				}

				$item->appendChild($div);
				$layouts->appendChild($item);
			}

			// Default columns:
			if (empty($this->section->layout)) {
				$this->appendColumn($columns, Layout::LARGE);
				$this->appendColumn($columns, Layout::SMALL);
			}

			// Current columns:
			else foreach ($this->section->layout as $column) {
				if ($column->size == Layout::LARGE) {
					$this->appendColumn($columns, Layout::LARGE, $column->fieldsets, $fields);
				}

				else {
					$this->appendColumn($columns, Layout::SMALL, $column->fieldsets, $fields);
				}
			}

			// Templates:
			if (is_array($fields)) foreach($fields as $position => $field) {
				$this->appendField($templates, $field);
			}

			$widget->appendChild($layouts);
			$widget->appendChild($templates);
			$widget->appendChild($columns);
			$fieldset->appendChild($widget);
			$content->appendChild($fieldset);
			$layout->appendTo($this->Form);

			$div = $this->createElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[save]', __('Save Changes'), 'submit', array('accesskey' => 's')));

			if($this->_context[0] == 'edit'){
				$div->appendChild(
					$this->createElement('button', __('Delete'), array(
						'name' => 'action[delete]',
						'class' => 'confirm delete',
						'title' => __('Delete this section'),
						'type' => 'submit'
						)
					)
				);
			}

			$this->Form->appendChild($div);
		}

		private function __form(Section $existing = null){
			// Status message:
			$callback = Administration::instance()->getPageCallback();

			if (isset($callback['flag']) && !is_null($callback['flag'])) {
				switch($callback['flag']){
					case 'saved':
						$this->pageAlert(
							__(
								'Section updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/blueprints/sections/new/',
									ADMIN_URL . '/blueprints/sections/',
								)
							),
							Alert::SUCCESS
						);
						break;
					case 'created':
						$this->pageAlert(
							__(
								'Section created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/blueprints/sections/new/',
									ADMIN_URL . '/blueprints/sections/',
								)
							),
							Alert::SUCCESS
						);
						break;
				}
			}

			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$right = $layout->createColumn(Layout::LARGE);

			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Sections'))));
			$this->appendSubheading(($existing instanceof Section ? $existing->name : __('New Section')));

			if ($existing instanceof Section) {
				$this->appendViewOptions();
			}

			/*
			TODO: This code should become MessageStack::appendTo(...)

			// Show errors:
			function appendErrorSummary(SymphonyDOMElement $wrapper, MessageStack $messages) {
				$document = $wrapper->ownerDocument;
				$list = $document->createElement('ol');
				$list->setAttribute('class', 'error-list');

				foreach ($messages as $key => $message) {
					if ($message instanceof MessageStack) {
						$item = $document->createElement('li', $key . ':');

						appendErrorSummary($item, $message);
					}

					else {
						$item = $document->createElement('li', $key . ': ' . $message);
					}

					$list->appendChild($item);
				}

				$wrapper->appendChild($list);
			}

			appendErrorSummary($this->Form, $this->errors);
			*/

			// Essentials:
			$fieldset = $this->createElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(
				$this->createElement('h3', __('Essentials'))
			);

			$label = Widget::Label('Name');
			$label->appendChild(Widget::Input('essentials[name]', $this->section->name));

			$fieldset->appendChild((
				isset($this->errors->name)
					? Widget::wrapFormElementWithError($label, $this->errors->name)
					: $label
			));

			$input = Widget::Input('essentials[hidden-from-publish-menu]', 'yes', 'checkbox',
				($this->section->{'hidden-from-publish-menu'} == 'yes') ? array('checked' => 'checked') : array()
			);

			$label = Widget::Label(__('Hide this section from the Publish menu'));
			$label->prependChild($input);

			$fieldset->appendChild($label);

			$label = Widget::Label(__('Navigation Group'));
			$label->appendChild($this->createElement('i', __('Created if does not exist')));
			$label->appendChild(Widget::Input('essentials[navigation-group]', $this->section->{"navigation-group"}));

			$fieldset->appendChild((
				isset($this->errors->{'navigation-group'})
					? Widget::wrapFormElementWithError($label, $this->errors->{'navigation-group'})
					: $label
			));

			$navigation_groups = Section::fetchUsedNavigationGroups();

			if(is_array($navigation_groups) && !empty($navigation_groups)){
				$ul = $this->createElement('ul', NULL, array('class' => 'tags singular'));
				foreach($navigation_groups as $g){
					$ul->appendChild($this->createElement('li', $g));
				}
				$fieldset->appendChild($ul);
			}

			$left->appendChild($fieldset);

			// Fields
			$fieldset = $this->createElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild($this->createElement('h3', __('Fields')));

			$div = $this->createElement('div');
			$h3 = $this->createElement('h3', __('Fields'));
			$h3->setAttribute('class', 'label');
			$div->appendChild($h3);

			$duplicator = $this->createElement('div');
			$duplicator->setAttribute('id', 'section-duplicator');

			$templates = $this->createElement('ol');
			$templates->setAttribute('class', 'templates');

			$instances = $this->createElement('ol');
			$instances->setAttribute('class', 'instances');

			$ol = $this->createElement('ol');
			$ol->setAttribute('id', 'section-' . $section_id);
			$ol->setAttribute('class', 'section-duplicator');

			$fields = $this->section->fields;
			$types = array();

			foreach (new FieldIterator as $pathname){
				$type = preg_replace(array('/^field\./', '/\.php$/'), NULL, basename($pathname));
				$types[$type] = Field::load($pathname);
			}

			// To Do: Sort this list based on how many times a field has been used across the system
			uasort($types, create_function('$a, $b', 'return strnatcasecmp($a->name(), $b->name());'));

			if (is_array($types)) foreach ($types as $type => $field) {
				$defaults = array();

				$field->findDefaultSettings($defaults);

				foreach ($defaults as $key => $value) {
					$field->$key = $value;
				}

				$item = $this->createElement('li');

				$field->displaySettingsPanel($item, new MessageStack);
				$templates->appendChild($item);
			}

			if (is_array($fields)) foreach($fields as $position => $field) {
				$field->sortorder = $position;

				if ($this->errors->{"field::{$position}"}) {
					$messages = $this->errors->{"field::{$position}"};
				}

				else {
					$messages = new MessageStack;
				}

				$item = $this->createElement('li');
				$field->displaySettingsPanel($item, $messages);

				$instances->appendChild($item);
			}

			$duplicator->appendChild($templates);
			$duplicator->appendChild($instances);
			$fieldset->appendChild($duplicator);
			$right->appendChild($fieldset);

			$layout->appendTo($this->Form);

			$div = $this->createElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[save]', __('Save Changes'), 'submit', array('accesskey' => 's')));

			if($this->_context[0] == 'edit'){
				$div->appendChild(
					$this->createElement('button', __('Delete'), array(
						'name' => 'action[delete]',
						'class' => 'confirm delete',
						'title' => __('Delete this section'),
						'type' => 'submit'
						)
					)
				);
			}

			$this->Form->appendChild($div);
		}
	}
