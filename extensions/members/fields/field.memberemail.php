<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	class FieldMemberEmail extends Field {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct() {
			parent::__construct();

			$this->_name = 'Member Email';
		}

		public function create() {
			return Symphony::Database()->query(sprintf(
				"
					CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
						`entry_id` INT(11) UNSIGNED NOT NULL,
						`value` TEXT DEFAULT NULL,
						PRIMARY KEY (`id`),
						KEY `entry_id` (`entry_id`),
						FULLTEXT KEY `value` (`value`)
					)
				",
				$this->{'section'},
				$this->{'element-name'}
			));
		}

		public function allowDatasourceParamOutput() {
			return true;
		}

		public function canFilter() {
			return true;
		}

		public function canPrePopulate() {
			return true;
		}

		public function isSortable() {
			return true;
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $errors) {
			parent::displaySettingsPanel($wrapper, $errors);

			$document = $wrapper->ownerDocument;

		/*---------------------------------------------------------------------
			Options
		---------------------------------------------------------------------*/

			$options_list = $document->createElement('ul');
			$options_list->setAttribute('class', 'options-list');

			$this->appendShowColumnCheckbox($options_list);

			$wrapper->appendChild($options_list);
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry=NULL, $data=NULL) {
			$document = $wrapper->ownerDocument;
			$sortorder = $this->{'sortorder'};
			$element_name = $this->{'element-name'};

			$label = Widget::Label(
				(isset($this->{'publish-label'}) && strlen(trim($this->{'publish-label'})) > 0
					? $this->{'publish-label'}
					: $this->name)
			);

			// Input box:
			$input = Widget::Input("fields[$element_name]", $data->value);
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
			if (!isset($data->value) or strlen(trim($data->value)) == 0) {
				$errors->append(
					null, (object)array(
					 	'message' => __("'%s' is a required field.", array($this->{'name'})),
						'code' => self::ERROR_MISSING
					)
				);

				return self::STATUS_ERROR;
			}

			if (!preg_match('/^\w(?:\.?[\w%+-]+)*@\w(?:[\w-]*\.)+?[a-z]{2,}$/i', $data->value)) {
				$errors->append(
					null, (object)array(
					 	'message' => __("'%s' must be a valid email address.", array($this->{'name'})),
						'code' => self::ERROR_INVALID
					)
				);

				return self::STATUS_ERROR;
			}

			$result = Symphony::Database()->query(
				"
					SELECT COUNT(*) as `count`
					FROM `tbl_data_%s_%s`
					WHERE
						`value` = '%s'
						AND `entry_id` != %d
				",
				array(
					$entry->section,
					$this->{'element-name'},
					$data->value,
					$entry->id
				)
			);

			if((int)$result->current()->count != 0) {
				$errors->append(
					null, (object)array(
					 	'message' => __("'%s' must be unique.", array($this->{'name'})),
						'code' => self::ERROR_INVALID
					)
				);

				return self::STATUS_ERROR;
			}

			return self::STATUS_OK;
		}

		public function processData($data, Entry $entry = null) {
			$driver = Extension::load('members');

			if (isset($entry->data()->{$this->{'element-name'}})) {
				$result = $entry->data()->{$this->{'element-name'}};
			}

			else {
				$result = (object)array(
					'value'				=> null
				);
			}

			if (!is_null($data)) {
				$result->value = $data;
			}

			return $result;
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $mode = null) {
			$driver = Extension::load('members');
			$result = $wrapper->ownerDocument->createElement($this->{'element-name'});
			$value = $driver->repairEntities(trim($data->value));
			
			if ($value) {
				$fragment = $wrapper->ownerDocument->createDocumentFragment();
				$fragment->appendXML($value);
				$result->appendChild($fragment);
			}
			
			$wrapper->appendChild($result);
		}

		public function getParameterOutputValue(StdClass $data, Entry $entry = null) {
			return $data->handle;
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildFilterQuery($filter, &$joins, array &$where, Register $parameter_output) {
			$filter = $this->processFilter($filter);
			$filter_join = DataSource::FILTER_OR;
			$db = Symphony::Database();

			$values = DataSource::prepareFilterValue($filter->value, $parameter_output, $filter_join);

			if (!is_array($values)) $values = array();

			// Exact matches:
			if ($filter->type == 'is' or $filter->type == 'is-not') {
				$statements = array();

				if ($filter_join == DataSource::FILTER_OR) {
					$handle = $this->buildFilterJoin($joins);
				}

				foreach ($values as $index => $value) {
					if ($filter_join != DataSource::FILTER_OR) {
						$handle = $this->buildFilterJoin($joins);
					}

					$statements[] = $db->prepareQuery(
						"{$handle}.value = '%s'", array($value)
					);
				}

				if (empty($statements)) return true;

				if ($filter_join == DataSource::FILTER_OR) {
					$statement = "(\n\t" . implode("\n\tOR ", $statements) . "\n)";
				}

				else {
					$statement = "(\n\t" . implode("\n\tAND ", $statements) . "\n)";
				}

				if ($filter->type == 'is-not') {
					$statement = 'NOT ' . $statement;
				}

				$where[] = $statement;
			}

			return true;
		}
	}

	return 'FieldMemberEmail';
