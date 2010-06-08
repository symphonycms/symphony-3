<?php

	require_once(LIB . '/class.administrationpage.php');

	Class contentExtensionAACRoles extends AdministrationPage{
		
		private $driver;
		private $role;
		
		public function __construct(){
			parent::__construct();
			$this->driver = Extension::load('aac');
			$this->errors = new MessageStack;
		}
		
		public function __viewIndex(){
			
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Roles'));

			$this->appendSubheading(__('Roles'), Widget::Anchor(
				__('Add a Role'), Administration::instance()->getCurrentPageURL() . '/new/', array(
					'title' => __('Add a new Role'),
					'class' => 'create button'
				)
			));

		    $roles = new RoleIterator;

			$aTableHead = array(
				array(__('Name'), 'col'),
				array(__('Description'), 'col'),
				array(__('Users'), 'col')
			);

			$aTableBody = array();
			$colspan = count($aTableHead);

			if($roles->length() == 0){
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
				$role_move_options = array();
				
				foreach($roles as $r){
					
					## Move options
					$role_move_options[] = array('move::' . $r->id, false, $r->name);
					
					## Setup each cell
					$td1 = Widget::TableData(
						Widget::Anchor(
							$r->name, sprintf('%s/edit/%d/', Administration::instance()->getCurrentPageURL(), $r->id)
						)
					);
					
					
					$td2 = Widget::TableData(
						(is_null($r->description) ? 'None' : (string)$r->description),
						(is_null($r->description) ? array('class' => 'inactive') : array())
					);
				
					$td3 = Widget::TableData(
						//Widget::Anchor(
							(string)$r->users()->length()//,
						//	sprintf('%s/system/users/?filter=role_id:%d', ADMIN_URL, $r->id)
						//)
					);
				
					$td3->appendChild(Widget::Input("items[{$r->id}]", NULL, 'checkbox'));

					## Add a row to the body array, assigning each cell to the row
					$aTableBody[] = Widget::TableRow(array($td1, $td2, $td3));
				}
			}

			$table = Widget::Table(Widget::TableHead($aTableHead), NULL, Widget::TableBody($aTableBody));

			$this->Form->appendChild($table);
			
			if($roles->length() > 0){
				
				$tableActions = $this->createElement('div');
				$tableActions->setAttribute('class', 'actions');

				$options = array(
					array(NULL, false, 'With Selected...'),
					array('delete', false, 'Delete'),
					array(
						'label' => 'Move Users',
						'options' => $role_move_options
					)
				);

				$tableActions->appendChild(Widget::Select('with-selected', $options));
				$tableActions->appendChild(Widget::Input('action[apply]', 'Apply', 'submit'));

				$this->Form->appendChild($tableActions);
			}
			
		}

		public function __actionIndex(){
			$action = $_POST['with-selected'];
			
			if(preg_match('/^move::(\d+)$/i', $action, $matches)){
				foreach(array_keys($_POST['items']) as $role_id){
					Role::moveUsers($role_id, (int)$matches[1]);
				}
			}
			
			elseif($action == 'delete'){

				$checked = array_keys($_POST['items']);
				try{
					foreach($checked as $role_id){
						Role::delete($role_id);
					}

					redirect(ADMIN_URL . '/extension/aac/roles/');
				
				}
				catch(RoleException $e){
					$this->alerts()->append(
						$e->getMessage(), 
						AlertStack::ERROR, $this->errors
					);
				}

				catch(Exception $e){
					$this->alerts()->append(__(
						'An unknown error has occurred. <a class="more">Show trace information.</a>'), 
						AlertStack::ERROR, $e
					);
				}
			}
		}

		## Both the Edit and New pages need the same form
		public function __viewNew(){
			if(!($this->role instanceof Role)){
				$this->role = new Role;
			}
			$this->__form();
		}

		public function __viewEdit(){
			
			if(!($this->role instanceof Role) && !($this->role = Role::load((int)$this->_context[1]))){
				throw new SymphonyErrorPage('The role requested does not exist.', 'Role not found');
			}
			
			$this->__form();
		}
		
		private function __form(){

			$this->insertNodeIntoHead($this->createStylesheetElement(URL . '/extensions/aac/assets/roles.css'));
			$this->insertNodeIntoHead($this->createScriptElement(URL . '/extensions/aac/assets/roles.js'));

			if(!in_array($this->_context[0], array('new', 'edit'))) throw new AdministrationPageNotFoundException;
			
			$callback = Administration::instance()->getPageCallback();

			if(isset($callback['flag'])){
				switch($callback['flag']){

					case 'saved':

						$this->alerts()->append(
							__(
								'Role updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Roles</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/extension/aac/roles/new/',
									ADMIN_URL . '/extension/aac/roles/'
								)
							),
							AlertStack::SUCCESS);

						break;

					case 'created':

						$this->alerts()->append(
							__(
								'Role created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Roles</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/extension/aac/roles/new/',
									ADMIN_URL . '/extension/aac/roles/'
								)
							),
							AlertStack::SUCCESS);

						break;

				}
			}

			/**********

				INSERT logic for determining the current role and
				whether the user has permission to edit it

			**********/

			$this->setTitle(__(
				($this->_context[0] == 'new' ? '%1$s &ndash; %2$s &ndash; Untitled' : '%1$s &ndash; %2$s &ndash; %3$s'), 
				array(
					__('Symphony'), 
					__('Roles'), 
					$this->role->name
				)
			));
				
			$this->appendSubheading(
				$this->_context[0] == 'new' ? __('Untitled') : $this->role->name
			);

		// SETUP PAGE
			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$middle = $layout->createColumn(Layout::LARGE);
			$right = $layout->createColumn(Layout::LARGE);

			/** ESSENTIALS **/
			$fieldset = Widget::Fieldset(__('Essentials'));

			$label = Widget::Label(__('Name'));
			$label->appendChild(Widget::Input('fields[name]', $this->role->name));
			$fieldset->appendChild((isset($this->errors->name) ? Widget::wrapFormElementWithError($label, $this->errors->name) : $label));

			$label = Widget::Label(__('Description'));
			$label->appendChild(Widget::Textarea('fields[description]', $this->role->description, array('rows' => 7, 'cols' => 50)));
			$fieldset->appendChild((isset($this->errors->description) ? Widget::wrapFormElementWithError($label, $this->errors->description) : $label));

			$left->appendChild($fieldset);

			/// PUBLISHING PERMISSIONS
			$fieldset = Widget::Fieldset(__('Publishing Permissions'));

			$sections = new SectionIterator;

			if($sections->length() <= 0){
				$p = $this->createElement('p', 'No sections exist. ');
				$p->appendChild(Widget::Anchor(
					__('Create one'),
					ADMIN_URL . '/sections/new/'
				));
				$fieldset->appendChild($p);
			}

			else{
				$thead = array(
					array(__('Section'), 'col'),
					array(__('Create'), 'col', array('class' => 'checkbox')),
					array(__('Edit'), 'col', array('class' => 'checkbox'))
				);
				$tbody = array();
/*
				// TODO: Global permissions set all permissions
				
				$td1 = Widget::TableData(__('Global Permissions'));
				
				$td2 = Widget::TableData(
					Widget::Input('global-add', '1', 'checkbox'),
					array('class' => 'checkbox')
				);

				$td3 = Widget::TableData(NULL, array('class' => 'edit'));
				$td3->appendChild($this->createElement('p', NULL, array('class' => 'global-slider')));
				$td3->appendChild($this->createElement('span', 'n/a'));

				$tbody[] = Widget::TableRow(array($td1, $td2, $td3), array('class' => 'global'));
*/
				foreach($sections as $section){

					$td1 = Widget::TableData(
						$section->name
					);
					
					// TODO: Remove this and implement sliders
					$td2 = Widget::TableData();
					
					$td2->appendChild(Widget::Input(
						"fields[permissions][publish::{$section->handle}][create]", '0', 'checkbox',
						array('checked' => 'checked', 'style' => 'display: none;')
					));
					
					$td2->appendChild(Widget::Input(
						"fields[permissions][publish::{$section->handle}][create]", '1', 'checkbox',
						((int)$this->role->permissions()->{"publish::{$section->handle}.create"} > 0 ? array('checked' => 'checked') : NULL)
					));
					
					$edit_level = (int)$this->role->permissions()->{"publish::{$section->handle}.edit"};
					
					$td3 = Widget::TableData(
						Widget::Select("fields[permissions][publish::{$section->handle}][edit]", array(
							array('0', false, 'None'),
							array(1, $edit_level == 1, 'Own'),
							array(2, $edit_level == 2, 'All'),
						))
					);
					
/*
					$td2 = Widget::TableData(
					 	Widget::Input(
							"fields[permissions][{$section->handle}][create]",
							'1',
							'checkbox',
							($permissions['create'] == 1 ? array('checked' => 'checked') : array())
						),
						array('class' => 'checkbox')
					);

					$td3 = Widget::TableData(NULL, array('class' => 'edit'));
					$td3->appendChild($this->createElement('p', NULL, array('class' => 'slider')));
					$td3->appendChild(
						$this->createElement('span', '')
					);

					$td3->appendChild(
						Widget::Input('fields[permissions][' . $section->handle .'][edit]',
							(isset($permissions['edit']) ? $permissions['edit'] : '0'),
							'hidden'
						)
					);
*/
					$tbody[] = Widget::TableRow(array($td1, $td2, $td3));
				}

				$table = Widget::Table(Widget::TableHead($thead), NULL,	Widget::TableBody($tbody), array(
					'id' => 'role-permissions'
					)
				);

				$fieldset->appendChild($table);
				$right->appendChild($fieldset);
				
			}


			/// BLUEPRINTS PERMISSIONS
			$fieldset = Widget::Fieldset(__('Blueprints Permissions'));

			$thead = array(
				array(__('Area'), 'col'),
				array(__('Create'), 'col', array('class' => 'checkbox')),
				array(__('Edit'), 'col', array('class' => 'checkbox'))
			);
			$tbody = array();
			
			$areas = array(
				'sections' => 'Sections',
				'datasources' => 'Data Sources',
				'events' => 'Events',
				'views' => 'Views',
				'utilities' => 'Utilities'
			);
	
			foreach($areas as $key => $name){
				$td1 = Widget::TableData($name);

				$td2 = Widget::TableData(Widget::Input(
					"fields[permissions][blueprints::{$key}][create]", '1', 'checkbox',
					((int)$this->role->permissions()->{"blueprints::{$key}.create"} > 0 ? array('checked' => 'checked') : NULL)
				));

				$td3 = Widget::TableData(Widget::Input(
					"fields[permissions][blueprints::{$key}][edit]", '1', 'checkbox',
					((int)$this->role->permissions()->{"blueprints::{$key}.edit"} > 0 ? array('checked' => 'checked') : NULL)
				));

				$tbody[] = Widget::TableRow(array($td1, $td2, $td3));
			}

			$table = Widget::Table(Widget::TableHead($thead), NULL,	Widget::TableBody($tbody), array(
				'id' => 'role-permissions'
				)
			);

			$fieldset->appendChild($table);
			$middle->appendChild($fieldset);

			


			/// SYSTEM PERMISSIONS
			$fieldset = Widget::Fieldset(__('System Permissions'));

			$thead = array(
				array(__('Description'), 'col'),
				array(__('Enabled'), 'col', array('class' => 'checkbox')),
			);
			$tbody = array();
			
			$items = array(
				'Create Users' => array('users', 'create', 1),
				'Edit Users' => array('users', 'edit', 2)
			);
			
			foreach($items as $name => $item){
				list($key, $type, $level) = $item;
				
				$td1 = Widget::TableData($name);

				$td2 = Widget::TableData(Widget::Input(
					"fields[permissions][system::{$key}][{$type}]", (string)$level, 'checkbox',
					((int)$this->role->permissions()->{"system::{$key}.{$type}"} > 0 ? array('checked' => 'checked') : NULL)
				));

				$tbody[] = Widget::TableRow(array($td1, $td2));
			
			}
			
			$table = Widget::Table(Widget::TableHead($thead), NULL,	Widget::TableBody($tbody), array(
				'id' => 'role-permissions'
				)
			);
			
			$fieldset->appendChild($table);
			$middle->appendChild($fieldset);
			
			/**********

				BUILD view list and set up permissions interface

			**********/

			$layout->appendTo($this->Form);

			/** FORM ACTIONS **/
			$div = $this->createElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Submit(
					'action[save]', ($this->_context[0] == 'edit' ? __('Save Changes') : __('Create Role')),
					array(
						'accesskey' => 's'
					)
				)
			);

			if($this->_context[0] == 'edit' && !$isOwner){
				$div->appendChild(
					Widget::Submit(
						'action[delete]', __('Delete'),
						array(
							'class' => 'confirm delete',
							'title' => __('Delete this role')
						)
					)
				);
			}

			$this->Form->appendChild($div);

		}

		
