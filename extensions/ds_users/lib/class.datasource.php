<?php

	Class UsersDataSource extends DataSource {
		public function __construct(){
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
				'root-element' => NULL,
				'filters' => array(),
				'included-elements' => array()
			);
		}

		final public function getExtension(){
			return 'ds_users';
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
			$context = $page->createElement('div');
			$context->setAttribute('class', 'filters-duplicator context context-' . $section_handle);

			$templates = $page->createElement('ol');
			$templates->setAttribute('class', 'templates');

			$instances = $page->createElement('ol');
			$instances->setAttribute('class', 'instances');

			$sortableColumns = array(
				array(
					'name' => __('ID'),
					'column' => 'id',
					'value' => $this->parameters()->filters['id']
				),
				array(
					'name' => __('Username'),
					'column' => 'username',
					'value' => $this->parameters()->filters['username']
				),
				array(
					'name' => __('First Name'),
					'column' => 'first-name',
					'value' => $this->parameters()->filters['first-name']
				),
				array(
					'name' => __('Last Name'),
					'column' => 'last-name',
					'value' => $this->parameters()->filters['last-name']
				),
				array(
					'name' => __('Email Address'),
					'column' => 'email',
					'value' => $this->parameters()->filters['email']
				)
			);

			foreach($sortableColumns as $column) {
				$this->appendFilter($templates, $column);

				if(is_array($this->parameters()->filters) && array_key_exists($column['column'], $this->parameters()->filters)) {
					$this->appendFilter($instances, $column);
				}
			}

			$context->appendChild($templates);
			$context->appendChild($instances);
			$fieldset->appendChild($context);
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

		protected function appendFilter(SymphonyDOMElement $wrapper, $condition = array()) {
			$document = $wrapper->ownerDocument;

			$li = $document->createElement('li');

			$name = $document->createElement('span', $condition['name']);
			$name->setAttribute('class', 'name');
			$li->appendChild($name);

			$wrapper->appendChild($li);

			$label = Widget::Label(__('Value'));
			$input = Widget::Input('fields[filters][' . $condition['column'] . ']');

			if(isset($condition['value'])) {
				$input->setAttribute("value", $condition['value']);
			}

			$label->appendChild($input);

			$li->appendChild($label);

			$wrapper->appendChild($li);
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
				Widget::Anchor("Users", URL . '/symphony/system/users/', array(
					'title' => 'Users'
				))
			);

		}
	}
