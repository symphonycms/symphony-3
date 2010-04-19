<?php

	Class NavigationDataSource extends DataSource {

		public function __construct(){
			// Set Default Values
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
				'root-element' => NULL,
				'parent' => NULL,
				'type' => NULL
			);
		}

		final public function type(){
			return 'ds_navigation';
		}

		public function template(){
			return EXTENSIONS . '/ds_navigation/templates/datasource.php';
		}

		public function save(MessageStack &$errors){
			return parent::save($errors);
		}

		public function render(Register &$ParameterOutput){
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
				Widget::Anchor("Views", URL . '/symphony/blueprints/views/', array(
					'title' => 'Views'
				))
			);

		}
	}