/*
		private function __form(){

			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$center = $layout->createColumn(Layout::LARGE);
			$right = $layout->createColumn(Layout::SMALL);

			require_once(LIB . '/class.field.php');

			## Handle unknow context
			if(!in_array($this->_context[0], array('new', 'edit'))) throw new AdministrationPageNotFoundException;
			
			$callback = Administration::instance()->getPageCallback();
			if(isset($callback['flag'])){
				switch($callback['flag']){

					case 'saved':

						$this->alerts()->append(
							__(
								'User updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/system/users/new/',
									ADMIN_URL . '/system/users/'
								)
							),
							AlertStack::SUCCESS);

						break;

					case 'created':

						$this->alerts()->append(
							__(
								'User created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									ADMIN_URL . '/system/users/new/',
									ADMIN_URL . '/system/users/'
								)
							),
							AlertStack::SUCCESS);

						break;

				}
			}
			
			$isOwner = false;

			if($this->_context[0] == 'edit' && $this->role->id == Administration::instance()->User->id) $isOwner = true;

			$this->setTitle(__(($this->_context[0] == 'new' ? '%1$s &ndash; %2$s &ndash; Untitled' : '%1$s &ndash; %2$s &ndash; %3$s'), array(__('Symphony'), __('Users'), $this->role->getFullName())));
			$this->appendSubheading(($this->_context[0] == 'new' ? __('New User') : $this->role->getFullName()));

			### Essentials ###
			$fieldset = Widget::Fieldset(__('Essentials'));

			$label = Widget::Label(__('First Name'));
			$label->appendChild(Widget::Input('fields[first_name]', $this->role->{'first_name'}));
			$fieldset->appendChild((isset($this->errors->{'first-name'}) ? Widget::wrapFormElementWithError($label, $this->errors->{'first-name'}) : $label));

			$label = Widget::Label(__('Last Name'));
			$label->appendChild(Widget::Input('fields[last_name]', $this->role->{'last_name'}));
			$fieldset->appendChild((isset($this->errors->{'last-name'}) ? Widget::wrapFormElementWithError($label, $this->errors->{'last-name'}) : $label));

			$label = Widget::Label(__('Email Address'));
			$label->appendChild(Widget::Input('fields[email]', $this->role->email));
			$fieldset->appendChild((isset($this->errors->email) ? Widget::wrapFormElementWithError($label, $this->errors->email) : $label));

			$left->appendChild($fieldset);
			###

			### Login Details ###
			$fieldset = Widget::Fieldset(__('Login Details'));

			$label = Widget::Label(__('Username'));
			$label->appendChild(Widget::Input('fields[username]', $this->role->username, NULL));
			$fieldset->appendChild((isset($this->errors->username) ? Widget::wrapFormElementWithError($label, $this->errors->username) : $label));

			if($this->_context[0] == 'edit') {
				$fieldset->setAttribute('id', 'change-password');
			}

			$label = Widget::Label(($this->_context[0] == 'edit' ? __('New Password') : __('Password')));
			$label->appendChild(Widget::Input('fields[password]', NULL, 'password'));
			$fieldset->appendChild((isset($this->errors->password) ? Widget::wrapFormElementWithError($label, $this->errors->password) : $label));

			$label = Widget::Label(($this->_context[0] == 'edit' ? __('Confirm New Password') : __('Confirm Password')));
			if(isset($this->errors->{'password-confirmation'})) $label->setAttributeArray(array('class' => 'contains-error', 'title' => $this->errors->{'password-confirmation'}));
			$label->appendChild(Widget::Input('fields[password-confirmation]', NULL, 'password'));
			$fieldset->appendChild($label);

			if($this->_context[0] == 'edit'){
				$fieldset->appendChild($this->createElement('p', __('Leave password fields blank to keep the current password'), array('class' => 'help')));
			}

			$label = Widget::Label();
			$input = Widget::Input('fields[auth_token_active]', 'yes', 'checkbox');
			if($this->role->auth_token_active == 'yes') $input->setAttribute('checked', 'checked');
			$temp = ADMIN_URL . '/login/' . $this->role->createAuthToken() . '/';

			$label->appendChild($input);
			$label->appendChild(new DOMText(__('Allow remote login via ')));
			$label->appendChild(
				Widget::Anchor($temp, $temp)
			);

			$fieldset->appendChild($label);

			$center->appendChild($fieldset);

			### Default Section ###

			$fieldset = Widget::Fieldset(__('Custom Preferences'));

			$label = Widget::Label(__('Default Section'));

		    //$sections = SectionManager::instance()->fetch(NULL, 'ASC', 'sortorder');

			$options = array();

			//if(is_array($sections) && !empty($sections))
			foreach(new SectionIterator as $s){
				$options[] = array($s->handle, $this->role->default_section == $s->handle, $s->name);
			}

			$label->appendChild(Widget::Select('fields[default_section]', $options));
			$fieldset->appendChild($label);

			### Custom Language Selection ###
			$languages = Lang::getAvailableLanguages(true);
			if(count($languages > 1)) {

				// Get language names
				asort($languages);

				$label = Widget::Label(__('Language'));

				$options = array(
					array(NULL, is_null($this->role->language), __('System Default'))
				);

				foreach($languages as $code => $name) {
					$options[] = array($code, $code == $this->role->language, $name);
				}
				$select = Widget::Select('fields[language]', $options);
				$label->appendChild($select);
				$fieldset->appendChild($label);

				$right->appendChild($fieldset);

				$layout->appendTo($this->Form);
			}
			###

			$div = $this->createElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Submit(
					'action[save]', ($this->_context[0] == 'edit' ? __('Save Changes') : __('Create User')),
					array(
						'accesskey' => 's'
					)
				)
			);

			if($this->_context[0] == 'edit' && !$isOwner){
				$div->appendChild(
					Widget::Submit(
						'action[delete]', __('Delete'),
						array(
							'class' => 'confirm delete',
							'title' => __('Delete this user')
						)
					)
				);
			}

			$this->Form->appendChild($div);

		}
*/
		public function __actionNew(){
			if(array_key_exists('save', $_POST['action'])) {
				$this->__save(new Role);
			}
		}
		
		public function __actionEdit(){
			if(array_key_exists('save', $_POST['action'])) {
				$this->__save(Role::load((int)$this->_context[1]));
			}
			
			elseif(array_key_exists('delete', $_POST['action'])) {
				try{
					Role::delete((int)$this->_context[1]);
					redirect(ADMIN_URL . '/extension/aac/roles/');
				
				}
				catch(RoleException $e){
					$this->alerts()->append(
						$e->getMessage(), 
						AlertStack::ERROR, $this->errors
					);
				}

				catch(Exception $e){
					$this->alerts()->append(__(
						'An unknown error has occurred. <a class="more">Show trace information.</a>'), 
						AlertStack::ERROR, $e
					);
				}
			}
		}
		
		private function __save(Role $role){
			
			$fields = $_POST['fields'];
			$role->name = $fields['name'];
			$role->description = $fields['description'];
			
			$role->flushPermissions();
			
			foreach($fields['permissions'] as $key => $p){
				foreach($p as $type => $level){
					$role->permissions()->{"{$key}.{$type}"} = (int)$level;
				}
			}
			
			###
			# Delegate: AACRolePreCreate
			# Description: Just before creation of a new Role. Role object, fields array and error stack provided
			Extension::notify(
				'AACRolePreCreate', '/extension/aac/roles/new/',
				array(
					'fields' => $fields,
					'role' => &$role,
					'errors' => &$this->errors
				)
			);

			$this->role = $role;

			try{
				$result = Role::save($this->role, $this->errors);
				
				if(!isset($this->role->id)){
					$this->role->id = $result;
				}
				
				###
				# Delegate: AACRolePostCreate
				# Description: After creation of a new Role. Role object provided
				Extension::notify(
					'AACRolePostCreate', '/extension/aac/roles/new/',
					array(
						'role' => $this->role
					)
				);
				
				redirect(sprintf(
					"%s/extension/aac/roles/edit/%d/:%s/", 
					ADMIN_URL, $this->role->id, (isset($role->id) ? 'saved' : 'created')
				));
			}
			
			catch(RoleException $e){
				$this->alerts()->append(__(
					'There were some problems while attempting to save. Please check below for problem fields.'), 
					AlertStack::ERROR, $this->errors
				);
			}
			
			catch(Exception $e){
				$this->alerts()->append(__(
					'An unknown error has occurred. <a class="more">Show trace information.</a>'), 
					AlertStack::ERROR, $e
				);
			}
		}
	}
