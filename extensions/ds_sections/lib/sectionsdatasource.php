<?php

	Class SectionsDataSource extends DataSource {

		public function __construct(){
			// Set Default Values
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
			   'root-element' => NULL,
			   'limit' => 20,
			   'page' => 1,
			   'section' => NULL,
			   'conditions' => array(),
			   'filter' => array(),
			   'redirect-404-on-empty' => false,
			   'sort-field' => 'system:id',
			   'sort-order' => 'desc',
			   'included-elements' => array(),
			   'parameter-output' => array(),
			);
		}

		final public function type(){
			return 'ds_sections';
		}

		public function template(){
			return EXTENSIONS . '/ds_sections/templates/datasource.php';
		}

		public function save(MessageStack &$errors){

			if (strlen(trim($this->parameters()->limit)) == 0 || (is_numeric($this->parameters()->limit) && $this->parameters()->limit < 1)) {
				$errors->append('limit', __('A result limit must be set'));
			}

			if (strlen(trim($this->parameters()->page)) == 0 || (is_numeric($this->parameters()->page) && $this->parameters()->page < 1)) {
				$errors->append('page', __('A page number must be set'));
			}

			return parent::save($errors);
		}

		/*public function canAppendAssociatedEntryCount() {
			return false;
		}

		public function canAppendPagination() {
			return false;
		}

		public function canHTMLEncodeText() {
			return false;
		}

		public function canRedirectOnEmpty() {
			return false;
		}

		public function getFilters() {
			return array();
		}

		public function getGroupField() {
			return '';
		}

		public function getIncludedElements() {
			return array();
		}

		public function getOutputParams() {
			return array();
		}

		public function getPaginationLimit() {
			return '20';
		}

		public function getPaginationPage() {
			return '1';
		}

		public function getRequiredURLParam() {
			return '';
		}

		public function getRootElement() {
			return 'sections';
		}

		public function getSection() {
			return null;
		}

		public function getSortField() {
			return 'system:id';
		}

		public function getSortOrder() {
			return 'desc';
		}*/

		public function render(Register &$ParameterOutput){
			$execute = true;

			$doc = new XMLDocument;
			$doc->appendChild($doc->createElement($this->parameters()->{'root-element'}));

			//	TODO: Dependancies

			//	Conditions
			//	If any one condtion returns true (that is, do not execute), the DS will not execute at all
			if(is_array($this->parameters()->conditions)) {
				foreach($this->parameters()->conditions as $condition) {
					$c = Datasource::resolveParameter($condition['parameter'], $ParameterOutput);

					// Is Empty
					if($condition['logic'] == 'empty' && (is_null($c) || strlen($c) == 0)){
						$execute = false;
					}
					
					// Is Set
					elseif($condition['logic'] == 'set' && !is_null($c)){
						$execute = false;
					}

				}
			}

			if($execute === true) {

				if(is_array($this->parameters()->filters)) {

				}

				//	Retrieve Datasource Filters for each of the Fields
				//	Apply the Sorting & Direction
				//	Apply the limiting

				//	If count of result is 0 && redirect to 404 is true, throw FrontendException

				//	Inject any Output Params into the Register through ParameterOutput

				//	If any of the system: mode fields are called, append them to the front of the Datasource
				//	just after the root element.

				//	Foreach of the rows in the result, call appendFormattedElement

				//	Return a DOMDocument to the View::render function.

				/*
				$sort_field = $section->fetchFieldByHandle($section->{'publish-order-handle'});
				$sort_field->buildSortingSQL($joins, $order, $section->{'publish-order-direction'});

				$query = sprintf("
					SELECT e.*
					FROM `tbl_entries` AS e
					%s
					WHERE `section` = '%s'
					%s
					LIMIT %d, %d",
					$joins, $section->handle, $order, $pagination['start'], $pagination['entries-per-page']
				);

				$entries = Symphony::Database()->query($query, array(
						$section->handle,
						$section->{'publish-order-handle'}
					), 'EntryResult'
				);
				*/
				echo "DOING MY DATASOURCE WOOOOO";



				//return $doc;
			} else {
				echo "DON'T EXECUTE";

				//return null;
			}

			var_dump($execute);
			var_dump($this->parameters());
		}


		public function prepareSourceColumnValue(){
			$section = Section::loadFromHandle($this->_parameters->section);

			if ($section instanceof Section) {
				return Widget::TableData(
					Widget::Anchor($section->name, URL . '/symphony/blueprints/sections/edit/' . $section->handle . '/', array(
						'title' => $section->handle
					))
				);
			}

			else {
				return Widget::TableData(__('None'), array(
					'class' => 'inactive'
				));
			}
		}
	}
