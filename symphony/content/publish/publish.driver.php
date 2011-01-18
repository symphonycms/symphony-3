<?php

	/**
	* PublishDriver class...
	*/

	Class PublishDriver {

		public $document;
		public $url;
		public $view;
		public $section;

		public function __construct() {
			$this->view = Controller::instance()->View;
			$this->document = $this->view->document;
			$this->url = Controller::instance()->url;

			// Probably a neater way to store and fetch the section handle
			$this->section = Section::loadFromHandle($this->view->params[0]);
			
			$this->setTitle();
		}

		public function setTitle() {
			$this->view->title = __($this->section->__get('name'));
		}

		public function registerActions() {

			$actions = array(
				array(
					'name'		=> __('Create New'),
					'type'		=> 'new',
					'callback'	=> $url . '/new'
				),
				array(
					'name'		=> __('Filter'),
					'type'		=> 'tool'
				)
			);

			foreach($actions as $action) {
				$this->view->registerAction($action);
			}
		}

		public function registerDrawer() {
			// Do stuff
		}

		public function buildDataXML($data) {

			$section_xml = $this->document->createElement('section');
			$section_xml->setAttribute('handle', $this->section->handle);

			$filter = $filter_value = $where = $joins = NULL;

			$current_page = (isset($_REQUEST['pg']) && is_numeric($_REQUEST['pg']) ? max(1, intval($_REQUEST['pg'])) : 1);
			$current_filter = ($filter ? "&filter=$field_handle:$filter_value" : '');

			if(isset($_REQUEST['sort']) && is_string($_REQUEST['sort'])){
				$sort = $_REQUEST['sort'];
				$order = ($_REQUEST['order'] ? strtolower($_REQUEST['order']) : 'asc');

				if($this->section->{'publish-order-handle'} != $sort || $this->section->{'publish-order-direction'} != $order) {
					$this->section->{'publish-order-handle'} = $sort;
					$this->section->{'publish-order-direction'} = $order;

					$errors = new MessageStack;
					Section::save($this->section, $errors);
					redirect($url. $current_filter);
				}
			}
			elseif(isset($_REQUEST['unsort'])){
				$section->{'publish-order-handle'} = null;
				$section->{'publish-order-direction'} = null;

				$errors = new MessageStack;
				Section::save($section, $errors);

				redirect($URL);
			}

			$fields_xml = $this->document->createElement('fields');

			foreach($this->section->fields as $column){

				if($column->{'show-column'} == 'yes') {
					$field = $this->document->createElement(
						'field',
						$column->name
					);
					$field->setAttribute('handle', $column->{'element-name'});
					$fields_xml->appendChild($field);
				};

			}

			$section_xml->appendChild($fields_xml);

			try {
				$entry_count = Symphony::Database()->query(
					"SELECT COUNT(id) as `count` FROM `tbl_entries` WHERE `section` = '%s'", array($section->handle)
				)->current()->count;
			}
			catch (DatabaseException $ex) {

			}

			$pagination_xml = $this->document->createElement('pagination');

			$pagination_xml->appendChild($this->document->createElement(
				'total-entries',
				$entry_count
			));

			$pagination_xml->appendChild($this->document->createElement(
				'per-page',
				Symphony::Configuration()->core()->symphony->{'pagination-maximum-rows'}
			));

			$pagination_xml->appendChild($this->document->createElement(
				'total-pages',
				ceil($entry_count / Symphony::Configuration()->core()->symphony->{'pagination-maximum-rows'})
			));

			$pagination_xml->appendChild($this->document->createElement(
				'current-page',
				$current_page
			));

			$section_xml->appendChild($pagination_xml);

			// Simplified/messy entry fetching
			$entries = Symphony::Database()->query(
					"SELECT * FROM `tbl_entries` WHERE `section` = '%s' ORDER BY `id` ASC",
					array(
						$this->section->handle
					), 'EntryResult'
				);

			$entries_xml = $this->document->createElement('entries');

			foreach($entries as $entry){

				$entry_xml = $this->document->createElement('entry');

				$entry_xml->setAttribute('id', $entry->id);

				$fields_xml = $this->document->createElement('fields');

				foreach($this->section->fields as $column){
					if($column->{'show-column'} != 'yes') continue;

					$field_handle = $column->{'element-name'};

					$fields_xml->appendChild($this->document->createElement(
						$field_handle,
						$entry->data()->$field_handle->value
					));
				}

				$entry_xml->appendChild($fields_xml);
				$entries_xml->appendChild($entry_xml);
			}

			$section_xml->appendChild($entries_xml);

			$data->appendChild($section_xml);

		}
	}

	/*
	require_once(LIB . '/class.administrationpage.php');

	Class contentPublish extends AdministrationPage{

		private $entry;

		public function __switchboard($type='view'){

			$function = "__{$type}" . ucfirst($this->_context['page']);

			// If there is no view function, throw an error
			if (!is_callable(array($this, $function))){

				if ($type == 'view'){
					throw new AdministrationPageNotFoundException;
				}

				return false;
			}
			$this->$function();
		}

		public function view(){
			$this->__switchboard();
		}

		public function action(){
			$this->__switchboard('action');
		}

		public function __viewIndex(){

			/*
			**	Pagination, get the total number of entries and work out
			**	how many pages exist using the Symphony config
			**	TODO: Work with Ordering
			*
			try {
				$entry_count = Symphony::Database()->query(
					"SELECT COUNT(id) as `count` FROM `tbl_entries` WHERE `section` = '%s'", array($section->handle)
				)->current()->count;

				$pagination = array(
					'total-entries' => $entry_count,
					'entries-per-page' => Symphony::Configuration()->core()->symphony->{'pagination-maximum-rows'},
					'total-pages' => ceil($entry_count / Symphony::Configuration()->core()->symphony->{'pagination-maximum-rows'}),
					'current-page' => $current_page
				);
				$pagination['start'] = ($current_page != 1) ? ($current_page - 1) * $pagination['entries-per-page'] : 0;
				$pagination['remaining-entries'] = max(0, $entry_count - ($pagination['start'] + $pagination['entries-per-page']));
			}
			catch (DatabaseException $ex) {

			}

			//	If there's no sorting, just order by ID, otherwise applying column sorting
			if(!isset($section->{'publish-order-handle'}) || strlen($section->{'publish-order-handle'}) == 0) {
				$entries = Symphony::Database()->query(
					"SELECT * FROM `tbl_entries` WHERE `section` = '%s' ORDER BY `id` ASC LIMIT %d, %d ",
					array(
						$section->handle,
						$pagination['start'],
						$pagination['entries-per-page']
					), 'EntryResult'
				);
			}
			else {
				$join = NULL;
				$sort_field = $section->fetchFieldByHandle($section->{'publish-order-handle'});
				$sort_field->buildSortingQuery($join, $order);

				$joins .= sprintf($join, $sort_field->section, $sort_field->{'element-name'});
				$order = sprintf($order, $section->{'publish-order-direction'});

				// TODO: Implement filtering

				$query = sprintf("
					SELECT e.*
					FROM `tbl_entries` AS e
					%s
					WHERE e.section = '%s'
					ORDER BY %s
					LIMIT %d, %d",
					$joins, $section->handle, $order, $pagination['start'], $pagination['entries-per-page']
				);

				$entries = Symphony::Database()->query($query, array(
						$section->handle,
						$section->{'publish-order-handle'}
					), 'EntryResult'
				);
			}

			## Table Body
			$aTableBody = array();
			$colspan = count($aTableHead);

			if($entries->length() <= 0){
				$aTableBody[] = Widget::TableRow(
					array(
						Widget::TableData(__('None found.'), array(
								'class' => 'inactive',
								'colspan' => $colspan
							)
						)
					), array(
						'class' => 'odd'
					)
				);
			}

			else{

				foreach($entries as $entry){
					$cells = array();

					$link = Widget::Anchor(
						'None',
						Administration::instance()->getCurrentPageURL() . "/edit/{$entry->id}/",
						array('id' => $entry->id, 'class' => 'content')
					);

					$first = true;
					if($renderOnlyEntryIDColumn != true){
						foreach($section->fields as $column){
							if($column->{'show-column'} != 'yes') continue;

							$field_handle = $column->{'element-name'};
							if(!isset($entry->data()->$field_handle)){
								$cells[] = Widget::TableData(__('None'), array('class' => 'inactive'));
							}
							else{
								$value = $column->prepareTableValue(
									$entry->data()->$field_handle,
									($first == true ? $link : $this->createElement('span')),
									$entry
								);

								$cells[] = Widget::TableData(
									$value, ($value == __('None')) ? array('class' => 'inactive') : array()
								);
							}

							$first = false;
						}
					}
					else{
						$link->setValue($entry->id);
						$cells[] = Widget::TableData($link);
					}

					$cells[count($cells) - 1]->appendChild(
						Widget::Input('items['. $entry->id .']', NULL, 'checkbox')
					);

					if(!empty($cells)){
						$aTableBody[] = Widget::TableRow($cells);
					}
				}

			}

			$table = Widget::Table(Widget::TableHead($aTableHead), NULL, Widget::TableBody($aTableBody));
			$this->Form->appendChild($table);

			$tableActions = $this->createElement('div');
			$tableActions->setAttribute('class', 'actions');

			$options = array(
				array(NULL, false, __('With Selected...')),
				array('delete', false, __('Delete'))
			);

			$index = 2;
			foreach($section->fields as $field){
				if($field->canToggleData() != true) continue;

				$options[$index] = array('label' => __('Set %s', array($field->{'publish-label'})), 'options' => array());

				foreach ($field->getToggleStates() as $value => $state) {
					$options[$index]['options'][] = array('toggle::' . $field->{'element-name'} . '::' . $value, false, $state);
				}

				$index++;
			}

			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Submit('action[apply]', __('Apply')));

			$this->Form->appendChild($tableActions);

			if($pagination['total-pages'] > 1){
				$current_url = Administration::instance()->getCurrentPageURL() . '/?pg=';

				$ul = $this->createElement('ul');
				$ul->setAttribute('class', 'page');

				## First
				$li = $this->createElement('li');
				if($current_page > 1) $li->appendChild(Widget::Anchor(__('First'), $current_url . '1'));
				else $li->setValue(__('First'));
				$ul->appendChild($li);

				## Previous
				$li = $this->createElement('li');
				if($current_page > 1) $li->appendChild(Widget::Anchor(__('&larr; Previous'), $current_url . ($current_page - 1)));
				else $li->setValue(__('&larr; Previous'));
				$ul->appendChild($li);

				## Summary
				$li = $this->createElement('li', __('Page %1$s of %2$s', array($current_page, max($current_page, $pagination['total-pages']))));
				$li->setAttribute('title', __('Viewing %1$s - %2$s of %3$s entries', array(
					$pagination['start'] + 1,
					($current_page != $pagination['total-pages']) ? $current_page * $pagination['entries-per-page'] : $pagination['total-entries'],
					$pagination['total-entries']
				)));
				$ul->appendChild($li);

				## Next
				$li = $this->createElement('li');
				if($current_page < $pagination['total-pages']) $li->appendChild(Widget::Anchor(__('Next &rarr;'), $current_url . ($current_page + 1)));
				else $li->setValue(__('Next &rarr;'));
				$ul->appendChild($li);

				## Last
				$li = $this->createElement('li');
				if($current_page < $pagination['total-pages']) $li->appendChild(Widget::Anchor(__('Last'), $current_url . $pagination['total-pages']));
				else $li->setValue(__('Last'));
				$ul->appendChild($li);

				$this->Form->appendChild($ul);
			}
		}

		function __actionIndex(){
			$checked = is_array($_POST['items']) ? array_keys($_POST['items']) : null;
			$callback = Administration::instance()->getPageCallback();

			if(is_array($checked) && !empty($checked)){
				switch($_POST['with-selected']) {

	            	case 'delete':

						###
						# Delegate: Delete
						# Description: Prior to deletion of entries. Array of Entries is provided.
						#              The array can be manipulated
						Extension::notify('Delete', '/publish/', array('entry_id' => &$checked));

						foreach($checked as $entry_id){
							Entry::delete($entry_id);
						}

					 	redirect($_SERVER['REQUEST_URI']);

					default:

						## TODO: Add delegate
						//	I've add this in a 2.0.8 commit, look for it //brendan

						list($option, $field_handle, $value) = explode('::', $_POST['with-selected'], 3);

						if($option == 'toggle'){

							// TO DO: This is a funky way to access a field via its handle. Might need to rethink this
							$section = Section::loadFromHandle($callback['context']['section_handle']);
							foreach($section->fields as $f){
								if($f->{'element-name'} == $field_handle){
									$field = $f;
									break;
								}
							}

							if($field instanceof Field){
								foreach($checked as $entry_id){
									$entry = Entry::loadFromID($entry_id);

									$entry->data()->$field_handle = $field->processData($value, $entry);

									$this->errors->flush();
									Entry::save($entry, $this->errors);
								}
							}

							redirect($_SERVER['REQUEST_URI']);

						}

						break;
				}
			}
		}

		/* TODO: Remove once create/edit form becomes one and the same
		private function __wrapFieldWithDiv(Field $field, Entry $entry=NULL){
			$div = $this->createElement('div', NULL, array(
					'class' => sprintf('field field-%s %s %s',
						$field->handle(),
						($field->required == 'yes' ? 'required' : ''),
						$this->__calculateWidth($field->width)
					)
				)
			);

			$field->displayPublishPanel(
				$div, (!is_null($entry) ? $entry->getData($field->id) : NULL),
				(isset($this->_errors[$field->id]) ? $this->_errors[$field->id] : NULL),
				null,
				null,
				(!is_null($entry) && is_numeric($entry->get('id')) ? $entry->get('id') : NULL)
			);

			return $div;
		}

		public static function __calculateWidth($width) {
			switch($width) {
				case "3": return 'large';
				case "2": return 'medium';
				default: return 'small';
			}
		}

		public function __viewEdit(){
			try{
				$this->__form(Entry::loadFromID($this->_context['entry_id']));
			}
			catch(Exception $e){
				throw $e;
				//var_dump(Symphony::Database()); die();
			}
		}

		public function __viewNew(){
			$this->__form();
		}

		public function __form(Entry $existing=NULL){

			$callback = Administration::instance()->getPageCallback();
			$section = Section::loadFromHandle($callback['context']['section_handle']);

			// Check that a layout and fields exist
			if(isset($section->fields)) {
				$this->alerts()->append(
					__(
						'It looks like you\'re trying to create an entry. Perhaps you want fields first? <a href="%s">Click here to create some.</a>',
						array(
							ADMIN_URL . '/blueprints/sections/edit/' . $section->handle . '/'
						)
					),
					AlertStack::ERROR
				);
			}

			$this->Form->setAttribute('enctype', 'multipart/form-data');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), $section->name)));

			$subheading = __('New Entry');
			if(!is_null($existing) && $existing instanceof Entry){

				if(is_null($this->entry) || !($this->entry instanceof Entry)){
					$this->entry = $existing;
				}

				// Grab the first field in the section
				$first_field = $section->fields[0];
				$field_data = (object)array();

				if (!is_null($existing->data()->{$first_field->{'element-name'}})) {
					$field_data = $existing->data()->{$first_field->{'element-name'}};
				}

				$subheading = $first_field->prepareTableValue($field_data);
			}

			if(is_null($this->entry) || !($this->entry instanceof Entry)){
				$this->entry = new Entry;
			}

			$this->entry->section = $callback['context']['section_handle'];
			$this->appendSubheading($subheading);
			$this->entry->findDefaultFieldData();
			$this->Form->appendChild(Widget::Input(
				'MAX_FILE_SIZE',
				Symphony::Configuration()->core()->symphony->{'maximum-upload-size'},
				'hidden'
			));

			// Check if there is a field to prepopulate
			if (isset($_REQUEST['prepopulate']) && strlen(trim($_REQUEST['prepopulate'])) > 0) {
				$field_handle = key($_REQUEST['prepopulate']);
				$value = stripslashes(rawurldecode($_REQUEST['prepopulate'][$field_handle]));

				$prepopulate_filter = "?prepopulate[{$field_handle}]=" . rawurlencode($value);
				$this->Form->setAttribute('action', Administration::instance()->getCurrentPageURL() . $prepopulate_filter);

				if(is_null($existing)) {
					$this->entry->data()->{$field_handle}->value = $value;
				}
			}

			// Status message:
			if(isset($callback['flag']) and !is_null($callback['flag'])) {
				switch($callback['flag']){
					case 'saved':
						$this->alerts()->append(
							__(
								'Entry updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Entries</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/publish/'.$callback['context']['section_handle'].'/new/'.$prepopulate_filter,
									ADMIN_URL . '/publish/'.$callback['context']['section_handle'].'/'
								)
							),
							AlertStack::SUCCESS
						);
						break;

					case 'created':
						$this->alerts()->append(
							__(
								'Entry created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Entries</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/publish/'.$callback['context']['section_handle'].'/new/'.$prepopulate_filter,
									ADMIN_URL . '/publish/'.$callback['context']['section_handle'].'/'
								)
							),
							AlertStack::SUCCESS
						);
						break;
				}
			}

			// Load all the fields for this section
			$section_fields = array();
			foreach($section->fields as $index => $field) {
				$section_fields[$field->{'element-name'}] = $field;
			}

			$layout = new Layout;

			if(is_array($section->layout) && !empty($section->layout)) foreach ($section->layout as $data) {
				$column = $layout->createColumn($data->size);

				foreach ($data->fieldsets as $data) {
					$fieldset = $this->createElement('fieldset');

					if(isset($data->collapsed) && $data->collapsed == 'yes'){
						$fieldset->setAttribute('class', 'collapsed');
					}

					if(isset($data->name) && strlen(trim($data->name)) > 0){
						$fieldset->appendChild(
							$this->createElement('h3', $data->name)
						);
					}

					foreach ($data->fields as $handle) {
						$field = $section_fields[$handle];

						if (!$field instanceof Field) continue;

						$div = $this->createElement('div', NULL, array(
								'class' => trim(sprintf('field field-%s %s %s',
									$field->handle(),
									$this->__calculateWidth($field->width),
									($field->required == 'yes' ? 'required' : '')
								))
							)
						);

						$field->displayPublishPanel(
							$div,
							(isset($this->errors->{$field->{'element-name'}})
								? $this->errors->{$field->{'element-name'}}
								: new MessageStack),
							$this->entry,
							$this->entry->data()->{$field->{'element-name'}}
						);

						$fieldset->appendChild($div);
					}

					$column->appendChild($fieldset);
				}

				$layout->appendTo($this->Form);
			}

			else {
				$this->alerts()->append(
					__(
						'You haven\'t set any section layout rules. <a href="%s">Click here to define a layout.</a>',
						array(
							ADMIN_URL . '/blueprints/sections/layout/' . $section->handle . '/'
						)
					),
					AlertStack::ERROR
				);

				$column = $layout->createColumn(Layout::LARGE);
				$fieldset = $this->createElement('fieldset');
				$header = $this->createElement('h3', __('Untitled'));
				$fieldset->appendChild($header);

				if (is_array($section->fields)) foreach ($section->fields as $field) {
					$div = $this->createElement('div', NULL, array(
							'class' => trim(sprintf('field field-%s %s %s',
								$field->handle(),
								$this->__calculateWidth($field->width),
								($field->required == 'yes' ? 'required' : 'optional')
							))
						)
					);

					$field->displayPublishPanel(
						$div,
						(isset($this->errors->{$field->{'element-name'}})
							? $this->errors->{$field->{'element-name'}}
							: new MessageStack),
						$this->entry,
						$this->entry->data()->{$field->{'element-name'}}
					);

					$fieldset->appendChild($div);
				}

				$column->appendChild($fieldset);
				$layout->appendTo($this->Form);
			}

			$div = $this->createElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Submit(
					'action[save]', ($existing) ? __('Save Changes') : __('Create Entry'),
					array(
						'accesskey' => 's',
						'class'		=> 'constructive'
					)
				)
			);

			if(!is_null($existing)){
				$div->appendChild(
					Widget::Submit(
						'action[delete]', __('Delete'),
						array(
							'class' => 'confirm delete destructive',
							'title' => __('Delete this entry'),
						)
					)
				);
			}

			$this->Form->appendChild($div);
		}

		public function __actionNew(){

			$callback = Administration::instance()->getPageCallback();

			if(array_key_exists('save', $_POST['action']) || array_key_exists("done", $_POST['action'])) {

				$entry = new Entry;
				$entry->section = $callback['context']['section_handle'];
				$entry->user_id = Administration::instance()->User->id;

				$post = General::getPostData();
				if(isset($post['fields']) && is_array($post['fields']) && !empty($post['fields'])){
					$entry->setFieldDataFromFormArray($post['fields']);
				}

				$errors = new MessageStack;

				###
				# Delegate: EntryPreCreate
				# Description: Just prior to creation of an Entry. Entry object provided
				Extension::notify(
					'EntryPreCreate', '/publish/new/',
					array('entry' => &$entry)
				);

				$this->errors->flush();
				$status = Entry::save($entry, $this->errors);

				if($status == Entry::STATUS_OK){

					// Check if there is a field to prepopulate
					if (isset($_REQUEST['prepopulate']) && strlen(trim($_REQUEST['prepopulate'])) > 0) {
						$field_handle = key($_REQUEST['prepopulate']);
						$value = stripslashes(rawurldecode($_REQUEST['prepopulate'][$field_handle]));

						$prepopulate_filter = "?prepopulate[{$field_handle}]=" . rawurlencode($value);
					}
					else {
						$prepopulate_filter = null;
					}

					###
					# Delegate: EntryPostCreate
					# Description: Creation of an Entry. New Entry object is provided.
					Extension::notify(
						'EntryPostCreate', '/publish/new/',
						array('entry' => $entry)
					);

					## WOOT
					redirect(sprintf(
						'%s/symphony/publish/%s/edit/%d/:created/%s',
						URL,
						$entry->section,
						$entry->id,
						$prepopulate_filter
					));
				}

				// Oh dear
				$this->entry = $entry;

				$this->alerts()->append(
					__('An error occurred while processing this form. <a href="#error">See below for details.</a> <a class="more">Show a list of errors.</a>'),
					AlertStack::ERROR,
					$this->errors
				);
				return;
			}

		}

		public function __actionEdit(){

			$callback = Administration::instance()->getPageCallback();
			$entry_id = (int)$callback['context']['entry_id'];

			if(@array_key_exists('save', $_POST['action']) || @array_key_exists("done", $_POST['action'])){

				$entry = Entry::loadFromID($entry_id);

				$post = General::getPostData();
				$fields = array();

				if (isset($post['fields']) and !empty($post['fields'])) {
					$fields = $post['fields'];
				}

				$entry->setFieldDataFromFormArray($fields);

				###
				# Delegate: EntryPreEdit
				# Description: Just prior to editing of an Entry.
				Extension::notify(
					'EntryPreEdit', '/publish/edit/',
					array('entry' => &$entry)
				);

				$this->errors->flush();
				$status = Entry::save($entry, $this->errors);

				if($status == Entry::STATUS_OK){

					// Check if there is a field to prepopulate
					if (isset($_REQUEST['prepopulate']) && strlen(trim($_REQUEST['prepopulate'])) > 0) {
						$field_handle = key($_REQUEST['prepopulate']);
						$value = stripslashes(rawurldecode($_REQUEST['prepopulate'][$field_handle]));

						$prepopulate_filter = "?prepopulate[{$field_handle}]=" . rawurlencode($value);
					}
					else {
						$prepopulate_filter = null;
					}

					###
					# Delegate: EntryPostEdit
					# Description: Editing an entry. Entry object is provided.
					Extension::notify(
						'EntryPostEdit', '/publish/edit/',
						array('entry' => $entry)
					);

					## WOOT
					redirect(sprintf(
						'%s/symphony/publish/%s/edit/%d/:saved/%s',
						URL,
						$entry->section,
						$entry->id,
						$prepopulate_filter
					));
				}


				// Oh dear
				$this->entry = $entry;
				$this->alerts()->append(
					__('An error occurred while processing this form. <a href="#error">See below for details.</a> <a class="more">Show a list of errors.</a>'),
					AlertStack::ERROR
				);

				return;

			}

			elseif(@array_key_exists('delete', $_POST['action']) && is_numeric($entry_id)){
				$callback = Administration::instance()->getPageCallback();

				###
				# Delegate: Delete
				# Description: Prior to deleting an entry. Entry ID is provided, as an
				# array to remain compatible with other Delete delegate call
				Extension::notify('Delete', '/publish/', array('entry_id' => $entry_id));

				Entry::delete($entry_id);

				redirect(ADMIN_URL . '/publish/'.$callback['context']['section_handle'].'/');
			}

		}

	}*/
