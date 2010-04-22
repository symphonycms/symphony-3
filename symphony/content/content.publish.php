<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	//require_once(TOOLKIT . '/class.sectionmanager.php');

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

			$section = Section::load(sprintf('%s/%s.xml', SECTIONS, $this->_context['section_handle']));

			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), $section->name)));
			$this->Form->setAttribute("class", $section->handle);

			$filter = $filter_value = $where = $joins = NULL;

			$current_page = (isset($_REQUEST['pg']) && is_numeric($_REQUEST['pg']) ? max(1, intval($_REQUEST['pg'])) : 1);
			$current_filter = ($filter ? "&filter=$field_handle:$filter_value" : '');

			if(isset($_REQUEST['sort']) && is_string($_REQUEST['sort'])){
				$sort = $_REQUEST['sort'];
				$order = ($_REQUEST['order'] ? strtolower($_REQUEST['order']) : 'asc');

				if($section->{'publish-order-handle'} != $sort || $section->{'publish-order-direction'} != $order) {
					$section->{'publish-order-handle'} = $sort;
					$section->{'publish-order-direction'} = $order;

					$errors = new MessageStack;
					Section::save($section, $errors);
					redirect(Administration::instance()->getCurrentPageURL(). $current_filter);
				}
			}
			elseif(isset($_REQUEST['unsort'])){
				$section->{'publish-order-handle'} = null;
				$section->{'publish-order-direction'} = null;

				$errors = new MessageStack;
				Section::save($section, $errors);

				redirect(Administration::instance()->getCurrentPageURL());
			}

			$this->appendSubheading(
				$section->name,
				Widget::Anchor(
					__('Create New'),
					sprintf('%snew/%s', Administration::instance()->getCurrentPageURL(), ($filter ? "?prepopulate[{$filter}]={$filter_value}" : NULL)), array(
						'title' => __('Create a new entry'),
						'class' => 'create button'
					)
				)
			);

			$aTableHead = array();
			$renderOnlyEntryIDColumn = false;

			foreach($section->fields as $column){
				if($column->{'show-column'} != 'yes') continue;

				$label = $column->label;

				if($column->isSortable()) {

					$link = Administration::instance()->getCurrentPageURL();

					if($column->{'element-name'} == $section->{'publish-order-handle'}) {
						$link .= '?pg=' . $current_page . '&sort=' . $column->{'element-name'};
						$link .= '&order=' . ($section->{'publish-order-direction'} == 'desc' ? 'asc' : 'desc') . $current_filter;

						$anchor = Widget::Anchor($label, $link, array(
							'title' => __('Sort by %1$s %2$s', array(
								($section->{'publish-order-direction'} == 'desc' ? __('ascending') : __('descending')),
								strtolower($column->label)
							)),
							'class' => 'active'
						));
					}
					else {
						$link .= '?pg='.$current_page.'&sort='.$column->{'element-name'}.'&order=asc'.$current_filter;
						$anchor = Widget::Anchor($label, $link, array(
							'title' => __('Sort by %1$s %2$s', array(__('ascending'), strtolower($column->label)))
						));
					}

					$aTableHead[] = array($anchor, 'col');
				}

				else {
					$aTableHead[] = array($label, 'col');
				}
			}

			if(count($aTableHead) <= 0){
				$renderOnlyEntryIDColumn = true;
				$aTableHead[] = array('ID', 'col');
			}

			/*
			$entry = Entry::loadFromID(3);

			$entry = new Entry;
			$entry->section = 'blog';
			$entry->user_id = Administration::instance()->User->id;
			$entry->id = 3;

			$entry->data()->name = (object)array(
				'handle' => 'an-entry',
				'value' => 'An & Entry',
				'id' => 1,
				'entry_id' => $entry->id
			);

			$entry->data()->content = (object)array(
				'handle' => 'an-entry',
				'value' => 'Look at my copy isn\'t it grand!',
				'id' => 1,
				'entry_id' => $entry->id
			);

			$entry->data()->date = (object)array(
				'gmt' => strtotime(DateTimeObj::getGMT('c')),
				'local' => strtotime(DateTimeObj::get('c')),
				'value' => DateTimeObj::get('c'),
				'id' => 1,
				'entry_id' => $entry->id
			);

			$entry->data()->category = (object)array(
				'handle' => 'blah',
				'value' => 'Blah &',
				'id' => 1,
				'entry_id' => $entry->id
			);

			$entry->data()->user = (object)array(
				'id' => 1,
				'entry_id' => $entry->id,
				'user_id' => 1
			);

			$entry->data()->published = (object)array(
				'id' => 1,
				'entry_id' => $entry->id,
				'value' => 'no'
			);

			$entry->data()->tags = (object)array(
				'id' => 1,
				'entry_id' => $entry->id,
				'handle' => 'tag',
				'value' => 'Tag'
			);

			$entry->data()->upload = (object)array(
				'id' => 1,
				'entry_id' => $entry->id,
				'name' => 'Image 1',
				'file' => '/path/to/file',
				'size' => 2342343,
				'mimetype' => 'image/jpeg',
				'meta' => 'blah'
			);
*/
			//$messages = new MessageStack;
			//Entry::save($entry, $messages);
			//var_dump($messages); die();

			/*
			**	Pagination, get the total number of entries and work out
			**	how many pages exist using the Symphony config
			**	TODO: Work with Ordering
			*/
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
				$sort_field = $section->fetchFieldByHandle($section->{'publish-order-handle'});
				$sort_field->buildSortingSQL($joins, $order, $section->{'publish-order-direction'});

				$query = sprintf("
					SELECT e.*
					FROM `tbl_entries` AS e
					%s
					WHERE `section` = '%s'
					%s
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
						Administration::instance()->getCurrentPageURL() . "edit/{$entry->id}/",
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
									($first == true ? $link : NULL)
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

				$options[$index] = array('label' => __('Set %s', array($field->label)), 'options' => array());

				foreach ($field->getToggleStates() as $value => $state) {
					$options[$index]['options'][] = array('toggle::' . $field->{'element-name'} . '::' . $value, false, $state);
				}

				$index++;
			}

			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));

			$this->Form->appendChild($tableActions);

			if($pagination['total-pages'] > 1){
				$current_url = Administration::instance()->getCurrentPageURL() . '?pg=';

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

			// TODO: Fix Filtering
			/*if(isset($_REQUEST['filter'])){

				list($field_handle, $filter_value) = explode(':', $_REQUEST['filter'], 2);

				$field_names = explode(',', $field_handle);

				foreach($field_names as $field_name) {

					$filter_value = rawurldecode($filter_value);

					$filter = Symphony::Database()->fetchVar('id', 0, "SELECT `f`.`id`
																			   FROM `tbl_fields` AS `f`, `tbl_sections` AS `s`
																			   WHERE `s`.`id` = `f`.`parent_section`
																			   AND f.`element_name` = '$field_name'
																			   AND `s`.`handle` = '".$section->handle."' LIMIT 1");
					$field = FieldManager::instance()->fetch($filter);

					if(is_object($field)){
						$field->buildDSRetrivalSQL(array($filter_value), $joins, $where, false);
						$filter_value = rawurlencode($filter_value);
					}

				}

				if ($where != null) {
					$where = str_replace('AND', 'OR', $where); // multiple fields need to be OR
					$where = trim($where);
					$where = ' AND (' . substr($where, 2, strlen($where)) . ')'; // replace leading OR with AND
				}

			}*/

			// TODO: Fix Sorting
			/*if(isset($_REQUEST['sort']) && is_numeric($_REQUEST['sort'])){
				$sort = intval($_REQUEST['sort']);
				$order = ($_REQUEST['order'] ? strtolower($_REQUEST['order']) : 'asc');

				if($section->get('entry_order') != $sort || $section->get('entry_order_direction') != $order){
					SectionManager::instance()->edit($section->get('id'), array('entry_order' => $sort, 'entry_order_direction' => $order));
					redirect(Administration::instance()->getCurrentPageURL().($filter ? "?filter=$field_handle:$filter_value" : ''));
				}
			}

			elseif(isset($_REQUEST['unsort'])){
				SectionManager::instance()->edit($section->get('id'), array('entry_order' => NULL, 'entry_order_direction' => NULL));
				redirect(Administration::instance()->getCurrentPageURL());
			}*/

			/*
			$this->Form->setAttribute('action', Administration::instance()->getCurrentPageURL(). '?pg=' . $current_page.($filter ? "&amp;filter=$field_handle:$filter_value" : ''));

			## Remove the create button if there is a section link field, and no filtering set for it
			$section_links = $section->fetchFields('sectionlink');

			if(count($section_links) > 1 || (!$filter && $section_links) || (is_object($section_links[0]) && $filter != $section_links[0]->get('id'))){
				$this->appendSubheading($section->get('name'));
			}
			else{
				$this->appendSubheading($section->get('name'), Widget::Anchor(__('Create New'), Administration::instance()->getCurrentPageURL().'new/'.($filter ? '?prepopulate['.$filter.']=' . $filter_value : ''), __('Create a new entry'), 'create button'));
			}

			if(is_null(EntryManager::instance()->getFetchSorting()->field) && is_null(EntryManager::instance()->getFetchSorting()->direction)){
				EntryManager::instance()->setFetchSortingDirection('DESC');
			}

			$entries = EntryManager::instance()->fetchByPage($current_page, $section_id, Symphony::Configuration()->get('pagination_maximum_rows', 'symphony'), $where, $joins);

			$aTableHead = array();

			$visible_columns = $section->fetchVisibleColumns();

			if(is_array($visible_columns) && !empty($visible_columns)){
				foreach($visible_columns as $column){

					$label = $column->label;

					if($column->isSortable()) {

						if($column->id == $section->get('entry_order')){
							$link = Administration::instance()->getCurrentPageURL() . '?pg='.$current_page.'&amp;sort='.$column->id.'&amp;order='. ($section->get('entry_order_direction') == 'desc' ? 'asc' : 'desc').($filter ? "&amp;filter=$field_handle:$filter_value" : '');
							$anchor = Widget::Anchor($label, $link, __('Sort by %1$s %2$s', array(($section->get('entry_order_direction') == 'desc' ? __('ascending') : __('descending')), strtolower($column->label))), 'active');
						}

						else{
							$link = Administration::instance()->getCurrentPageURL() . '?pg='.$current_page.'&amp;sort='.$column->id.'&amp;order=asc'.($filter ? "&amp;filter=$field_handle:$filter_value" : '');
							$anchor = Widget::Anchor($label, $link, __('Sort by %1$s %2$s', array(__('ascending'), strtolower($column->label))));
						}

						$aTableHead[] = array($anchor, 'col');
					}

					else $aTableHead[] = array($label, 'col');
				}
			}

			else $aTableHead[] = array(__('ID'), 'col');

			$child_sections = NULL;

			$associated_sections = $section->fetchAssociatedSections();
			if(is_array($associated_sections) && !empty($associated_sections)){
				$child_sections = array();
				foreach($associated_sections as $key => $as){
					$child_sections[$key] = SectionManager::instance()->fetch($as['child_section_id']);
					$aTableHead[] = array($child_sections[$key]->get('name'), 'col');
				}
			}

			## Table Body
			$aTableBody = array();

			if(!is_array($entries['records']) || empty($entries['records'])){

				$aTableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None found.'), 'inactive', NULL, count($aTableHead))), 'odd')
				);
			}

			else{

				$bOdd = true;


				$field_pool = array();
				if(is_array($visible_columns) && !empty($visible_columns)){
					foreach($visible_columns as $column){
						$field_pool[$column->get('id')] = $column;
					}
				}

				foreach($entries['records'] as $entry){

					$tableData = array();

					## Setup each cell
					if(!is_array($visible_columns) || empty($visible_columns)){
						$tableData[] = Widget::TableData(Widget::Anchor($entry->get('id'), Administration::instance()->getCurrentPageURL() . 'edit/' . $entry->get('id') . '/'));
					}

					else{

						$link = Widget::Anchor(
							'None',
							Administration::instance()->getCurrentPageURL() . 'edit/' . $entry->get('id') . '/',
							$entry->get('id'),
							'content'
						);

						foreach ($visible_columns as $position => $column) {
							$data = $entry->getData($column->get('id'));
							$field = $field_pool[$column->id];

							$value = $field->prepareTableValue($data, ($position == 0 ? $link : null), $entry->get('id'));

							if (!is_object($value) && strlen(trim($value)) == 0) {
								$value = ($position == 0 ? $link->generate() : __('None'));
							}

							if ($value == 'None') {
								$tableData[] = Widget::TableData($value, 'inactive');

							} else {
								$tableData[] = Widget::TableData($value);
							}

							unset($field);
						}
					}

					if(is_array($child_sections) && !empty($child_sections)){
						foreach($child_sections as $key => $as){

							$field = FieldManager::instance()->fetch((int)$associated_sections[$key]['child_section_field_id']);

							$parent_section_field_id = (int)$associated_sections[$key]['parent_section_field_id'];

							if(!is_null($parent_section_field_id)){
								$search_value = $field->fetchAssociatedEntrySearchValue(
									$entry->getData($parent_section_field_id),
									$parent_section_field_id,
									$entry->get('id')
								);
							}

							else{
								$search_value = $entry->get('id');
							}

							$associated_entry_count = $field->fetchAssociatedEntryCount($search_value);

							$tableData[] = Widget::TableData(
								Widget::Anchor(
									sprintf('%d &rarr;', max(0, intval($associated_entry_count))),
									sprintf(
										'%s/symphony/publish/%s/?filter=%s:%s',
										URL,
										$as->get('handle'),
										$field->{'element-name'},
										rawurlencode($search_value)
									),
									$entry->get('id'),
									'content')
							);
						}
					}

					$tableData[count($tableData) - 1]->appendChild(Widget::Input('items['.$entry->get('id').']', NULL, 'checkbox'));

					## Add a row to the body array, assigning each cell to the row
					$aTableBody[] = Widget::TableRow($tableData, ($bOdd ? 'odd' : NULL));

					$bOdd = !$bOdd;

				}
			}

			$table = Widget::Table(
				Widget::TableHead($aTableHead),
				NULL,
				Widget::TableBody($aTableBody)
			);

			$this->Form->appendChild($table);


			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');

			$options = array(
				array(NULL, false, __('With Selected...')),
				array('delete', false, __('Delete'))
			);

			$toggable_fields = $section->fetchToggleableFields();

			if (is_array($toggable_fields) && !empty($toggable_fields)) {
				$index = 2;

				foreach ($toggable_fields as $field) {
					$options[$index] = array('label' => __('Set %s', array($field->label)), 'options' => array());

					foreach ($field->getToggleStates() as $value => $state) {
						$options[$index]['options'][] = array('toggle-' . $field->id . '-' . $value, false, $state);
					}

					$index++;
				}
			}

			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));

			$this->Form->appendChild($tableActions);
			*/
		}

		function __actionIndex(){
			$checked = array_keys($_POST['items']);
			$callback = Administration::instance()->getPageCallback();

			if(is_array($checked) && !empty($checked)){
				switch($_POST['with-selected']) {

	            	case 'delete':

						###
						# Delegate: Delete
						# Description: Prior to deletion of entries. Array of Entries is provided.
						#              The array can be manipulated
						ExtensionManager::instance()->notifyMembers('Delete', '/publish/', array('entry_id' => &$checked));

						foreach($checked as $entry_id){
							Entry::delete($entry_id);
						}

					 	redirect($_SERVER['REQUEST_URI']);

					default:

						## TODO: Add delegate

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

									$entry->data()->$field_handle = $field->processFormData($value, $entry);

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

		/* TODO: Remove once create/edit form becomes one and the same */
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
				var_dump(Symphony::Database()); die();
			}
		}

		public function __viewNew(){
			$this->__form();
		}

		public function __form(Entry $existing=NULL){

			$callback = Administration::instance()->getPageCallback();

			$section = Section::load(sprintf('%s/%s.xml', SECTIONS, $callback['context']['section_handle']));

			// Check that a layout and fields exist
			if(isset($section->fields)) {
				return $this->pageAlert(
					__(
						'It looks like you\'re trying to create an entry. Perhaps you want fields first? <a href="%s">Click here to create some.</a>',
						array(
							ADMIN_URL . '/blueprints/sections/edit/' . $section->handle . '/'
						)
					),
					Alert::ERROR
				);
			}

			$this->Form->setAttribute('enctype', 'multipart/form-data');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), $section->name)));

			$subheading = __('Untitled');
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
			$this->Form->appendChild(Widget::Input('MAX_FILE_SIZE', Symphony::Configuration()->get('max_upload_size', 'admin'), 'hidden'));

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
			if(!is_null($callback['flag'])) {
				switch($callback['flag']){
					case 'saved':
						$this->pageAlert(
							__(
								'Entry updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Entries</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/publish/'.$callback['context']['section_handle'].'/new/'.$prepopulate_filter,
									ADMIN_URL . '/publish/'.$callback['context']['section_handle'].'/'
								)
							),
						Alert::SUCCESS);

						break;

					case 'created':
						$this->pageAlert(
							__(
								'Entry created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Entries</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/publish/'.$callback['context']['section_handle'].'/new/'.$prepopulate_filter,
									ADMIN_URL . '/publish/'.$callback['context']['section_handle'].'/'
								)
							),
						Alert::SUCCESS);
						break;
				}
			}

			// Load all the fields for this section
			$section_fields = array();
			foreach($section->fields as $index => $field) {
				$section_fields[$field->{'element-name'}] = $field;
			}

			/*
			Array
			(
			    [0] => stdClass Object
			        (
			            [size] => large
			            [fieldsets] => Array
			                (
			                    [0] => stdClass Object
			                        (
			                            [name] => SimpleXMLElement Object
			                                (
			                                    [0] => Untitled
			                                )

			                            [fields] => Array
			                                (
			                                    [0] => title
			                                    [1] => body
			                                )

			                        )

			                )

			        )
			*/

			$layout = new Layout;

			if(is_array($section->layout) && !empty($section->layout)) foreach ($section->layout as $data) {
				$column = $layout->createColumn($data->size);

				foreach ($data->fieldsets as $data) {
					$fieldset = $this->createElement('fieldset');

					$header = $this->createElement('h3', $data->name);
					$fieldset->appendChild($header);

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
							 $this->entry->data()->{$field->{'element-name'}},
							(isset($this->errors->{$field->{'element-name'}})
								? $this->errors->{$field->{'element-name'}}
								: NULL),
							$this->entry
						);

						$fieldset->appendChild($div);
					}

					$column->appendChild($fieldset);
				}

				$layout->appendTo($this->Form);
			}

			else {
				$this->pageAlert(
					__(
						'You haven\'t set any section layout rules. PERHAPS IF NO LAYOUT IS SET A DEFAULT TWO COLUMN SHOULD BE USED? <a href="%s">Click here to define a layout.</a>',
						array(
							ADMIN_URL . '/blueprints/sections/layout/' . $section->handle . '/'
						)
					),
					Alert::ERROR
				);
			}
