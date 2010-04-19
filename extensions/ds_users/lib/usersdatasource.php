<?php

	Class UsersDataSource extends DataSource {

		public function __construct(){
			// Set Default Values
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
				'root-element' => NULL,
				'filter' => array(),
				'included-elements' => array()
			);
		}

		final public function type(){
			return 'ds_users';
		}

		public function template(){
			return EXTENSIONS . '/ds_users/templates/datasource.php';
		}

		public function save(MessageStack &$errors){
			return parent::save($errors);
		}

		//	TODO: Allow Filtering by Parameter Output
		public function render(Register &$ParameterOutput){
			$result = new XMLDocument;
			$root = $result->createElement($this->parameters()->{'root-element'});

			try {
				$user_ids = array();

				##	User Filtering
				//	TODO: Check that this is working once Duplicators are ready
				if (is_array($this->parameters()->filters) && !empty($this->parameters()->filters)) {
					foreach ($this->parameters()->filters as $field => $value){
						if(!is_array($value) && trim($value) == '') continue;

						$ret = $this->processUserFilter($field, $value);

						if(empty($ret)){
							$user_ids = array();
							break;
						}

						if(empty($user_ids)) {
							$user_ids = $ret;
							continue;
						}

						$user_ids = array_intersect($user_ids, $ret);

					}

					$users = UserManager::fetchByID(array_values($user_ids));
				}

				else {
					$users = UserManager::fetch();
				}

				if(is_array($users) && !empty($users)) {
					foreach($users as $user) {
						$xUser = $result->createElement('user', null);
						$xUser->setAttribute('id', $user->id);

						$fields = array(
							'name' => $result->createElement('name', $user->getFullName()),
							'username' => $result->createElement('username', $user->username),
							'email' => $result->createElement('email', $user->email)
						);

						if($user->isTokenActive()) {
							$fields['authentication-token'] = $result->createElement('authentication-token', $user->createAuthToken());
						}

						try {
							$section = Section::loadFromHandle($user->default_section);

							$default_section = $result->createElement('default-section', $section->name);
							$default_section->setAttribute('handle', $section->handle);

							$fields['default-section'] =  $default_section;
						}
						catch (SectionException $error) {
							// Do nothing, section doesn't exist, but no need to error out about it..
						}

						foreach($fields as $field) {
							$xUser->appendChild($value);
						}

						$root->appendChild($xUser);
					}
				}

				else {
					throw new DataSourceException("No records found.");
				}
			}

			catch (FrontendPageNotFoundException $error) {
				FrontendPageNotFoundExceptionHandler::render($error);
			}

			catch (Exception $error) {
				$root->appendChild($doc->createElement(
					'error', General::sanitize($error->getMessage())
				));
			}

			$result->appendChild($root);

			if ($this->_force_empty_result) $result = $this->emptyXMLSet();

			return $result;
		}

		protected function processUserFilter($field, $filter) {
			if (!is_array($filter)) {
				$bits = preg_split('/,\s*/', $filter, -1, PREG_SPLIT_NO_EMPTY);
				$bits = array_map('trim', $bits);
			}

			else {
				$bits = $filter;
			}

			$query = sprintf(
				"
					SELECT
						`id`
					FROM
						`tbl_users`
					WHERE
						`%s` IN ('%%s')
				",
				$field
			);

			$result = Symphony::Database()->query($query, array(
				implode("','", $bits)
			));
			$users = $result->resultColumn('id');

			return (is_array($users) && !empty($users) ? $users : NULL);
		}

		public function prepareSourceColumnValue() {

			return Widget::TableData(
				Widget::Anchor("Users", URL . '/symphony/system/users/', array(
					'title' => 'Users'
				))
			);

		}
	}
