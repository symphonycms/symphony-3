<?php
	
	require_once LIB . '/class.datasource.php';
	
	Class UsersDataSource extends DataSource {
		public function __construct(){
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
				'root-element' => NULL,
				'filters' => array(),
				'included-elements' => array()
			);
		}

		public function getType() {
			return 'UsersDataSource';
		}

		public function getTemplate(){
			return EXTENSIONS . '/ds_users/templates/datasource.php';
		}
		
	/*-----------------------------------------------------------------------*/
		
		public function prepare(array $data=NULL) {
			if(!is_null($data)){
				if(isset($data['about']['name'])) $this->about()->name = $data['about']['name'];
				if(isset($data['included-elements'])) $this->parameters()->{'included-elements'} = $data['included-elements'];

				$this->parameters()->filters = array();

				if(isset($data['filters']) && is_array($data['filters'])){
					foreach($data['filters'] as $handle => $value){
						$this->parameters()->filters[$handle] = $value;
					}
				}
			}
		}

		public function view(SymphonyDOMElement $wrapper, MessageStack $errors) {
			$page = $wrapper->ownerDocument;
			$page->insertNodeIntoHead($page->createScriptElement(URL . '/extensions/ds_sections/assets/view.js'), 55533140);

			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$middle = $layout->createColumn(Layout::LARGE);
			$right = $layout->createColumn(Layout::SMALL);

		//	Essentials --------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Essentials'), null, array(
				'class' => 'settings'
			));

			// Name:
			$label = Widget::Label(__('Name'));
			$input = Widget::Input('fields[about][name]', General::sanitize($this->about()->name));
			$label->appendChild($input);

			if (isset($errors->{'about::name'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'about::name'});
			}

			$fieldset->appendChild($label);
			$left->appendChild($fieldset);

		//	Filtering ---------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Filtering'), '<code>{$param}</code> or <code>Value</code>', array(
				'class' => 'settings'
			));
			
			// Filters
			$duplicator = new Duplicator(__('Add Filter'));
			
			$filters = array(
				'id' => __('ID'),
				'username' => __('Username'),
				'first-name' => __('First Name'),
				'last-name' => __('Last Name'),
				'email' => __('Email Address'),
			);
			
			foreach($filters as $handle => $name) {
				$this->appendFilter($duplicator, $handle, $name);

				if(is_array($this->parameters()->filters) && array_key_exists($handle, $this->parameters()->filters)){
					$this->appendFilter($duplicator, $handle, $name, $this->parameters()->filters[$handle]);
				}
			}

			$duplicator->appendTo($fieldset);
			$middle->appendChild($fieldset);

		//	Output options ----------------------------------------------------

			$fieldset = Widget::Fieldset(__('Output Options'));

			$select = Widget::Select('fields[included-elements][]', array(
				array('username', in_array('username', $this->parameters()->{"included-elements"}), 'username'),
				array('name', in_array('name', $this->parameters()->{"included-elements"}), 'name'),
				array('email-address', in_array('email-address', $this->parameters()->{"included-elements"}), 'email-address'),
				array('language', in_array('language', $this->parameters()->{"included-elements"}), 'language'),
				array('authentication-token', in_array('authentication-token', $this->parameters()->{"included-elements"}), 'authentication-token'),
				array('default-section', in_array('default-section', $this->parameters()->{"included-elements"}), 'default-section'),
			));
			$select->setAttribute('class', 'filtered');
			$select->setAttribute('multiple', 'multiple');

			$label = Widget::Label(__('Included Elements'));
			$label->appendChild($select);

			$fieldset->appendChild($label);
			$right->appendChild($fieldset);

			$layout->appendTo($wrapper);
		}

		protected function appendFilter(Duplicator $duplicator, $handle, $name, $value=NULL) {
			$document = $duplicator->ownerDocument;
		
			if (is_null($value)) {
				$item = $duplicator->createTemplate($name);
			}
		
			else {
				$item = $duplicator->createInstance($name);
			}
			
			$group = $document->createElement('div');
			$group->setAttribute('class', 'group double');

			// Value
			$label = $document->createElement('label', __('Value'));
			$input = Widget::Input('fields[filters][' . $handle . ']');
			
			if(!is_null($value)) {
				$input->setAttribute("value", $value);
			}
			$label->appendChild($input);
			$group->appendChild($label);

			$group->appendChild($label);
			$item->appendChild($group);

		}
		
	/*-----------------------------------------------------------------------*/

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
				Widget::Anchor("Users", ADMIN_URL . '/system/users/', array(
					'title' => 'Users'
				))
			);

		}
	}