/*
			//Check if there is a field to prepopulate
			if (isset($_REQUEST['prepopulate'])) {
				$field_handle = array_shift(array_keys($_REQUEST['prepopulate']));
				$value = stripslashes(rawurldecode(array_shift($_REQUEST['prepopulate'])));

				$this->Form->prependChild(Widget::Input(
					"prepopulate[{$field_handle}]",
					rawurlencode($value),
					'hidden'
				));

				 	Need FieldManager first.
				// 	The actual pre-populating should only happen if there is not existing fields post data
				if(!isset($_POST['fields']) && $field = FieldManager::instance()->fetch($field_id)) {
					$entry->setData(
						$field->id,
						$field->processRawFieldData($value, $error, true)
					);
				}

			}

			// If there is post data floating around, due to errors, create an entry object
			if (isset($_POST['fields'])) {
				$entry = EntryManager::instance()->create();
				$entry->set('section_id', $section_id);
				$entry->setDataFromPost($_POST['fields'], $error, true);
			}

			// Brand new entry, so need to create some various objects
			else {
				$entry = EntryManager::instance()->create();
				$entry->set('section_id', $section_id);
			}
*/
			$div = $this->createElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[save]', (!is_null($existing) ? __('Save Changes') : __('Create Entry')), 'submit', array('accesskey' => 's')));

			if(!is_null($existing)){
				$button = $this->createElement('button', __('Delete'));
				$button->setAttributeArray(array(
					'name' => 'action[delete]',
					'class' => 'confirm delete',
					'title' => __('Delete this entry'),
					'type' => 'submit'
				));
				$div->appendChild($button);
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
				ExtensionManager::instance()->notifyMembers(
					'EntryPreCreate', '/publish/new/',
					array('entry' => &$entry)
				);

				$this->errors->flush();
				Entry::save($entry, $this->errors);

				if($this->errors->length() <= 0){

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
					ExtensionManager::instance()->notifyMembers(
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
				$this->pageAlert(__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), Alert::ERROR);
				return;

/*
				$section = Section::loadFromHandle($this->_context['section_handle']);

				$entry =& EntryManager::instance()->create();
				$entry->set('section', $section->handle);
				$entry->set('user_id', Administration::instance()->User->id);
				$entry->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));
				$entry->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));

				$post = General::getPostData();
				$fields = $post['fields'];

				if(Entry::STATUS_ERROR == $entry->checkPostData($fields, $this->_errors)):
					$this->pageAlert(__('Some errors were encountered while attempting to save.'), Alert::ERROR);

				elseif(Entry::STATUS_ERROR != $entry->setDataFromPost($fields, $error)):
					$this->pageAlert($error['message'], Alert::ERROR);

				else:

					###
					# Delegate: EntryPreCreate
					# Description: Just prior to creation of an Entry. Entry object and fields are provided
					ExtensionManager::instance()->notifyMembers('EntryPreCreate', '/publish/new/', array('section' => $section, 'fields' => &$fields, 'entry' => &$entry));

					if(!$entry->commit()){
						define_safe('__SYM_DB_INSERT_FAILED__', true);
						$this->pageAlert(NULL, Alert::ERROR);

					}

					else{

						###
						# Delegate: EntryPostCreate
						# Description: Creation of an Entry. New Entry object is provided.
						ExtensionManager::instance()->notifyMembers('EntryPostCreate', '/publish/new/', array('section' => $section, 'entry' => $entry, 'fields' => $fields));

						$prepopulate_field_id = $prepopulate_value = NULL;
						if(isset($_POST['prepopulate'])){
							$prepopulate_field_id = array_shift(array_keys($_POST['prepopulate']));
							$prepopulate_value = stripslashes(rawurldecode(array_shift($_POST['prepopulate'])));
						}

			  		   	redirect(sprintf(
							'%s/symphony/publish/%s/edit/%d/created%s/',
							URL,
							$this->_context['section_handle'],
							$entry->get('id'),
							(!is_null($prepopulate_field_id) ? ":{$prepopulate_field_id}:{$prepopulate_value}" : NULL)
						));

					}

				endif;
				*/
			}

		}
