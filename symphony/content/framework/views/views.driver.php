<?php

	$views_xml = $Document->createElement('views','Testing the views driver');
	$data->appendChild($views_xml);

	/*require_once(LIB . '/class.messagestack.php');
	require_once(LIB . '/class.utility.php');
	require_once(LIB . '/class.event.php');

	class contentBlueprintsViews extends AdministrationView {

		public function __viewIndex() {

			$iterator = new ViewIterator;

			$views_xml = $this->createElement('views');

			foreach ($iterator as $v) {
				$class = array();

				$page_title = $v->title;

				$page_url = sprintf('%s/%s/', URL, $v->path);
				$page_edit_url = sprintf('%s/edit/%s/', Administration::instance()->getCurrentPageURL(), $v->path);

				$page_types = $v->types;

				// Initialize <view> element
				$view = $this->createElement('view');


				$view->appendChild($this->createElement('url',$page_url));

				if(is_array($v->{'url-parameters'}) && count($v->{'url-parameters'}) > 0){
					$params = $this->createElement('url-params');
					foreach($v->{'url-parameters'} as $p){
						$params->appendChild($this->createElement('item',$p));
					}
					$view->appendChild($params);
				}

				if(is_array($page_types) && count($page_types) > 0){
					$types = $this->createElement('types');
					foreach($page_types as $t){
						$types->appendChild($this->createElement('item',$t));
					}
					$view->appendChild($types);
				}

				$views_xml->appendChild($view);
			}

			return $views_xml;
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
						'accesskey' => 's'
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
						'accesskey' => 's'
					)
				)
			);

			if($this->_context[0] == 'edit'){
				$div->appendChild(
					Widget::Submit(
						'action[delete]', __('Delete'),
						array(
							'class' => 'confirm delete',
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
*/
//				$fields['types'] = preg_split('/\s*,\s*/', $fields['types'], -1, PREG_SPLIT_NO_EMPTY);
/*
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
	}*/