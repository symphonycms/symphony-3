<?php

	/**
	* DatasourcesDriver class...
	*/

	Class DatasourcesDriver {

		public $url;
		public $view;

		public function __construct() {
			$this->view = Controller::instance()->View;
			$this->url = Controller::instance()->url;

			$this->setTitle();
		}

		public function setTitle() {
			$this->view->title = __('Data Sources');
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

		}
	}

	/*
	require_once(LIB . '/class.administrationpage.php');
	//require_once(LIB . '/class.datasourcemanager.php');
	//require_once(LIB . '/class.sectionmanager.php');
	require_once(LIB . '/class.messagestack.php');

	Class ContentBlueprintsDatasources extends AdministrationPage{

		protected $errors;
		protected $fields;
		protected $editing;
		protected $failed;
		protected $datasource;
		protected $handle;
		protected $status;
		protected $type;
		protected $types;

		protected static $_loaded_views;

		public function __construct(){
			parent::__construct();

			$this->errors = new MessageStack;
			$this->fields = array();
			$this->editing = $this->failed = false;
			$this->datasource = $this->handle = $this->status = $this->type = NULL;
			$this->types = array();

			foreach (new ExtensionIterator(ExtensionIterator::FLAG_TYPE, array('Data Source')) as $extension) {
				$path = Extension::getPathFromClass(get_class($extension));
				$handle = Extension::getHandleFromPath($path);

				if (Extension::status($handle) != Extension::STATUS_ENABLED) continue;
				if (!method_exists($extension, 'getDataSourceTypes')) continue;

				foreach ($extension->getDataSourceTypes() as $type) {
					$this->types[$type->class] = $type;
				}
			}

			if(empty($this->types)){
				$this->alerts()->append(
					__(
						'There are no Data Source types currently available. You will not be able to create or edit Data Sources.'
					),
					AlertStack::ERROR
				);
			}
		}

		public function __viewIndex() {

			// This is the 'correct' way to append a string containing an entity
			$title = $this->createElement('title');
			$title->appendChild($this->createTextNode(__('Symphony') . ' '));
			$title->appendChild($this->createEntityReference('ndash'));
			$title->appendChild($this->createTextNode(' ' . __('Data Sources')));
			$this->insertNodeIntoHead($title);

			$this->appendSubheading(__('Data Sources'), Widget::Anchor(
				__('Create New'), Administration::instance()->getCurrentPageURL() . '/new/', array(
					'title' => __('Create a new data source'),
					'class' => 'create button'
				)
			));

			$datasources = new DatasourceIterator;

			$dsTableHead = array(
				array(__('Name'), 'col'),
				array(__('Source'), 'col'),
				array(__('Type'), 'col'),
				array(__('Used By'), 'col')
			);

			$dsTableBody = array();
			$colspan = count($dsTableHead);

			if($datasources->length() <= 0) {
				$dsTableBody = array(Widget::TableRow(
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
							'data-sources' => $view->{'data-sources'}
						);
					}
				}

				foreach ($datasources as $pathname) {
					$ds = DataSource::load($pathname);

					$view_mode = ($ds->allowEditorToParse() == true ? 'edit' : 'info');
					$handle = DataSource::getHandleFromFilename($pathname);

					// Name
					$col_name = Widget::TableData(
						Widget::Anchor($ds->about()->name, ADMIN_URL . "/blueprints/datasources/{$view_mode}/{$handle}/", array(
							'title' => $ds->parameters()->pathname
						))
					);
					$col_name->appendChild(Widget::Input("items[{$handle}]", NULL, 'checkbox'));

					// Source
					try{
						$col_source = $ds->prepareSourceColumnValue();
					}
					catch(Exception $e){
						$col_source = Widget::TableData(__('None'), array('class' => 'inactive'));
					}

					// Used By
					$fragment_views = $this->createDocumentFragment();

					foreach(self::$_loaded_views as $view) {
						if(is_array($view['data-sources']) && in_array($handle, $view['data-sources'])) {
							if($fragment_views->hasChildNodes()) $fragment_views->appendChild(new DOMText(', '));

							$fragment_views->appendChild(
								Widget::Anchor($view['title'], ADMIN_URL . "/blueprints/views/edit/{$view['handle']}/")
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
					if (is_null($ds->getType())) {
						$col_type = Widget::TableData(__('Unknown'), array('class' => 'inactive'));
					}

					else{
						$col_type = Widget::TableData($this->types[$ds->getType()]->name);
					}

					$dsTableBody[] = Widget::TableRow(array(
						$col_name, $col_source, $col_type, $col_views
					));
				}
			}

			$table = Widget::Table(Widget::TableHead($dsTableHead), NULL,Widget::TableBody($dsTableBody), array(
					'id' => 'datasources-list'
				)
			);

			$this->Form->appendChild($table);

			$tableActions = $this->createElement('div');
			$tableActions->setAttribute('class', 'actions');

			$options = array(
				array(NULL, false, __('With Selected...')),
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
					$this->type = Symphony::Configuration()->core()->{'default-datasource-type'};
				}

				// Should the default type or the selected type no longer be valid, choose the first available one instead
				if(!in_array($this->type, array_keys($this->types))){
					$this->type = current(array_keys($this->types));
				}

				foreach ($this->types as $type) {
					if ($type->class != $this->type) continue;

					$this->datasource = new $type->class;
					$this->datasource->prepare(
						isset($_POST['fields'])
							? $_POST['fields']
							: NULL
					);

					break;
				}
			}

			else {
				$this->handle = $this->_context[1];

				// Status message:
				$callback = Administration::instance()->getPageCallback();
				if(isset($callback['flag']) && !is_null($callback['flag'])){
					$this->status = $callback['flag'];
				}

				$this->datasource = Datasource::loadFromHandle($this->handle);
				$this->type = $this->datasource->getType();

				$this->datasource->prepare(
					isset($_POST['fields'])
						? $_POST['fields']
						: NULL
				);

				if (!$this->datasource->allowEditorToParse()) {
					redirect(ADMIN_URL . '/blueprints/datasources/info/' . $this->handle . '/');
				}
			}
		}

		protected function __actionForm() {

			// Delete datasource:
			if ($this->editing && array_key_exists('delete', $_POST['action'])) {
				$this->__actionDelete(array($this->handle), ADMIN_URL . '/blueprints/datasources/');
			}

			// Saving
			try{
				$pathname = $this->datasource->save($this->errors);
				$handle = preg_replace('/.php$/i', NULL, basename($pathname));
				redirect(ADMIN_URL . "/blueprints/datasources/edit/{$handle}/:".($this->editing == true ? 'saved' : 'created')."/");
			}

			catch (DatasourceException $e) {
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
								'Data source updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/blueprints/datasources/new/',
									ADMIN_URL . '/blueprints/datasources/'
								)
							),
							AlertStack::SUCCESS
						);
						break;

					case 'created':
						$this->alerts()->append(
							__(
								'Data source created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/blueprints/datasources/new/',
									ADMIN_URL . '/blueprints/datasources/'
								)
							),
							AlertStack::SUCCESS
						);
						break;
				}
			}

			if(!($this->datasource instanceof Datasource) || is_null($this->datasource->about()->name) || strlen(trim($this->datasource->about()->name)) == 0){
				$this->setTitle(__('%1$s &ndash; %2$s &ndash; %3$s', array(
					__('Symphony'), __('Data Sources'), __('Untitled')
				)));
				$this->appendSubheading(General::sanitize(__('Data Source')));
			}

			else{
				$this->setTitle(__('%1$s &ndash; %2$s &ndash; %3$s', array(
					__('Symphony'), __('Data Sources'), $this->datasource->about()->name
				)));
				$this->appendSubheading(General::sanitize($this->datasource->about()->name));
			}

			// Track type with a hidden field:
			if($this->editing || ($this->editing && isset($_POST['type']))){
				$input = Widget::Input('type', $this->type, 'hidden');
				$this->Form->appendChild($input);
			}

			 // Let user choose type:
			else{
				$header = $this->xpath('//h2')->item(0);
				$options = array();

				foreach ($this->types as $type) {
					$options[] = array($type->class, ($this->type == $type->class), $type->name);
				}

				usort($options, 'General::optionsSort');
				$select = Widget::Select('type', $options);

				$header->prependChild($select);
				$header->prependChild(new DOMText(__('New')));
			}

			if($this->datasource instanceof Datasource){
				$this->datasource->view($this->Form, $this->errors);
			}

			$actions = $this->createElement('div');
			$actions->setAttribute('class', 'actions');

			$save = Widget::Submit(
				'action[save]', ($this->editing) ? __('Save Changes') : __('Create Data Source'),
				array(
					'accesskey' => 's'
				)
			);
			if(!($this->datasource instanceof Datasource)){
				$save->setAttribute('disabled', 'disabled');
			}
			$actions->appendChild($save);

			if ($this->editing == true) {
				$actions->appendChild(
					Widget::Submit(
						'action[delete]', __('Delete'),
						array(
							'class' => 'confirm delete',
							'title' => __('Delete this data source')
						)
					)
				);
			}


			$this->Form->appendChild($actions);
		}

		public function __viewInfo(){
			$datasource = DataSource::loadFromHandle($this->_context[1]);
			$about = $datasource->about();

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
			*

		}


		function __injectIncludedElements(&$shell, $elements){
			if(!is_array($elements) || empty($elements)) return;

			$shell = str_replace('<!-- INCLUDED ELEMENTS -->', "public \$dsParamINCLUDEDELEMENTS = array(" . PHP_EOL . "\t\t\t'" . implode("'," . PHP_EOL . "\t\t\t'", $elements) . "'" . PHP_EOL . '		);' . PHP_EOL, $shell);

		}

		function __injectFilters(&$shell, $filters){
			if(!is_array($filters) || empty($filters)) return;

			$string = 'public $dsParamFILTERS = array(' . PHP_EOL;

			foreach($filters as $key => $val){
				if(strlen(trim($val)) == 0) continue;
				$string .= "\t\t\t'$key' => '" . addslashes($val) . "'," . PHP_EOL;
			}

			$string .= '		);' . PHP_EOL;

			$shell = str_replace('<!-- FILTERS -->', trim($string), $shell);

		}

		function __injectAboutInformation(&$shell, $details){
			if(!is_array($details) || empty($details)) return;

			foreach($details as $key => $val) $shell = str_replace('<!-- ' . strtoupper($key) . ' -->', addslashes($val), $shell);
		}

		function __injectVarList(&$shell, $vars){
			if(!is_array($vars) || empty($vars)) return;

			$var_list = NULL;
			foreach($vars as $key => $val){

				if(!is_array($val) && strlen(trim($val)) == 0) continue;

				$var_list .= sprintf('		public $dsParam%s = ', strtoupper($key));

				if(is_array($val) && !empty($val)){
					$var_list .= 'array(' . PHP_EOL;
					foreach($val as $item){
						$var_list .= sprintf("\t\t\t'%s',", addslashes($item)) . PHP_EOL;
					}
					$var_list .= '		);' . PHP_EOL;
				}
				else{
					$var_list .= sprintf("'%s';", addslashes($val)) . PHP_EOL;
				}
			}

			$shell = str_replace('<!-- VAR LIST -->', trim($var_list), $shell);

		}

		function __appendUserFilter(&$wrapper, $h4_label, $name, $value=NULL, $templateOnly=true){

			if(!$templateOnly){

				$li = $this->createElement('li');
				$li->setAttribute('class', 'unique');
				$li->appendChild($this->createElement('h4', $h4_label));
				$label = Widget::Label(__('Value'));
				$label->appendChild(Widget::Input('fields[filters][user]['.$name.']', General::sanitize($value)));
				$li->appendChild($label);

			 	$wrapper->appendChild($li);
			}

			$li = $this->createElement('li');
			$li->setAttribute('class', 'unique template');
			$li->appendChild($this->createElement('h4', $h4_label));
			$label = Widget::Label(__('Value'));
			$label->appendChild(Widget::Input('fields[filters][user]['.$name.']'));
			$li->appendChild($label);

		 	$wrapper->appendChild($li);

		}

		protected function __actionDelete(array $datasources, $redirect=NULL) {
			$success = true;

			foreach ($datasources as $handle) {
				try{
					Datasource::delete($handle);
				}
				catch(DatasourceException $e){
					$success = false;
					$this->alerts()->append(
						$e->getMessage(),
						AlertStack::ERROR, $e
					);
				}
				catch(Exception $e){
					$success = false;
					$this->alerts()->append(
						__('An unknown error has occurred. <a class="more">Show trace information.</a>'),
						AlertStack::ERROR, $e
					);
				}

				// TODO: Delete reference from View XML

				/*$sql = "SELECT * FROM `tbl_pages` WHERE `data_sources` REGEXP '[[:<:]]".$ds."[[:>:]]' ";
				$pages = Symphony::Database()->fetch($sql);

				if(is_array($pages) && !empty($pages)){
					foreach($pages as $page){

						$page['data_sources'] = preg_replace('/\b'.$ds.'\b/i', '', $page['data_sources']);

						Symphony::Database()->update($page, 'tbl_pages', "`id` = '".$page['id']."'");
					}
				}*
			}

			if($success) redirect($redirect);
		}

		public function __actionIndex() {
			$checked = is_array($_POST['items']) ? array_keys($_POST['items']) : null;

			if(is_array($checked) && !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						$this->__actionDelete($checked, ADMIN_URL . '/blueprints/datasources/');
						break;
				}
			}
		}


	}*/