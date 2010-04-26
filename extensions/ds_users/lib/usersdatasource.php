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
				$user_ids = NULL;

				##	User Filtering
				//	TODO: Check that this is working once Duplicators are ready
				if (is_array($this->parameters()->filter) && !empty($this->parameters()->filter)) {
					foreach ($this->parameters()->filter as $field => $value){
						if(!is_array($value) && trim($value) == '') continue;

						$ret = $this->processUserFilter($field, $this->replaceParametersInString($value, $ParameterOutput));
						
						
						if(!is_null($ret)){
							$user_ids = (!is_null($user_ids) ? array_intersect($user_ids, $ret) : $ret);
							continue;
						}
						
						// Since this is an "AND" operation, if $user_ids is ever 
						// empty, it means one of the filters has returned no results
						break;
						
					}
					
					if(!empty($user_ids)){
						$users = Symphony::Database()->query(
							"SELECT * FROM `tbl_users` WHERE `id` IN (%s) ORDER BY `id` ASC", 
							array(
								implode(',', array_values($user_ids))
							),
							'UserResult'
						);
					}
					
				}

				else {
					$users = new UserIterator;
				}

				if(is_object($users) && $users->length() > 0){

					$included_fields = $this->parameters()->{'included-elements'};

					foreach($users as $user) {
						$xUser = $result->createElement('user', null);
						$xUser->setAttribute('id', $user->id);

						foreach($included_fields as $element){

							switch($element){
								
								case 'name':
									$xUser->appendChild($result->createElement('name', $user->getFullName()));
									break;
									
								case 'email-address':
									$xUser->appendChild($result->createElement('email-address', $user->email));
									break;
									
								case 'username':
									$xUser->appendChild($result->createElement('username', $user->username));
									break;
								
								case 'language':
									if(!is_null($user->language)) $xUser->appendChild($result->createElement('language', $user->language));
									break;
									
								case 'authentication-token':
									if($user->isTokenActive()) $xUser->appendChild($result->createElement('authentication-token', $user->createAuthToken()));
									break;
									
								case 'default-section':

									try {
										$section = Section::loadFromHandle($user->default_section);

										$default_section = $result->createElement('default-section', $section->name);
										$default_section->setAttribute('handle', $section->handle);

										$xUser->appendChild($default_section);
									}
									catch (SectionException $error) {
										// Do nothing, section doesn't exist, but no need to error out about it..
									}
								
									break;
									
								default:
									break;
								
							}
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
				$root->appendChild($result->createElement(
					'error', General::sanitize($error->getMessage())
				));
			}

			if ($this->_force_empty_result) $this->emptyXMLSet($root);

			$result->appendChild($root);

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

			$query = sprintf("SELECT `id` FROM `tbl_users` WHERE `%s` IN ('%%s')", $field);

			$result = Symphony::Database()->query($query, array(
				implode("','", $bits)
			));

			return ($result->length() > 0 ? $result->resultColumn('id') : NULL);
		}

		public function prepareSourceColumnValue() {

			return Widget::TableData(
				Widget::Anchor("Users", URL . '/symphony/system/users/', array(
					'title' => 'Users'
				))
			);

		}
	}