/*
		function __viewEdit() {

			$section = Section::loadFromHandle($this->_context['section_handle']);
			$entry_id = intval($this->_context['entry_id']);

			EntryManager::instance()->setFetchSorting('id', 'DESC');

			if(!$existingEntry = EntryManager::instance()->fetch($entry_id)) Administration::instance()->customError(E_USER_ERROR, __('Unknown Entry'), __('The entry you are looking for could not be found.'), false, true);
			$existingEntry = $existingEntry[0];

			// If there is post data floating around, due to errors, create an entry object
			if (isset($_POST['fields'])) {
				$fields = $_POST['fields'];

				$entry =& EntryManager::instance()->create();
				$entry->set('section_id', $existingEntry->get('section_id'));
				$entry->set('id', $entry_id);

				$entry->setDataFromPost($fields, $error, true);
			}

			// Editing an entry, so need to create some various objects
			else {
				$entry = $existingEntry;

				if (!$section) {
					$section = Section::loadFromHandle($entry->get('section'));
				}
			}

			###
			# Delegate: EntryPreRender
			# Description: Just prior to rendering of an Entry edit form. Entry object can be modified.
			ExtensionManager::instance()->notifyMembers('EntryPreRender', '/publish/edit/', array('section' => $section, 'entry' => &$entry, 'fields' => $fields));

			if(isset($this->_context['flag'])){

				$link = 'publish/'.$this->_context['section_handle'].'/new/';

				list($flag, $field_id, $value) = preg_split('/:/i', $this->_context['flag'], 3);

				if(is_numeric($field_id) && $value){
					$link .= "?prepopulate[$field_id]=$value";

					$this->Form->prependChild(Widget::Input(
						"prepopulate[{$field_id}]",
						rawurlencode($value),
						'hidden'
					));
				}

				switch($flag){

					case 'saved':

						$this->pageAlert(
							__(
								'Entry updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Entries</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . "/$link",
									ADMIN_URL . '/publish/'.$this->_context['section_handle'].'/'
								)
							),
							Alert::SUCCESS);

						break;

					case 'created':
						$this->pageAlert(
							__(
								'Entry created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Entries</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . "/$link",
									ADMIN_URL . '/publish/'.$this->_context['section_handle'].'/'
								)
							),
							Alert::SUCCESS);
						break;

				}
			}

			### Determine the page title
			$field_id = Symphony::Database()->fetchVar('id', 0, "SELECT `id` FROM `tbl_fields` WHERE `parent_section` = '".$section->get('id')."' ORDER BY `sortorder` LIMIT 1");
			$field = FieldManager::instance()->fetch($field_id);

			$title = trim(strip_tags($field->prepareTableValue($existingEntry->getData($field->id), NULL, $entry_id)));

			if (trim($title) == '') {
				$title = 'Untitled';
			}

			// Check if there is a field to prepopulate
			if (isset($_REQUEST['prepopulate'])) {
				$field_id = array_shift(array_keys($_REQUEST['prepopulate']));
				$value = stripslashes(rawurldecode(array_shift($_REQUEST['prepopulate'])));

				$this->Form->prependChild(Widget::Input(
					"prepopulate[{$field_id}]",
					rawurlencode($value),
					'hidden'
				));
			}

			$this->Form->setAttribute('enctype', 'multipart/form-data');
			$this->setTitle(__('%1$s &ndash; %2$s &ndash; %3$s', array(__('Symphony'), $section->get('name'), $title)));
			$this->appendSubheading($title);
			$this->Form->appendChild(Widget::Input('MAX_FILE_SIZE', Symphony::Configuration()->get('max_upload_size', 'admin'), 'hidden'));

			###

			$primary = new XMLElement('fieldset');
			$primary->setAttribute('class', 'primary');

			$sidebar_fields = $section->fetchFields(NULL, 'sidebar');
			$main_fields = $section->fetchFields(NULL, 'main');

			if((!is_array($main_fields) || empty($main_fields)) && (!is_array($sidebar_fields) || empty($sidebar_fields))){
				$primary->appendChild(new XMLElement('p', __('It looks like your trying to create an entry. Perhaps you want fields first? <a href="%s">Click here to create some.</a>', array(ADMIN_URL . '/blueprints/sections/edit/'. $section->get('id') . '/'))));
			}

			else{

				if(is_array($main_fields) && !empty($main_fields)){
					foreach($main_fields as $field){
						$primary->appendChild($this->__wrapFieldWithDiv($field, $entry));
					}

					$this->Form->appendChild($primary);
				}

				if(is_array($sidebar_fields) && !empty($sidebar_fields)){
					$sidebar = new XMLElement('fieldset');
					$sidebar->setAttribute('class', 'secondary');

					foreach($sidebar_fields as $field){
						$sidebar->appendChild($this->__wrapFieldWithDiv($field, $entry));
					}

					$this->Form->appendChild($sidebar);
				}

			}

			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[save]', __('Save Changes'), 'submit', array('accesskey' => 's')));

			$button = new XMLElement('button', __('Delete'));
			$button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'confirm delete', 'title' => __('Delete this entry'), 'type' => 'submit'));
			$div->appendChild($button);

			$this->Form->appendChild($div);

		}
*/
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
				ExtensionManager::instance()->notifyMembers(
					'EntryPreEdit', '/publish/edit/',
					array('entry' => &$entry)
				);

				$this->errors->flush();
				Entry::save($entry, $this->errors);

				if($this->errors->length() <= 0){

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
					ExtensionManager::instance()->notifyMembers(
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
				$this->pageAlert(__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), Alert::ERROR);
				return;



/*
				if(Entry::STATUS_ERROR == $entry->checkPostData($fields, $this->_errors)):
					$this->pageAlert(__('Some errors were encountered while attempting to save.'), Alert::ERROR);

				elseif(Entry::STATUS_ERROR != $entry->setDataFromPost($fields, $error)):
					$this->pageAlert($error['message'], Alert::ERROR);

				else:


					###
					# Delegate: EntryPreEdit
					# Description: Just prior to editing of an Entry.
					ExtensionManager::instance()->notifyMembers('EntryPreEdit', '/publish/edit/', array('section' => $section, 'entry' => &$entry, 'fields' => $fields));

					if(!$entry->commit()){
						define_safe('__SYM_DB_INSERT_FAILED__', true);
						$this->pageAlert(NULL, Alert::ERROR);

					}

					else{

						###
						# Delegate: EntryPostEdit
						# Description: Editing an entry. Entry object is provided.
						ExtensionManager::instance()->notifyMembers('EntryPostEdit', '/publish/edit/', array('section' => $section, 'entry' => $entry, 'fields' => $fields));


						$prepopulate_field_id = $prepopulate_value = NULL;
						if(isset($_POST['prepopulate'])){
							$prepopulate_field_id = array_shift(array_keys($_POST['prepopulate']));
							$prepopulate_value = stripslashes(rawurldecode(array_shift($_POST['prepopulate'])));
						}

			  		    //redirect(ADMIN_URL . '/publish/' . $this->_context['section_handle'] . '/edit/' . $entry_id . '/saved/');

			  		   	redirect(sprintf(
							'%s/symphony/publish/%s/edit/%d/saved%s/',
							URL,
							$this->_context['section_handle'],
							$entry->get('id'),
							(!is_null($prepopulate_field_id) ? ":{$prepopulate_field_id}:{$prepopulate_value}" : NULL)
						));

					}

				endif;
				*/
			}

			elseif(@array_key_exists('delete', $_POST['action']) && is_numeric($entry_id)){
				$callback = Administration::instance()->getPageCallback();

				###
				# Delegate: Delete
				# Description: Prior to deleting an entry. Entry ID is provided, as an
				# array to remain compatible with other Delete delegate call
				ExtensionManager::instance()->notifyMembers('Delete', '/publish/', array('entry_id' => $entry_id));

				Entry::delete($entry_id);

				redirect(ADMIN_URL . '/publish/'.$callback['context']['section_handle'].'/');
			}

		}

	}


