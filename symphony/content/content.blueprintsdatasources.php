<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	//require_once(TOOLKIT . '/class.datasourcemanager.php');
	//require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.messagestack.php');

	Class ContentBlueprintsDatasources extends AdministrationPage{

		protected $errors;
		protected $fields;
		protected $editing;
		protected $failed;
		protected $datasource;
		protected $handle;
		protected $status;
		protected $type;

		protected static $_loaded_views;

		public function __construct(){
			parent::__construct();

			$this->errors = new MessageStack;
			$this->fields = array();
			$this->editing = $this->failed = false;
			$this->datasource = $this->handle = $this->status = $this->type = NULL;
		}

		public function __viewIndex() {
			// This is the 'correct' way to append a string containing an entity
			$title = $this->createElement('title');
			$title->appendChild($this->createTextNode(__('Symphony') . ' '));
			$title->appendChild($this->createEntityReference('ndash'));
			$title->appendChild($this->createTextNode(' ' . __('Data Sources')));
			$this->insertNodeIntoHead($title);

			$this->appendSubheading(__('Data Sources') . $heading, Widget::Anchor(
				__('Create New'), Administration::instance()->getCurrentPageURL() . 'new/', array(
					'title' => __('Create a new data source'),
					'class' => 'create button'
				)
			));

			$datasources = new DatasourceIterator;

			$dsTableHead = array(
				array(__('Name'), 'col'),
				array(__('Source'), 'col'),
				array(__('Type'), 'col'),
				array(__('Views Using'), 'col'),
				array(__('Author'), 'col')
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
					$handle = preg_replace('/.php$/i', NULL, basename($ds->parameters()->pathname));

					// Name
					$col_name = Widget::TableData(
						Widget::Anchor($ds->about()->name,URL . "/symphony/blueprints/datasources/{$view_mode}/{$handle}/", array(
							'title' => $handle . '.php'
						))
					);
					$col_name->appendChild(Widget::Input("items[{$handle}]", NULL, 'checkbox'));

					// Source
					if(!method_exists($ds, 'prepareSourceColumnValue')) {
						$col_source = Widget::TableData(__('None'), array('class' => 'inactive'));
					}
					else {
						$col_source = $ds->prepareSourceColumnValue();
					}

					// Views that have this datasource Attached
					$fragment_views = $this->createDocumentFragment();

					foreach(self::$_loaded_views as $view) {
						if(is_array($view['data-sources']) && in_array(preg_replace('/.php$/i', NULL, $pathname), $view['data-sources'])) {
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
					if(is_null($ds->type())){
						$col_type = Widget::TableData(__('Unknown'), array('class' => 'inactive'));
					}
					else{
						$extension = ExtensionManager::instance()->about($ds->type());
						$col_type = Widget::TableData($extension['name']);
					}

					// Author
					if (isset($ds->about()->author->website)) {
						$col_author = Widget::TableData(Widget::Anchor(
							$ds->about()->author->name,
							General::validateURL($ds->about()->author->website)
						));
					}
					else if (isset($ds->about()->author->email)) {
						$col_author = Widget::TableData(Widget::Anchor(
							$ds->about()->author->name,
							'mailto:' . $ds->about()->author->email
						));
					}

					else {
						$col_author = Widget::TableData($ds->about()->author->name);
					}

					$dsTableBody[] = Widget::TableRow(array(
						$col_name, $col_source, $col_type, $col_views, $col_author
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

				$this->datasource = ExtensionManager::instance()->create($this->type)->prepare(
					(isset($_POST['fields']) ? $_POST['fields'] : NULL)
				);
			}

			else {
				$this->handle = $this->_context[1];

				// Status message:
				$callback = Administration::instance()->getPageCallback();
				if(isset($callback['flag']) && !is_null($callback['flag'])){
					$this->status = $callback['flag'];
				}

				$this->datasource = Datasource::loadFromHandle($this->handle);
				$this->type = $this->datasource->type();

				$this->datasource = ExtensionManager::instance()->create($this->type)->prepare(
					(isset($_POST['fields']) ? $_POST['fields'] : NULL), $this->datasource
				);

				//$this->datasource = Datasource::loadFromHandle($this->handle, NULL, false); //DatasourceManager::instance()->create($this->handle, NULL, false);

				if (!$this->datasource->allowEditorToParse()) {
					redirect(URL . '/symphony/blueprints/datasources/info/' . $this->handle . '/');
				}

				$this->type = $this->datasource->type();
			}

			###
			# Delegate: DataSourceFormPrepare
			# Description: Prepare any data before the form view and action are fired.
			/*ExtensionManager::instance()->notifyMembers(
				'DataSourceFormPrepare', '/backend/',
				array(
					'type'		=> &$this->type,
					'handle'		=> &$this->handle,
					'datasource'	=> $this->datasource,
					'editing'		=> $this->editing,
					'failed'		=> &$this->failed,
					'fields'		=> &$this->fields,
					'errors'		=> &$this->errors
				)
			);*/
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
				redirect(URL . "/symphony/blueprints/datasources/edit/{$handle}/:".($this->editing == true ? 'saved' : 'created')."/");
			}

			catch (DatasourceException $e) {
				$this->alerts()->append(
					$e->getMessage(),
					AlertStack::ERROR, $e
				);
			}

			catch (Exception $e) {
				$this->alerts()->append(
					__(
						'An unknown error has occurred. %s',
						array($e->getMessage())
					),
					AlertStack::ERROR, $e
				);
			}

			/*$type_file = NULL;
			$type_data = array();



			$this->fields = $_POST['fields'];

			// About info:
			if (!isset($this->fields['about']['name']) || empty($this->fields['about']['name'])) {
				$this->errors->append('about::name', __('This is a required field'));
				$this->failed = true;
			}

			###
			# Delegate: DataSourceFormAction
			# Description: Prepare any data before the form view and action are fired.
			ExtensionManager::instance()->notifyMembers(
				'DataSourceFormAction', '/backend/',
				array(
					'type'		=> &$this->type,
					'handle'		=> &$this->handle,
					'datasource'	=> $this->datasource,
					'editing'		=> $this->editing,
					'failed'		=> &$this->failed,
					'fields'		=> &$this->fields,
					'errors'		=> &$this->errors,
					'type_file'	=> &$type_file,
					'type_data'	=> &$type_data
				)
			);

			// Save type:
			if ($this->errors->length() <= 0) {
				$user = Administration::instance()->User;

				if (!file_exists($type_file)) {
					throw new Exception(sprintf("Unable to find Data Source type '%s'.", $type_file));
				}

				$default_data = array(
					// Class name:
					str_replace(' ', '_', ucwords(
						str_replace('-', ' ', Lang::createHandle($this->fields['about']['name']))
					)),

					// About info:
					var_export($this->fields['about']['name'], true),
					var_export($user->getFullName(), true),
					var_export(URL, true),
					var_export($user->email, true),
					var_export('1.0', true),
					var_export(DateTimeObj::getGMT('c'), true),
				);

				foreach ($type_data as $value) {
					$default_data[] = var_export($value, true);
				}

				header('content-type: text/plain');
				echo vsprintf(file_get_contents($type_file), $default_data);

				exit;
			}*/
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
									URL . '/symphony/blueprints/datasources/new/',
									URL . '/symphony/blueprints/datasources/'
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
									URL . '/symphony/blueprints/datasources/new/',
									URL . '/symphony/blueprints/datasources/'
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
				foreach(ExtensionManager::instance()->listByType('Data Source') as $e){
					if($e['status'] != Extension::ENABLED) continue;
					$options[] = array($e['handle'], ($this->type == $e['handle']), $e['name']);
				}

				$select = Widget::Select('type', $options);

				$div->appendChild($label);
				$div->appendChild($select);
				$this->Form->appendChild($div);
			}

			if(is_null($this->datasource->about()->name) || strlen(trim($this->datasource->about()->name)) == 0){
				$this->setTitle(__('%1$s &ndash; %2$s &ndash; %3$s', array(
					__('Symphony'), __('Data Sources'), __('Untitled')
				)));
				$this->appendSubheading(General::sanitize(__('New Data Source')));
			}

			else{
				$this->setTitle(__('%1$s &ndash; %2$s &ndash; %3$s', array(
					__('Symphony'), __('Data Sources'), $this->datasource->about()->name
				)));
				$this->appendSubheading(General::sanitize($this->datasource->about()->name));
			}

			ExtensionManager::instance()->create($this->type)->view($this->datasource, $this->Form, $this->errors);

			/*
			###
			# Delegate: DataSourceFormView
			# Description: Prepare any data before the form view and action are fired.
			ExtensionManager::instance()->notifyMembers(
				'DataSourceFormView', '/backend/',
				array(
					'type'		=> &$this->type,
					'handle'		=> &$this->handle,
					'datasource'	=> $this->datasource,
					'editing'		=> $this->editing,
					'failed'		=> &$this->failed,
					'fields'		=> &$this->fields,
					'errors'		=> &$this->errors,
					'wrapper'		=> $this->Form
				)
			);
			*/

			$actions = $this->createElement('div');
			$actions->setAttribute('class', 'actions');

			$actions->appendChild(
				Widget::Submit(
					'action[save]', __('Create Data Source'),
					array(
						'accesskey' => 's'
					)
				)
			);

			if ($this->editing == true) {
				$save->setAttribute('value', __('Save Changes'));
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

		function __viewInfo(){
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
			*/

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
				$label->appendChild(Widget::Input('fields[filter][user]['.$name.']', General::sanitize($value)));
				$li->appendChild($label);

			 	$wrapper->appendChild($li);
			}

			$li = $this->createElement('li');
			$li->setAttribute('class', 'unique template');
			$li->appendChild($this->createElement('h4', $h4_label));
			$label = Widget::Label(__('Value'));
			$label->appendChild(Widget::Input('fields[filter][user]['.$name.']'));
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
						__(
							'An unknown error has occurred. %s',
							array($e->getMessage())
						),
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
				}*/
			}

			if($success) redirect($redirect);
		}

		public function __actionIndex() {
			$checked = array_keys($_POST['items']);

			if(is_array($checked) && !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						$this->__actionDelete($checked, URL . '/symphony/blueprints/datasources/');
						break;
				}
			}
		}


	}

