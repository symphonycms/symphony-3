<?php

	require_once(LIB . '/class.administrationpage.php');
	require_once(LIB . '/class.event.php');

	Class contentBlueprintsEvents extends AdministrationPage{

		protected $event;
		protected $errors;
		protected $fields;
		protected $editing;
		protected $failed;
		protected $handle;
		protected $status;
		protected $type;
		
		protected static $_loaded_views;
		
		public function __construct(){
			parent::__construct();

			$this->errors = new MessageStack;
			$this->fields = array();
			$this->editing = $this->failed = false;
			$this->event = $this->handle = $this->status = $this->type = NULL;
		}

		public function __viewIndex() {
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Events'))));

			$this->appendSubheading(__('Events'), Widget::Anchor(
				__('Create New'), Administration::instance()->getCurrentPageURL() . 'new/', array(
					'title' => __('Create a new event'),
					'class' => 'create button'
				)
			));

			$eTableHead = array(
				array(__('Name'), 'col'),
				array(__('Source'), 'col'),
				array(__('Type'), 'col'),
				array(__('Attached On'), 'col')
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

			else {
				//	Load Views so we can determine what Datasources are attached
				if(!self::$_loaded_views) {
					foreach (new ViewIterator as $view) {
						self::$_loaded_views[$view->guid] = array(
							'title' => $view->title,
							'handle' => $view->handle,
							'events' => $view->{'events'}
						);
					}
				}

				foreach($iterator as $pathname){

					$event = Event::load($pathname);
					$view_mode = ($event->allowEditorToParse() == true ? 'edit' : 'info');
					$handle = Event::getHandleFromFilename($pathname);


					$col_name = Widget::TableData(
						Widget::Anchor($event->about()->name, URL . "/symphony/blueprints/events/{$view_mode}/{$handle}/", array(
							'title' => $event->parameters()->pathname
						))
					);

					$col_name->appendChild(Widget::Input("items[{$handle}]", null, 'checkbox'));

					// Source
					if(is_null($event->parameters()->section)){
						$col_source = Widget::TableData(__('None'), array('class' => 'inactive'));
					}
					else{
						$section = Section::loadFromHandle($event->parameters()->section);

						$col_source = Widget::TableData(Widget::Anchor(
							$section->name,
							URL . '/symphony/blueprints/sections/edit/' . $section->handle . '/',
							array('title' => $section->handle)
						));
					}

					// Attached On
					$fragment_views = $this->createDocumentFragment();

					foreach(self::$_loaded_views as $view) {
						if(is_array($view['events']) && in_array($handle, $view['events'])) {
							if($fragment_views->hasChildNodes()) $fragment_views->appendChild(new DOMText(', '));

							$fragment_views->appendChild(
								Widget::Anchor($view['title'], URL . "/symphony/blueprints/views/edit/{$view['handle']}/")
							);
						}
					}

					if(!$fragment_views->hasChildNodes()) {
						$col_views = Widget::TableData(__('None'), array('class' => 'inactive'));
					}
					else{
						$col_views = Widget::TableData($fragment_views);
					}

					// Type
					if(is_null($event->getExtension())){
						$col_type = Widget::TableData(__('Unknown'), array('class' => 'inactive'));
					}
					else{
						$extension = ExtensionManager::instance()->about($event->getExtension());
						$col_type = Widget::TableData($extension['name']);
					}
					
					$eTableBody[] = Widget::TableRow(
						array($col_name, $col_source, $col_type, $col_views)
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

		public function build($context) {
			if (isset($context[0]) and ($context[0] == 'edit' or $context[0] == 'new')) {
				$context[0] = 'form';
			}

			parent::build($context);
		}
		
		protected function __prepareForm() {

			$this->editing = isset($this->_context[1]);

			if (!$this->editing) {
				$this->type = $_REQUEST['type'];

				if (is_null($this->type)){
					$this->type = Symphony::Configuration()->core()->{'default-event-type'};
				}

				$this->event = ExtensionManager::instance()->create($this->type)->prepareEvent(
					null, (isset($_POST['fields']) ? $_POST['fields'] : NULL)
				);
			}

			else {
				$this->handle = $this->_context[1];

				// Status message:
				$callback = Administration::instance()->getPageCallback();
				if(isset($callback['flag']) && !is_null($callback['flag'])){
					$this->status = $callback['flag'];
				}

				$this->event = Event::loadFromHandle($this->handle);
				$this->type = $this->event->getExtension();

				$this->event = ExtensionManager::instance()->create($this->type)->prepareEvent(
					$this->event, (isset($_POST['fields']) ? $_POST['fields'] : NULL)
				);

				if (!$this->event->allowEditorToParse()) {
					redirect(URL . '/symphony/blueprints/events/info/' . $this->handle . '/');
				}

				$this->type = $this->event->getExtension();
			}
		}

		protected function __actionForm() {
			// Delete event:
			if ($this->editing && array_key_exists('delete', $_POST['action'])) {
				$this->__actionDelete(array($this->handle), ADMIN_URL . '/blueprints/events/');
			}
			
			// Saving
			try{
				$pathname = $this->event->save($this->errors);
				$handle = preg_replace('/.php$/i', NULL, basename($pathname));
				redirect(
					URL . "/symphony/blueprints/events/edit/{$handle}/:"
					. ($this->editing == true ? 'saved' : 'created') . "/"
				);
			}

			catch (EventException $e) {
				$this->alerts()->append(
					$e->getMessage(),
					AlertStack::ERROR, $e
				);
			}

			catch (Exception $e) {
				$this->alerts()->append(
					__('An unknown error has occurred. <a class="more">Show trace information.</a>'),
					AlertStack::ERROR, $e
				);
			}
		}

		protected function __viewForm() {
			// Show page alert:
			if ($this->failed) {
				$this->alerts()->append(
					__('An error occurred while processing this form. <a href="#error">See below for details.</a>'),
					AlertStack::ERROR
				);
			}

			else if (!is_null($this->status)) {
				switch ($this->status) {
					case 'saved':
						$this->alerts()->append(
							__(
								'Event updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									URL . '/symphony/blueprints/events/new/',
									URL . '/symphony/blueprints/events/'
								)
							),
							AlertStack::SUCCESS
						);
						break;

					case 'created':
						$this->alerts()->append(
							__(
								'Event created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									URL . '/symphony/blueprints/events/new/',
									URL . '/symphony/blueprints/events/'
								)
							),
							AlertStack::SUCCESS
						);
						break;
				}
			}

			// Track type with a hidden field:
			if($this->editing || ($this->editing && isset($_POST['type']))){
				$input = Widget::Input('type', $this->type, 'hidden');
				$this->Form->appendChild($input);
			}

			 // Let user choose type:
			else{
				$div = $this->createElement('div');
				$div->setAttribute('id', 'master-switch');

				$label = Widget::Label(__('Select Type'));

				$options = array();
				foreach(ExtensionManager::instance()->listByType('Event') as $e){
					if($e['status'] != Extension::ENABLED) continue;
					$options[] = array($e['handle'], ($this->type == $e['handle']), $e['name']);
				}

				$select = Widget::Select('type', $options);

				$div->appendChild($label);
				$div->appendChild($select);
				$this->Form->appendChild($div);
			}

			if(is_null($this->event->about()->name) || strlen(trim($this->event->about()->name)) == 0){
				$this->setTitle(__('%1$s &ndash; %2$s &ndash; %3$s', array(
					__('Symphony'), __('Events'), __('Untitled')
				)));
				$this->appendSubheading(General::sanitize(__('New Event')));
			}

			else{
				$this->setTitle(__('%1$s &ndash; %2$s &ndash; %3$s', array(
					__('Symphony'), __('Events'), $this->event->about()->name
				)));
				$this->appendSubheading(General::sanitize($this->event->about()->name));
			}

			ExtensionManager::instance()->create($this->type)->viewEvent($this->event, $this->Form, $this->errors);

			$actions = $this->createElement('div');
			$actions->setAttribute('class', 'actions');

			$save = Widget::Submit(
				'action[save]', ($this->editing) ? __('Save Changes') : __('Create Event'),
				array(
					'accesskey' => 's'
				)
			);
			$actions->appendChild($save);

			if ($this->editing == true) {
				$actions->appendChild(
					Widget::Submit(
						'action[delete]', __('Delete'),
						array(
							'class' => 'confirm delete',
							'title' => __('Delete this event')
						)
					)
				);
			}

			$this->Form->appendChild($actions);
		}

		public function __viewInfo(){
			$event = Event::loadFromHandle($this->_context[1]);
			$about = $event->about();

			$this->setTitle(__('%1$s &ndash; %2$s &ndash; %3$s', array(__('Symphony'), __('Data Source'), $about->name)));
			$this->appendSubheading($about->name);
			$this->Form->setAttribute('id', 'controller');

			$link = $about->author->name;

			if(isset($about->author->website)){
				$link = Widget::Anchor($about->author->name, General::validateURL($about->author->website));
			}

			elseif(isset($about->author->email)){
				$link = Widget::Anchor($about->author->name, 'mailto:' . $about->author->email);
			}

			foreach($about as $key => $value) {

				$fieldset = NULL;

				switch($key) {
					case 'user':
						$fieldset = $this->createElement('fieldset');
						$fieldset->appendChild($this->createElement('legend', 'User'));
						$fieldset->appendChild($this->createElement('p', $link));
						break;

					case 'version':
						$fieldset = $this->createElement('fieldset');
						$fieldset->appendChild($this->createElement('legend', 'Version'));
						$fieldset->appendChild($this->createElement('p', $value . ', released on ' . DateTimeObj::get(__SYM_DATE_FORMAT__, strtotime($about->{'release-date'}))));
						break;

					case 'description':
						$fieldset = $this->createElement('fieldset');
						$fieldset->appendChild($this->createElement('legend', 'Description'));
						$fieldset->appendChild((is_object($about->description) ? $about->description : $this->createElement('p', $about->description)));

					case 'example':
						if (is_callable(array($datasource, 'example'))) {
							$fieldset = $this->createElement('fieldset');
							$fieldset->appendChild($this->createElement('legend', 'Example XML'));

							$example = $datasource->example();

							if(is_object($example)) {
								 $fieldset->appendChild($example);
							} else {
								$p = $this->createElement('p');
								$p->appendChild($this->createElement('pre', '<code>' . str_replace('<', '&lt;', $example) . '</code>'));
								$fieldset->appendChild($p);
							}
						}
						break;
				}

				if ($fieldset) {
					$fieldset->setAttribute('class', 'settings');
					$this->Form->appendChild($fieldset);
				}

			}

			/*
			$dl->appendChild(new XMLElement('dt', __('URL Parameters')));
			if(!is_array($about['recognised-url-param']) || empty($about['recognised-url-param'])){
				$dl->appendChild(new XMLElement('dd', '<code>'.__('None').'</code>'));
			}
			else{
				$dd = new XMLElement('dd');
				$ul = new XMLElement('ul');

				foreach($about['recognised-url-param'] as $f) $ul->appendChild(new XMLElement('li', '<code>' . $f . '</code>'));

				$dd->appendChild($ul);
				$dl->appendChild($dd);
			}
			$fieldset->appendChild($dl);
			*/

		}
	}

