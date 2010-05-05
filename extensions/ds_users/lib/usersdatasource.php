<?php

	Class UsersDataSource extends DataSource {

		public function __construct(){
			// Set Default Values
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
				'root-element' => NULL,
				'filters' => array(),
				'included-elements' => array()
			);
		}

		final public function type(){
			return 'ds_users';
		}

		public function template(){
			return EXTENSIONS . '/ds_users/templates/datasource.php';
		}

		public function save(MessageStack $errors){
			return parent::save($errors);
		}

		//	TODO: Allow Filtering by Parameter Output
		public function render(Register $ParameterOutput){
			$result = new XMLDocument;
			$root = $result->createElement($this->parameters()->{'root-element'});

			try {

				##	User Filtering
				if (is_array($this->parameters()->filters) && !empty($this->parameters()->filters)) {
					
					$user_ids = NULL;
					$where_clauses = array();

					$query = "SELECT * FROM `tbl_users` WHERE 1 %s ORDER BY `id` ASC";
					
					foreach ($this->parameters()->filters as $field => $value){
						if(!is_array($value) && trim($value) == '') continue;
						
						$value = self::replaceParametersInString($value, $ParameterOutput);
						
						if (!is_array($value)) {
							$value = preg_split('/,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
							$value = array_map('trim', $value);
						}
						
						$where_clauses[] = sprintf("`%s` IN ('%s')", str_replace('-', '_', $field), implode(',', $value));

					}
					
					// Should the $where_clauses array be empty, it means there were no valid filters. I.E. they were all empty strings.
					// If that is the case, we still want Users to get returned, hence the "WHERE 1" part of the SQL to avoid errors.
					if(!empty($where_clauses)) $where_clauses = 'AND' . implode(' AND ', $where_clauses);
					else $where_clauses = NULL;
					
					$users = Symphony::Database()->query(sprintf($query, $where_clauses), 
						array(),
						'UserResult'
					);
					
				}
				
				// There are no filters, meaning we want all users, so use a UserIterator instead
				else{
					$users = new UserIterator;
				}
				
				if($users->length() > 0){

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

			//catch (FrontendPageNotFoundException $error) {
			//	FrontendPageNotFoundExceptionHandler::render($error);
			//}

			catch (Exception $error) {
				$root->appendChild($result->createElement(
					'error', General::sanitize($error->getMessage())
				));
			}

			$result->appendChild($root);

			return $result;
		}



		public function prepareSourceColumnValue() {

			return Widget::TableData(
				Widget::Anchor("Users", URL . '/symphony/system/users/', array(
					'title' => 'Users'
				))
			);

		}
	}
