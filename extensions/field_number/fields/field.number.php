<?php

	class FieldNumber extends Field {
		function __construct(){
			parent::__construct();
			$this->_name = 'Number';
		}

		public function create() {
			return Symphony::Database()->query(sprintf(
				"
					CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
					  	`id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
					  	`entry_id` INT(11) UNSIGNED NOT NULL,
					  	`value` DOUBLE DEFAULT NULL,
					  	PRIMARY KEY (`id`),
					  	KEY `entry_id` (`entry_id`),
					  	KEY `value` (`value`)
					) ENGINE=MyISAM;
				",
				$this->{'section'},
				$this->{'element-name'},
				$this->{'column-type'}
			));
		}

		public function allowDatasourceOutputGrouping(){
			return true;
		}

		public function allowDatasourceParamOutput(){
			return true;
		}

		public function canFilter(){
			return true;
		}

		public function isSortable(){
			return true;
		}

		public function canPrePopulate(){
			return true;
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $errors) {
			parent::displaySettingsPanel($wrapper, $errors);

			$document = $wrapper->ownerDocument;

			$options_list = $document->createElement('ul');
			$options_list->setAttribute('class', 'options-list');

			$this->appendShowColumnCheckbox($options_list);
			$this->appendRequiredCheckbox($options_list);

			// 	TODO: Possible allow the switching between INT, DOUBLE table column types
			//	This would need to trigger a MySQL ALTER COLUMN call if it happened, or that option
			//	be made static after creation.

			$wrapper->appendChild($options_list);
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry=NULL, $data=NULL) {
			$document = $wrapper->ownerDocument;
			$element_name = $this->{'element-name'};

			$label = Widget::Label(
				(isset($this->{'publish-label'}) && strlen(trim($this->{'publish-label'})) > 0
					? $this->{'publish-label'}
					: $this->name)
			);

			if ($this->{'required'} != 'yes') {
				$label->appendChild($wrapper->ownerDocument->createElement('em', __('Optional')));
			}

			$input = Widget::Input(
				"fields[$element_name]", $data->value
			);

			$label->appendChild($input);

			if ($errors->valid()) {
				$label = Widget::wrapFormElementWithError($label, $errors->current()->message);
			}

			$wrapper->appendChild($label);
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/
		
		public function validateData(MessageStack $errors, Entry $entry, $data = null) {
			if (self::STATUS_OK != parent::validateData($errors, $entry, $data)) {
				return self::STATUS_ERROR;
			}
			
			if (!isset($data->value) || empty($data->value)) return self::STATUS_OK;
			
			if (!is_numeric($data->value)) {
				$errors->append(
					null, (object)array(
					 	'message' => __("'%s' must be a number.", array($this->{'name'})),
						'code' => self::ERROR_INVALID
					)
				);
				
				return self::STATUS_ERROR;
			}
			
			return self::STATUS_OK;
		}
		
	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/
		
		public function getFilterTypes($data) {
			return array(
				array('is', false, __('Is')),
				array('is-not', $data->type == 'is-not', __('Is not')),
				array('less-than', $data->type == 'less-than', __('Less than')),
				array('less-than-or-equal', $data->type == 'less-than-or-equal', __('Less than or equal')),
				array('more-than', $data->type == 'more-than', __('More than')),
				array('more-than-or-equal', $data->type == 'more-than-or-equal', __('More than or equal'))
			);
		}
		
		public function buildFilterQuery($filter, &$joins, array &$where, Register $parameter_output = null) {
			$filter = $this->processFilter($filter);
			$filter_join = DataSource::FILTER_OR;
			$db = Symphony::Database();
			$statements = array();
			
			// Exact matches:
			switch ($filter->type) {
				case 'is':					$operator = '='; break;
				case 'is-not':				$operator = '!='; break;
				case 'less-than':			$operator = '>'; break;
				case 'less-than-or-equal':	$operator = '>='; break;
				case 'more-than':			$operator = '<'; break;
				case 'more-than-or-equal':	$operator = '<='; break;
			}
			
			if (empty($this->last_handle)) {
				$this->join_handle = $this->buildFilterJoin($joins);
			}
			
			$handle = $this->join_handle;
			
			$value = DataSource::replaceParametersInString(
				trim($filter->value), $parameter_output
			);
			
			$statements[] = $db->prepareQuery(
				"%d {$operator} {$handle}.value",
				array($value)
			);
			
			if (empty($statements)) return true;
			
			if ($filter_join == DataSource::FILTER_OR) {
				$statement = "(\n\t" . implode("\n\tOR ", $statements) . "\n)";
			}
			
			else {
				$statement = "(\n\t" . implode("\n\tAND ", $statements) . "\n)";
			}
			
			$where[] = $statement;
			
			return true;
		}

		/*-------------------------------------------------------------------------
			Grouping:
		-------------------------------------------------------------------------*/

		public function groupRecords($records) {
			if (!is_array($records) or empty($records)) return;

			$groups = array(
				$this->{'element-name'} => array()
			);

			foreach ($records as $record) {
				$data = $record->getData($this->{'id'});

				$value = $data['value'];
				$element = $this->{'element-name'};

				if (!isset($groups[$element][$value])) {
					$groups[$element][$value] = array(
						'attr'		=> array(
							'value'	=> $value
						),
						'records'	=> array(),
						'groups'	=> array()
					);
				}

				$groups[$element][$value]['records'][] = $record;
			}

			return $groups;
		}
	}

	return 'FieldNumber';

?>