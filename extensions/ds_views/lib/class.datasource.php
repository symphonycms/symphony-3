<?php
	
	require_once LIB . '/class.datasource.php';
	
	Class ViewsDataSource extends DataSource {
		public function __construct(){
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
				'root-element' => NULL,
				'parent' => NULL,
				'type' => NULL
			);
		}

		public function getType() {
			return 'ViewsDataSource';
		}

		public function getTemplate(){
			return EXTENSIONS . '/ds_views/templates/datasource.php';
		}
		
	/*-----------------------------------------------------------------------*/
		
		public function prepare(array $data = null) {
			if(!is_null($data)){
				if(isset($data['about']['name'])) $this->about()->name = $data['about']['name'];
				if(isset($data['parent'])) $this->parameters()->parent = $data['parent'];
				if(isset($data['type'])) $this->parameters()->type = $data['type'];
			}
		}

		public function view(SymphonyDOMElement $wrapper, MessageStack $errors) {
			$page = Administration::instance()->Page;
			
			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$right = $layout->createColumn(Layout::LARGE);

		//	Essentials --------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Essentials'));

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

			$p = $page->createElement('p');
			$p->setAttribute('class', 'help');
			$p->appendChild(
				$page->createElement('code', '{$param}')
			);
			$p->setValue(' or ');
			$p->appendChild(
				$page->createElement('code', 'Value')
			);
			$fieldset = Widget::Fieldset(__('Filtering'), $p);

			// Parent View:
			$label = Widget::Label(__('Parent View'));
			$input = Widget::Input('fields[parent]', General::sanitize($this->parameters()->parent));
			$label->appendChild($input);

			if (isset($errors->{'parent'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'parent'});
			}

			$fieldset->appendChild($label);

			$ul = $page->createElement('ul');
			$ul->setAttribute('class', 'tags');

			foreach (new ViewIterator as $view) {
				$ul->appendChild($page->createElement('li', $view->path));
			}

			$fieldset->appendChild($ul);

			// View Type:
			$label = Widget::Label(__('View Type'));
			$input = Widget::Input('fields[type]', General::sanitize($this->parameters()->type));
			$label->appendChild($input);

			if (isset($errors->{'type'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'type'});
			}

			$fieldset->appendChild($label);

			$ul = $page->createElement('ul');
			$ul->setAttribute('class', 'tags');

			foreach(View::fetchUsedTypes() as $type){
				$ul->appendChild($page->createElement('li', $type));
			}

			$fieldset->appendChild($ul);

/*
			if (isset($this->parameters()->parent) && !is_null($this->parameters()->parent)){
				$li = new XMLElement('li');
				$li->setAttribute('class', 'unique');
				$li->appendChild(new XMLElement('h4', __('Parent View')));
				$label = Widget::Label(__('Value'));
				$label->appendChild(Widget::Input(
					'fields[parent]', General::sanitize($this->parameters()->parent)
				));
				$li->appendChild($label);
				$li->appendChild($ul);
				$ol->appendChild($li);
			}

			$li = new XMLElement('li');
			$li->setAttribute('class', 'unique template');
			$li->appendChild(new XMLElement('h4', __('Parent View')));
			$label = Widget::Label(__('Value'));
			$label->appendChild(Widget::Input('fields[parent]'));
			$li->appendChild($label);
			$li->appendChild($ul);
			$ol->appendChild($li);

			$ul = new XMLElement('ul');
			$ul->setAttribute('class', 'tags');
			foreach(View::fetchUsedTypes() as $type) $ul->appendChild(new XMLElement('li', $type));

			if (isset($this->parameters()->type) && !is_null($this->parameters()->type)){
				$li = new XMLElement('li');
				$li->setAttribute('class', 'unique');
				$li->appendChild(new XMLElement('h4', __('View Type')));
				$label = Widget::Label(__('Value'));
				$label->appendChild(Widget::Input(
					'fields[type]',
					General::sanitize($this->parameters()->type)
				));
				$li->appendChild($label);
				$li->appendChild($ul);
				$ol->appendChild($li);
			}

			$li = new XMLElement('li');
			$li->setAttribute('class', 'unique template');
			$li->appendChild(new XMLElement('h4', __('View Type')));
			$label = Widget::Label(__('Value'));
			$label->appendChild(Widget::Input('fields[type]'));
			$li->appendChild($label);
			$li->appendChild($ul);
			$ol->appendChild($li);
*/

			$right->appendChild($fieldset);
			$layout->appendTo($wrapper);
		}
		
	/*-----------------------------------------------------------------------*/

		public function render(Register $ParameterOutput){
			$result = new XMLDocument;
			$root = $result->createElement($this->parameters()->{'root-element'});

			try {
				$filter_parent = (isset($this->parameters()->parent) && $this->parameters()->parent != "");
				$filter_type = (isset($this->parameters()->type) && $this->parameters()->type != "");

				if ($filter_parent && $filter_type) {
					$filtered_by_parent = new ViewIterator('/' . $this->parameters()->parent . '/');

					$iterator = array();
					foreach($filtered_by_parent as $v) if(@in_array($type, $v->types)) {
						$iterator[$v->guid] = $v;
					}
				}

				else if ($filter_parent) {
					$iterator = new ViewIterator('/' . $this->parameters()->parent . '/', false);
				}

				else if ($filter_type) {
					$iterator = View::findFromType($this->parameters()->type);
				}

				else {
					$iterator = new ViewIterator(null, false);
				}

				if(count($iterator) <= 0) {
					throw new DatasourceException("No views found.");
				}

				else foreach ($iterator as $index => $view) {

					if($filter_parent) $view = $view->parent();

					$node = $this->__buildPageXML($view);

					if(!is_null($node)) {
						$root->appendChild(
							$result->importNode($node, true)
						);
					}
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

			$result->appendChild($root);

			return $result;
		}

		public function __buildPageXML($view, View $parent = null) {
			$result = new XMLDocument;

			$xView = $result->createElement('view');
			$xView->setAttribute('handle', $view->handle);

			$xView->appendChild(
				$result->createElement('title', $view->title)
			);

			##	Types
			if(is_array($view->types) && count($view->types) > 0){
				$types = $result->createElement('types');
				foreach($view->types as $t){
					$types->appendChild(
						$result->createElement('item', General::sanitize($t))
					);
				}
				$xView->appendChild($types);
			}

			##	Children
			foreach($view->children() as $child) {
				$node = $this->__buildPageXML($child, $view);

				if(!is_null($node)) {
					$xView->appendChild(
						$result->importNode($node, true)
					);
				}
			};

			return $xView;

		}

		public function prepareSourceColumnValue() {

			return Widget::TableData(
				Widget::Anchor("Views", ADMIN_URL . '/blueprints/views/', array(
					'title' => 'Views'
				))
			);

		}
	}
