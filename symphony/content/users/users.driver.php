<?php

	/**
	* UsersDriver class...
	*/

	Class UsersDriver {

		public $url;
		public $view;

		public function __construct() {
			$this->view = Controller::instance()->View;
			$this->url = Controller::instance()->url;

			$this->setTitle();
		}

		public function setTitle() {
			$this->view->title = __('Users');
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

	/*require_once(LIB . '/class.administrationpage.php');
 	//require_once(LIB . '/class.sectionmanager.php');

	Class contentSystemUsers extends AdministrationPage{

		private $user;

		public function __viewIndex(){

		    $users = new UserIterator;

			$users_xml = $this->createElement('users');

			foreach($users as $u){

				$user = $this->createElement('user');
				$user->setAttribute('id',$u->id);
				$user->appendChild($this->createElement('username',$u->username));
				$user->appendChild($this->createElement('full-name', $u->getFullName()));
				$user->appendChild($this->createElement('email', $u->email));

				if($u->last_seen != NULL){
					$user->setAttribute('last-seen',DateTimeObj::get(__SYM_DATETIME_FORMAT__, strtotime($u->last_seen)));
				}

				$users_xml->appendChild($user);
			}

			return $users_xml;

		}

		public function __actionIndex(){
			if($_POST['with-selected'] == 'delete'){

				$checked = array_keys($_POST['items']);

				## FIXME: Fix this delegate
				###
				# Delegate: Delete
				# Description: Prior to deleting an User. ID is provided.
				//Extension::notify('Delete', getCurrentPage(), array('user_id' => $user_id));

				foreach($checked as $user_id){
					if(Administration::instance()->User->id == $user_id) continue;
					User::delete($user_id);
				}

				redirect(ADMIN_URL . '/system/users/');
			}
		}

		## Both the Edit and New pages need the same form
		public function __viewNew(){
			if(!($this->user instanceof User)){
				$this->user = new User;
			}
			$this->__form();
		}

		public function __viewEdit(){

			if(!($this->user instanceof User) && !($this->user = User::load((int)$this->_context[1]))){
				throw new SymphonyErrorPage('The user profile you requested does not exist.', 'User not found');
			}

			$this->__form();
		}

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

			/*if(isset($_POST['fields']))
				$user = $this->user;

			elseif($this->_context[0] == 'edit'){

				if(!$user_id = $this->_context[1]) redirect(ADMIN_URL . '/system/users/');

				if(!$user = UserManager::fetchByID($user_id)){
					throw new SymphonyErrorPage('The user profile you requested does not exist.', 'User not found');
				}
			}

			else *



			if($this->_context[0] == 'edit' && $this->user->id == Administration::instance()->User->id) $isOwner = true;

			$this->setTitle(__(($this->_context[0] == 'new' ? '%1$s &ndash; %2$s &ndash; Untitled' : '%1$s &ndash; %2$s &ndash; %3$s'), array(__('Symphony'), __('Users'), $this->user->getFullName())));
			$this->appendSubheading(($this->_context[0] == 'new' ? __('New User') : $this->user->getFullName()));

			### Essentials ###
			$fieldset = Widget::Fieldset(__('Essentials'));

			$label = Widget::Label(__('First Name'));
			$label->appendChild(Widget::Input('fields[first_name]', $this->user->{'first_name'}));
			$fieldset->appendChild((isset($this->errors->{'first-name'}) ? Widget::wrapFormElementWithError($label, $this->errors->{'first-name'}) : $label));

			$label = Widget::Label(__('Last Name'));
			$label->appendChild(Widget::Input('fields[last_name]', $this->user->{'last_name'}));
			$fieldset->appendChild((isset($this->errors->{'last-name'}) ? Widget::wrapFormElementWithError($label, $this->errors->{'last-name'}) : $label));

			$label = Widget::Label(__('Email Address'));
			$label->appendChild(Widget::Input('fields[email]', $this->user->email));
			$fieldset->appendChild((isset($this->errors->email) ? Widget::wrapFormElementWithError($label, $this->errors->email) : $label));

			$left->appendChild($fieldset);
			###

			### Login Details ###
			$fieldset = Widget::Fieldset(__('Login Details'));

			$label = Widget::Label(__('Username'));
			$label->appendChild(Widget::Input('fields[username]', $this->user->username, NULL));
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
			if($this->user->auth_token_active == 'yes') $input->setAttribute('checked', 'checked');
			$temp = ADMIN_URL . '/login/' . $this->user->createAuthToken() . '/';

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
				$options[] = array($s->handle, $this->user->default_section == $s->handle, $s->name);
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
					array(NULL, is_null($this->user->language), __('System Default'))
				);

				foreach($languages as $code => $name) {
					$options[] = array($code, $code == $this->user->language, $name);
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

		public function __actionNew(){

			if(array_key_exists('save', $_POST['action']) || array_key_exists('done', $_POST['action'])) {

				$fields = $_POST['fields'];

			    $this->user = new User;

				$this->user->email = $fields['email'];
				$this->user->username = $fields['username'];
				$this->user->first_name = General::sanitize($fields['first_name']);
				$this->user->last_name = General::sanitize($fields['last_name']);
				$this->user->last_seen = NULL;
				$this->user->password = (trim($fields['password']) == '' ? NULL : md5($fields['password']));
				$this->user->default_section = $fields['default_section'];
				$this->user->auth_token_active = ($fields['auth_token_active'] ? $fields['auth_token_active'] : 'no');
				$this->user->language = $fields['language'];

				###
				# Delegate: UserPreCreate
				# Description: Just before creation of a new User. User object, fields and error array provided
				Extension::notify(
					'UserPreCreate', '/system/users/new/',
					array(
						'fields' => $fields,
						'user' => &$this->user,
						'errors' => &$this->errors
					)
				);

				if($this->errors->length() == 0 && $this->user->validate($this->errors)):

					if($fields['password'] != $fields['password-confirmation']){
						$this->errors->append('password', __('Passwords did not match'));
						$this->errors->append('password-confirmation', __('Passwords did not match'));
					}

					elseif($user_id = User::save($this->user)){

						###
						# Delegate: PostCreate
						# Description: Just after creation of a new User. The ID of the User is provided.
						Extension::notify('PostCreate', '/system/users/new/', array('user' => $this->user));

			  		   redirect(ADMIN_URL . "/system/users/edit/{$this->user->id}/:created/");

					}

				endif;

				if($this->errors->length() > 0){
					$this->alerts()->append(__('There were some problems while attempting to save. Please check below for problem fields.'), AlertStack::ERROR, $this->errors);
				}
				else{
					$this->alerts()->append(
						__('Unknown errors occurred while attempting to save. Please check your <a href="%s">activity log</a>.',
						array(ADMIN_URL . '/system/log/')),
						AlertStack::ERROR
					);
				}

			}
		}

		public function __actionEdit(){

			if(!$user_id = $this->_context[1]) redirect(ADMIN_URL . '/system/users/');

			if(array_key_exists('save', $_POST['action']) || array_key_exists('done', $_POST['action'])) {

				$fields = $_POST['fields'];

			    $this->user = User::load($user_id);

				$this->user->id = $user_id;

				$this->user->email = $fields['email'];
				$this->user->username = $fields['username'];
				$this->user->first_name = General::sanitize($fields['first_name']);
				$this->user->last_name = General::sanitize($fields['last_name']);

				if(trim($fields['password']) != ''){
					$this->user->password = md5($fields['password']);
					$changing_password = true;
				}

				$this->user->default_section = $fields['default_section'];
				$this->user->auth_token_active = ($fields['auth_token_active'] ? $fields['auth_token_active'] : 'no');
				$this->user->language = $fields['language'];

				###
				# Delegate: UserPreSave
				# Description: Just before creation of a new User. User object, fields and error array provided
				Extension::notify(
					'UserPreSave', '/system/users/edit/',
					array(
						'fields' => $fields,
						'user' => &$this->user,
						'errors' => &$this->errors
					)
				);

				$this->user->validate($this->errors);

				if($this->errors->length() == 0):

					if(($fields['password'] != '' || $fields['password-confirmation'] != '') && $fields['password'] != $fields['password-confirmation']){
						$this->errors->append('password', __('Passwords did not match'));
						$this->errors->append('password-confirmation', __('Passwords did not match'));
					}

					elseif(User::save($this->user)){

						Symphony::Database()->delete('tbl_forgotpass', array(DateTimeObj::getGMT('c'), $user_id), " `expiry` < '%s' OR `user_id` = %d ");

						// This is the logged in user, so update their session
						if($user_id == Administration::instance()->User->id){
							Administration::instance()->login($this->user->username, $this->user->password, true);
						}

						###
						# Delegate: PostSave
						# Description: Just after creation of a new User. The ID of the User is provided.
						Extension::notify('PostSave', '/system/users/edit/', array('user' => $this->user));

		  		    	redirect(ADMIN_URL . "/system/users/edit/{$this->user->id}/:saved/");

					}

					else{
						$this->alerts()->append(
							__('Unknown errors occurred while attempting to save. Please check your <a href="%s">activity log</a>.',
							array(ADMIN_URL . '/system/log/')),
							AlertStack::ERROR
						);
					}

				else:
					$this->alerts()->append(__('There were some problems while attempting to save. Please check below for problem fields.'), AlertStack::ERROR, $this->errors);
				endif;

			}

			elseif(array_key_exists('delete', $_POST['action'])){

				## FIXME: Fix this delegate
				###
				# Delegate: Delete
				# Description: Prior to deleting an User. ID is provided.
				//Extension::notify('Delete', getCurrentPage(), array('user_id' => $user_id));

				User::delete($user_id);

				redirect(ADMIN_URL . '/system/users/');
			}
		}

	}*/