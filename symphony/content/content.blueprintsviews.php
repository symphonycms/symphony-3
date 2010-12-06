<?php

	require_once(LIB . '/class.administrationpage.php');
	require_once(LIB . '/class.messagestack.php');
	require_once(LIB . '/class.xslproc.php');
	require_once(LIB . '/class.utility.php');
	require_once(LIB . '/class.event.php');

	class contentBlueprintsViews extends AdministrationPage {

		public function __viewIndex() {
			// This is the 'correct' way to append a string containing an entity
			$title = $this->createElement('title');
			$title->appendChild($this->createTextNode(__('Symphony') . ' '));
			$title->appendChild($this->createEntityReference('ndash'));
			$title->appendChild($this->createTextNode(' ' . __('Views')));
			$this->insertNodeIntoHead($title);

			$this->appendSubheading(__('Views'), Widget::Anchor(
				__('Create New'), Administration::instance()->getCurrentPageURL() . '/new/', array(
					'title' => __('Create a new view'),
					'class' => 'button constructive'
				)
			));

			$iterator = new ViewIterator;

			$aTableHead = array(
				array(__('Title'), 'col'),
				array(Widget::Acronym('URL', array('title' => __('Universal Resource Locator'))), 'col'),
				array(Widget::Acronym('URL', array('title' => __('Universal Resource Locator')), __(' Parameters')), 'col'),
				array(__('Type'), 'col')
			);

			$aTableBody = array();
			$colspan = count($aTableHead);

			if($iterator->length() <= 0) {
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

			else{
				foreach ($iterator as $view) {
					$class = array();

					$page_title = $view->title;

					$page_url = sprintf('%s/%s/', URL, $view->path);
					$page_edit_url = sprintf('%s/edit/%s/', Administration::instance()->getCurrentPageURL(), $view->path);

					$page_types = $view->types;

					$link = Widget::Anchor($page_title, $page_edit_url, array('title' => $view->handle));

					$col_title = Widget::TableData($link);
					$col_title->appendChild(Widget::Input("items[{$view->path}]", null, 'checkbox'));

					$col_url = Widget::TableData(Widget::Anchor(substr($page_url, strlen(URL)), $page_url));

					if(is_array($view->{'url-parameters'}) && count($view->{'url-parameters'}) > 0){
						$col_params = Widget::TableData(implode('/', $view->{'url-parameters'}));

					} else {
						$col_params = Widget::TableData(__('None'), array('class' => 'inactive'));
					}

					if(!empty($page_types)) {
						$col_types = Widget::TableData(implode(', ', $page_types));

					} else {
						$col_types = Widget::TableData(__('None'), array('class' => 'inactive'));
					}

					$col_toggle = Widget::TableData('', array('class' => 'toggle'));

					$columns = array($col_title, $col_url, $col_params, $col_types);

					$row = Widget::TableRow($columns);
					$next = $view->parent();
					$class = '';

					while (!is_null($next)) {
						$class .= ' view-' . $next->guid;

						$next = $next->parent();
					}

					//if (is_null($view->parent())) {
						$row->setAttribute('id', 'view-' . $view->guid);
					//}

					if (trim($class)) {
						$row->setAttribute('class', trim($class));
					}

					$aTableBody[] = $row;
				}
			}

			$table = Widget::Table(
				Widget::TableHead($aTableHead), null,
				Widget::TableBody($aTableBody), array(
					'id' => 'views-list'
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
			$tableActions->appendChild(Widget::Submit('action[apply]', __('Apply')));

			$this->Form->appendChild($tableActions);
		}

		public function __viewTemplate() {

			$callback = Administration::instance()->getPageCallback();

			$context = $this->_context;
			array_shift($context);
			$view_pathname = implode('/', $context);

			$view = View::loadFromPath($view_pathname);

			$this->Form->setAttribute('action', ADMIN_URL . '/blueprints/views/template/' . $view->path . '/');

			$filename = $view->handle . '.xsl';

			$formHasErrors = ($this->errors instanceof MessageStack && $this->errors->length() > 0);

			// Status message:
			if(!is_null($callback['flag']) && $callback['flag'] == 'saved') {
				$this->alerts()->append(
					__(
						'View updated at %s. <a href="%s">View all</a>',
						array(
							DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
							ADMIN_URL . '/blueprints/views/'
						)
					),
					AlertStack::SUCCESS
				);
			}

			$this->setTitle(__(
				($filename ? '%1$s &ndash; %2$s &ndash; %3$s' : '%1$s &ndash; %2$s'),
				array(
					__('Symphony'),
					__('Views'),
					$filename
				)
			));
			$this->appendSubheading(__($filename ? $filename : __('Untitled')));

			$this->appendViewOptions(array(
				__('Configuration')			=>	ADMIN_URL . '/blueprints/views/edit/' . $view_pathname . '/',
				__('Template')				=>	Administration::instance()->getCurrentPageURL()
			));
			
			$layout = new Layout();
			$left = $layout->createColumn(Layout::LARGE);
			$right = $layout->createColumn(Layout::SMALL);

			if(!empty($_POST)){
				$view->template = stripslashes($_POST['fields']['template']);
			}

			$fieldset = Widget::Fieldset(__('Template'));

			$label = Widget::Label(__('XSLT'));
			$label->appendChild(
				Widget::Textarea('fields[template]', $view->template, array(
					'rows' => 30,
					'cols' => 80,
					'class'	=> 'code'
				)
			));

			if(isset($this->errors->template)) {
				$label = Widget::wrapFormElementWithError($label, $this->errors->template);
			}

			$fieldset->appendChild($label);
			$left->appendChild($fieldset);

			$utilities = new UtilityIterator;

			if($utilities->length() > 0){

				$fieldset = Widget::Fieldset(__('Utilities'));

				$ul = $this->createElement('ul');
				$ul->setAttribute('id', 'utilities');

				foreach ($utilities as $u) {
					$li = $this->createElement('li');
					$li->appendChild(Widget::Anchor(
						$u->name, ADMIN_URL . '/blueprints/utilities/edit/' . str_replace('.xsl', NULL, $u->name) . '/'
					));
					$ul->appendChild($li);
				}

				$fieldset->appendChild($ul);
				$right->appendChild($fieldset);
			}
			
			$layout->appendTo($this->Form);

			$div = $this->createElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Submit(
					'action[save]', __('Save Changes'),
					array(
						'accesskey' => 's',
						'class'		=> 'constructive'
					)
				)
			);
			
			$this->Form->appendChild($div);
		}

		public function __actionTemplate() {

			$context = $this->_context;
			array_shift($context);

			$view = self::__loadExistingView(implode('/', $context));

			$view->template = stripslashes($_POST['fields']['template']);

			$this->errors = new MessageStack;

			try{
				View::save($view, $this->errors);
				redirect(ADMIN_URL . '/blueprints/views/template/' . $view->path . '/:saved/');
			}
			catch(ViewException $e){
				switch($e->getCode()){
					case View::ERROR_MISSING_OR_INVALID_FIELDS:
						$this->alerts()->append(__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), AlertStack::ERROR, $this->errors);
						break;

					case View::ERROR_FAILED_TO_WRITE:
						$this->alerts()->append($e->getMessage(), AlertStack::ERROR, $e);
						break;
				}
			}
			catch(Exception $e){
				// Errors!!
				// Not sure what happened!!
				$this->alert()->append(
					__('An unknown error has occurred. <a class="more">Show trace information.</a>'),
					AlertStack::ERROR, $e
				);
			}

		}

		public function __viewNew() {
			$this->__form();
		}

		public function __viewEdit() {
			$this->__form();
		}

		private static function __loadExistingView($path){
			try{
				$existing = View::loadFromPath($path);
				return $existing;
			}
			catch(ViewException $e){

				switch($e->getCode()){
					case View::ERROR_VIEW_NOT_FOUND:
						throw new SymphonyErrorPage(
							__('The view you requested to edit does not exist.'),
							__('View not found'), NULL,
							array('header' => 'HTTP/1.0 404 Not Found')
						);
						break;

					default:
					case View::ERROR_FAILED_TO_LOAD:
						throw new SymphonyErrorPage(
							__('The view you requested could not be loaded. Please check it is readable and the XML is valid.'),
							__('Failed to load view')
						);
						break;
				}
			}
			catch(Exception $e){
				throw new SymphonyErrorPage(
					sprintf(__("An unknown error has occurred. %s"), $e->getMessage()),
					__('Unknown Error'), NULL,
					array('header' => 'HTTP/1.0 500 Internal Server Error')
				);
			}
		}

		public function __form() {
			$layout = new Layout();
			$left = $layout->createColumn(Layout::LARGE);
			$center = $layout->createColumn(Layout::LARGE);
			$right = $layout->createColumn(Layout::LARGE);
			$existing = null;
			$fields = array();

			// Verify view exists:
			if($this->_context[0] == 'edit') {

				if(!isset($this->_context[1]) || strlen(trim($this->_context[1])) == 0){
					redirect(ADMIN_URL . '/blueprints/views/');
				}

				$context = $this->_context;
				array_shift($context);
				$view_pathname = implode('/', $context);

				$existing = self::__loadExistingView($view_pathname);

			}

			// Status message:
			$callback = Administration::instance()->getPageCallback();
			if(isset($callback['flag']) && !is_null($callback['flag'])){

				switch($callback['flag']){

					case 'saved':

						$this->alerts()->append(
							__(
								'View updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/blueprints/views/new/',
									ADMIN_URL . '/blueprints/views/',
								)
							),
							AlertStack::SUCCESS);

						break;

					case 'created':

						$this->alerts()->append(
							__(
								'View created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/blueprints/views/new/',
									ADMIN_URL . '/blueprints/views/',
								)
							),
							AlertStack::SUCCESS);

						break;

				}
			}

			// Find values:
			if(isset($_POST['fields'])) {
				$fields = $_POST['fields'];
			}

			elseif($this->_context[0] == 'edit') {
				$fields = (array)$existing->about();
				
				// Flatten the types array:
				$fields['types'] = (
					(isset($fields['types']) and is_array($fields['types']))
						? implode(', ', $fields['types'])
						: null
				);
				
				// Flatten the url-parameters array:
				$fields['url-parameters'] = (
					(isset($fields['url-parameters']) and is_array($fields['url-parameters']))
						? implode('/', $fields['url-parameters'])
						: null
				);
				
				$fields['parent'] = (
					$existing->parent() instanceof View
						? $existing->parent()->path
						: NULL
				);
				$fields['handle'] = $existing->handle;
			}

			$title = null;
			
			if (isset($fields['title'])) {
				$title = $fields['title'];
			}
			
			if(strlen(trim($title)) == 0){
				$title = ($existing instanceof View ? $existing->title : 'New View');
			}

			$this->setTitle(__(
				($title ? '%1$s &ndash; %2$s &ndash; %3$s' : '%1$s &ndash; %2$s'),
				array(
					__('Symphony'),
					__('Views'),
					$title
				)
			));

			if ($existing instanceof View) {
				$template_name = $fields['handle'];
				$this->appendSubheading(
					__($title ? $title : __('New View'))
				);
				$viewoptions = array(
					__('Configuration')		=>	Administration::instance()->getCurrentPageURL(),
					__('Template')			=>	sprintf('%s/blueprints/views/template/%s/', ADMIN_URL, $view_pathname)
				);
				
				$this->appendViewOptions($viewoptions);
			}
			
			else {
				$this->appendSubheading(($title ? $title : __('Untitled')));
			}

		// Fieldset -----------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Essentials'));

		// Title --------------------------------------------------------------

			$label = Widget::Label(__('Title'));
			$label->appendChild(Widget::Input(
				'fields[title]',
				isset($fields['title'])
					? $fields['title']
					: null
			));

			if(isset($this->errors->title)) {
				$label = Widget::wrapFormElementWithError($label, $this->errors->title);
			}

			$fieldset->appendChild($label);

		// Type ---------------------------------------------------------------

			$container = $this->createElement('div');

			$label = Widget::Label(__('View Type'));
			$label->appendChild(Widget::Input(
				'fields[types]',
				isset($fields['types'])
					? $fields['types']
					: null
			));

			if(isset($this->errors->types)) {
				$label = Widget::wrapFormElementWithError($label, $this->errors->types);
			}

			$tags = $this->createElement('ul');
			$tags->setAttribute('class', 'tags');

			foreach(self::__fetchAvailableViewTypes() as $t){
				$tags->appendChild($this->createElement('li', $t));
			}

			$container->appendChild($label);
			$container->appendChild($tags);
			$fieldset->appendChild($container);

			$left->appendChild($fieldset);

		// Fieldset -----------------------------------------------------------

			$fieldset = Widget::Fieldset(__('URL Settings'));

		// Parent -------------------------------------------------------------

			$label = Widget::Label(__('Parent'));

			$options = array(
				array(NULL, false, '/')
			);

			foreach(new ViewIterator as $v){
				// Make sure the current view cannot be set as either a child of itself, or a child of
				// another view that is already at child of the current view.
				if(isset($existing) && $existing instanceof View && ($v->isChildOf($existing) || $v->guid == $existing->guid)) continue;

				$options[] = array(
					$v->path, isset($fields['parent']) and $fields['parent'] == $v->path, "/{$v->path}"
				);
			}

			$label->appendChild(Widget::Select(
				'fields[parent]', $options
			));

			$fieldset->appendChild($label);

		// Handle -------------------------------------------------------------

			$label = Widget::Label(__('Handle'));
			$label->appendChild(Widget::Input(
				'fields[handle]',
				isset($fields['handle'])
					? $fields['handle']
					: null
			));

			if(isset($this->errors->handle)) {
				$label = Widget::wrapFormElementWithError($label, $this->errors->handle);
			}

			$fieldset->appendChild($label);

		// Parameters ---------------------------------------------------------

			$label = Widget::Label(__('Parameters'));
			$label->appendChild(Widget::Input(
				'fields[url-parameters]',
				isset($fields['url-parameters'])
					? $fields['url-parameters']
					: null
			));

			$fieldset->appendChild($label);
			$center->appendChild($fieldset);

		// Fieldset -----------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Resources'));

			$label = Widget::Label(__('Events'));

			$options = array();

			foreach (new EventIterator as $pathname){
				$event = Event::load($pathname);
				$handle = Event::getHandleFromFilename($pathname);
				$options[] = array(
					$handle, in_array($handle, (array)$fields['events']), $event->about()->name
				);
			}
			
			$label->appendChild(Widget::Select('fields[events][]', $options, array('multiple' => 'multiple')));
			$fieldset->appendChild($label);

		// Data Sources -------------------------------------------------------

			$label = Widget::Label(__('Data Sources'));

			$options = array();
			foreach (new DataSourceIterator as $pathname){
				$ds = DataSource::load($pathname);
				$handle = DataSource::getHandleFromFilename($pathname);

				$options[] = array(
					$handle, in_array($handle, (array)$fields['data-sources']), $ds->about()->name
				);
			}
			

			$label->appendChild(Widget::Select('fields[data-sources][]', $options, array('multiple' => 'multiple')));
			$fieldset->appendChild($label);
			$right->appendChild($fieldset);
			
			$layout->appendTo($this->Form);

		// Controls -----------------------------------------------------------

			$div = $this->createElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Submit(
					'action[save]', ($this->_context[0] == 'edit' ? __('Save Changes') : __('Create View')),
					array(
						'accesskey' => 's',
						'class'		=> 'constructive'
					)
				)
			);

			if($this->_context[0] == 'edit'){
				$div->appendChild(
					Widget::Submit(
						'action[delete]', __('Delete'),
						array(
							'class' => 'confirm delete destructive',
							'title' => __('Delete this view')
						)
					)
				);
			}

			$this->Form->appendChild($div);

		}
		
		public function __actionNew() {
			$this->__actionEdit();
		}

		public function __actionEdit() {

			if($this->_context[0] != 'new' && strlen(trim($this->_context[1])) == 0){
				redirect(ADMIN_URL . '/blueprints/views/');
			}

			$context = $this->_context;
			array_shift($context);

			$view_pathname = implode('/', $context);

			if(array_key_exists('delete', $_POST['action'])) {
				$this->__actionDelete(array($view_pathname), ADMIN_URL . '/blueprints/views/');
			}

			elseif(array_key_exists('save', $_POST['action'])) {

				$fields = $_POST['fields'];

				$fields['types'] = preg_split('/\s*,\s*/', $fields['types'], -1, PREG_SPLIT_NO_EMPTY);

				if(strlen(trim($fields['url-parameters'])) > 0){
					$fields['url-parameters'] = preg_split('/\/+/', trim($fields['url-parameters'], '/'), -1, PREG_SPLIT_NO_EMPTY);
				}

				if(strlen(trim($fields['handle'])) == 0){
					$fields['handle'] = Lang::createHandle($fields['title']);
				}

				$path = trim($fields['parent'] . '/' . $fields['handle'], '/');

				if($this->_context[0] == 'edit'){
					$view = self::__loadExistingView($view_pathname);

					$view->types = $fields['types'];
					$view->title = $fields['title'];
					$view->{'data-sources'} = $fields['data-sources'];
					$view->events = (isset($fields['events']) ? $fields['events'] : array());
					$view->{'url-parameters'} = $fields['url-parameters'];

					// Path has changed - Need to move the existing one, then save it
					if($view->path != $path){

						$this->errors = new MessageStack;

						try{
							// Before moving or renaming, simulate saving to check for potential errors
							View::save($view, $this->errors, true);
							View::move($view, $path);
						}
						catch(Exception $e){
							// Saving failed, catch it further down
						}
					}

				}
				else{
					$view = View::loadFromFieldsArray($fields);
					$view->template = file_get_contents(TEMPLATES . '/template.view.txt');
					$view->handle = $fields['handle'];
					$view->path = $path;
				}

				$this->errors = new MessageStack;

				try{
					View::save($view, $this->errors);
					redirect(ADMIN_URL . '/blueprints/views/edit/' . $view->path . '/:saved/');
				}
				catch(ViewException $e){
					switch($e->getCode()){
						case View::ERROR_MISSING_OR_INVALID_FIELDS:
							$this->alerts()->append(__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), AlertStack::ERROR, $this->errors);
							break;

						case View::ERROR_FAILED_TO_WRITE:
							$this->alerts()->append($e->getMessage(), AlertStack::ERROR, $e);
							break;
					}
				}
				catch(Exception $e){
					// Errors!!
					// Not sure what happened!!
					$this->alerts()->append(
						__('An unknown error has occurred. <a class="more">Show trace information.</a>'),
						AlertStack::ERROR, $e
					);
				}
			}
		}

		private static function __fetchAvailableViewTypes(){

			// TODO: Delegate here so extensions can add custom view types?

			$types = array('index', 'XML', 'admin', '404', '403');

			foreach(View::fetchUsedTypes() as $t){
				$types[] = $t;
			}

			return General::array_remove_duplicates($types);

		}

		protected function __actionDelete(array $views, $redirect) {

			rsort($views);

			$success = true;

			foreach($views as $path){
				try{
					View::delete($path);
				}
				catch(Exception $e){
					$success = false;
				}
			}

			if($success == true){ 
				redirect($redirect);
			}
			
			$this->alerts()->append(
				__('An error occurred while attempting to delete selected views. <a class="more">Show trace information.</a>'),
				AlertStack::ERROR,
				$e
			);
		}

		public function __actionIndex() {
			$checked = is_array($_POST['items']) ? array_keys($_POST['items']) : null;

			if(is_array($checked) && !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						$this->__actionDelete($checked, ADMIN_URL . '/blueprints/views/');
						break;
				}
			}
		}
	}
