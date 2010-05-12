<?php

	Class fieldDate extends Field{

		const SIMPLE = 0;
		const REGEXP = 1;
		const RANGE = 3;
		const ERROR = 4;

		function __construct(){
			parent::__construct();
			$this->_name = __('Date');
		}

		public function create(){
			return Symphony::Database()->query(
				sprintf(
					'CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`entry_id` int(11) unsigned NOT NULL,
						`value` DATETIME default NULL,
						PRIMARY KEY  (`id`),
						KEY `entry_id` (`entry_id`),
						KEY `value` (`value`)
					)',
					$this->section,
					$this->{'element-name'}
				)
			);
		}

		function allowDatasourceOutputGrouping(){
			return true;
		}

		function allowDatasourceParamOutput(){
			return true;
		}

		function canFilter(){
			return true;
		}

		public function canImport(){
			return true;
		}

		function isSortable(){
			return true;
		}

		/*-------------------------------------------------------------------------
			Utilities:
		-------------------------------------------------------------------------*/

		protected function __buildSimpleFilterSQL($filter, &$joins, &$where, $operation_type=DataSource::FILTER_OR) {
			/* 	TODO: Fix Simple SQL
				SPRINTF EATS UP %d
				%%d% seems to make no difference

			foreach($filter['value'] as $value) {
				$filter['value'] = DataSource::prepareFilterValue($value);
			}

			if ($operation_type == DataSource::FILTER_AND) {
				foreach ($value as $v) {
					$where .= sprintf(
						" AND DATE_FORMAT(t%1\$s.value, '%Y-%m-%%d %H:%i:%%s') %2\$s '%3\$s') ",
						self::$key,
						$filter['type'] == 'is-not' ? '<>' : '=',
						DateTimeObj::get('Y-m-d h:i:s', strtotime($v))
					);
				}
			}

			else {
				$parsed_values = array();
				foreach($value as $v) $parsed_values[] = DateTimeObj::get('Y-m-d h:i:s', strtotime($v));

				$where .= sprintf(
					" AND DATE_FORMAT(t%1\$s.value, '%Y-%m-%%d %H:%i:%%s') %2\$s IN ('%3\$s') ",
					self::$key,
					$filter['type'] == 'is-not' ? 'NOT' : NULL,
					implode("', '", $parsed_values)
				);
			}
			*/
		}

		protected function __buildRangeFilterSQL($filter, &$joins, &$where, $operation_type=DataSource::FILTER_OR) {
			/* 	TODO: Fix Range SQL
				SPRINTF EATS UP %d
				%%d% seems to make no difference


			foreach($filter['value'] as $key => $value) {
				$filter['value'][$key] = DataSource::prepareFilterValue($value);
			}

			if ($operation_type == DataSource::FILTER_AND) {
				foreach ($filter['value']['start'] as $k => $v) {

					$where .= sprintf(
						" AND (
							DATE_FORMAT(`t%1\$d.value, '%Y-%m-%d %H:%i:%s') >= '%2\$s'
						  	AND DATE_FORMAT(`t%1\$d.value, '%Y-%m-%d %H:%i:%s') <= '%3\$s'
						) ",
						self::$key,
						DateTimeObj::get('Y-m-d h:i:s', strtotime($v)),
						DateTimeObj::get('Y-m-d h:i:s', strtotime($filter['value']['end'][$k]))
					);
				}


			} else {

				$tmp = array();

				foreach ($filter['value']['start'] as $k => $v) {
					$tmp[] .= sprintf(
						" AND (
							DATE_FORMAT(`t%1$s.value, '%Y-%m-%d %H:%i:%s') >= '%s'
						  	AND DATE_FORMAT(`t%1$s.value, '%Y-%m-%d %H:%i:%s') <= '%s'
						) ",
						self::$key,
						DateTimeObj::get('Y-m-d h:i:s', strtotime($v)),
						DateTimeObj::get('Y-m-d h:i:s', strtotime($filter['value']['end'][$k]))
					);
				}

				$where .= " AND (" . implode(' OR ', $tmp). ") ";

			}
			*/

		}

		protected static function __cleanFilterString($string){
			$string = trim($string);
			$string = trim($string, '-/');

			return $string;
		}

		protected static function __parseFilter(&$string){
/*
			$string = self::__cleanFilterString($string);

			## Check its not a regexp
			if(preg_match('/^regexp:/i', $string)){
				$string = str_replace('regexp:', '', $string);
				return self::REGEXP;
			}

			## Look to see if its a shorthand date (year only), and convert to full date
			elseif(preg_match('/^(1|2)\d{3}$/i', $string)){
				$string = "$string-01-01 to $string-12-31";
			}

			## Human friendly terms
			elseif(preg_match('/^(equal to or )?(earlier|later) than (.*)$/i', $string, $match)){

				$string = $match[3];

				if(!self::__isValidDateString($string)) return self::ERROR;

				$time = strtotime($string);
				if($match[1] == "equal to or "){
					$later = DateTimeObj::get('Y-m-d H:i:s', $time);
					$earlier = $later;
				}
				else {
					$later = DateTimeObj::get('Y-m-d H:i:s', $time+1);
					$earlier = DateTimeObj::get('Y-m-d H:i:s', $time-1);
				}
				switch($match[2]){
					case 'later': $string = $later . ' to 2038-01-01'; break;
					case 'earlier': $string = '1970-01-03 to ' . $earlier; break;
				}

			}

			## Look to see if its a shorthand date (year and month), and convert to full date
			elseif(preg_match('/^(1|2)\d{3}[-\/]\d{1,2}$/i', $string)){

				$start = "{$string}-01";

				if(!self::__isValidDateString($start)) return self::ERROR;

				$string = "{$start} to {$string}-" . date('t', strtotime($start));
			}

			## Match for a simple date (Y-m-d), check its ok using checkdate() and go no further
			elseif(!preg_match('/to/i', $string)){

				if(preg_match('/^(1|2)\d{3}[-\/]\d{1,2}[-\/]\d{1,2}$/i', $string)){
					$string = "{$string} to {$string}";
				}

				else{
					if(!self::__isValidDateString($string)) return self::ERROR;

					$string = DateTimeObj::get('Y-m-d H:i:s', strtotime($string));
					return self::SIMPLE;
				}
			}

			## Parse the full date range and return an array

			if(!$parts = preg_split('/to/', $string, 2, PREG_SPLIT_NO_EMPTY)) return self::ERROR;

			$parts = array_map(array('self', '__cleanFilterString'), $parts);

			list($start, $end) = $parts;

			if(!self::__isValidDateString($start) || !self::__isValidDateString($end)) return self::ERROR;

			$string = array('start' => $start, 'end' => $end);

			return self::RANGE;
*/
		}

		protected static function __isValidDateString($string){
			$string = trim($string);

			if(empty($string)) return false;

			## Its not a valid date, so just return it as is
			if(!$info = getdate(strtotime($string))) return false;
			elseif(!checkdate($info['mon'], $info['mday'], $info['year'])) return false;

			return true;
		}

		/*-------------------------------------------------------------------------
			Settings:
		-------------------------------------------------------------------------*/

		public function findDefaultSettings(array &$fields){
			if(!isset($fields['pre-populate'])) $fields['pre-populate'] = 'yes';
		}

		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			$document = $wrapper->ownerDocument;

			$options_list = $document->createElement('ul');
			$options_list->setAttribute('class', 'options-list');

			$this->appendShowColumnCheckbox($options_list);
			$this->appendRequiredCheckbox($options_list);

			$label = Widget::Label(__('Pre-populate this field with today\'s date'));
			$input = Widget::Input('pre-populate', 'yes', 'checkbox');
			if($this->{'pre-populate'} == 'yes') $input->setAttribute('checked', 'checked');

			$label->prependChild($input);
			$item = $document->createElement('li');
			$item->appendChild($label);
			$options_list->appendChild($item);

			$wrapper->appendChild($options_list);

		}

		/*-------------------------------------------------------------------------
			Publish:
		-------------------------------------------------------------------------*/

		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry = null, $data = null){
			$name = $this->{'element-name'};
			$value = null;

			// New entry:
			if (is_null($data) && $this->{'pre-populate'} == 'yes') {
				$value = DateTimeObj::get(__SYM_DATETIME_FORMAT__, null);
			}

			// Empty entry:
			else if (isset($data->value) && !is_null($data->value)) {
				$timestamp = DateTimeObj::fromGMT($data->value);
				$value = DateTimeObj::get(__SYM_DATETIME_FORMAT__, $timestamp, 'Australia/Brisbane');
			}

			$label = Widget::Label($this->label, Widget::Input("fields[{$name}]", $value), array(
				'class' => 'date')
			);

			if ($errors->valid()){
				$label = Widget::wrapFormElementWithError($label, $errors->current()->message);
			}

			$wrapper->appendChild($label);
		}

		/*-------------------------------------------------------------------------
			Input:
		-------------------------------------------------------------------------*/

		public function processData($data, Entry $entry=NULL){

			if(isset($entry->data()->{$this->{'element-name'}})){
				$result = $entry->data()->{$this->{'element-name'}};
			}

			else {
				$result = (object)array(
					'value' => null
				);
			}
			
			if(is_null($data) || strlen(trim($data)) == 0){
				if ($this->{'pre-populate'} == 'yes') {
					$timestamp = strtotime(DateTimeObj::get('c', null));
				}
			}
			
			else{
				$timestamp = strtotime($data);
			}

			if(!is_null($timestamp)){
				$result->value = DateTimeObj::getGMT('Y-m-d H:i:s', $timestamp);
			}
			
			return $result;
		}

		public function validateData(MessageStack $errors, Entry $entry = null, $data = null) {

			if(empty($data)) return self::STATUS_OK;

			$message = NULL;

			if(!self::__isValidDateString($data->value)){
				$message = __("The date specified in '%s' is invalid.", array($this->label));
				return self::ERROR_INVALID;
			}

			return self::STATUS_OK;
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/
		
		public function prepareTableValue(StdClass $data, SymphonyDOMElement $link=NULL) {
			$value = null;
			
			if (isset($data->value) && !is_null($data->value)) {
				$timestamp = DateTimeObj::fromGMT($data->value);
				$value = DateTimeObj::get(__SYM_DATETIME_FORMAT__, $timestamp);
			}

			return parent::prepareTableValue((object)array('value' => $value), $link);
		}
		
		public function appendFormattedElement(&$wrapper, $data, $encode = false) {
			if (isset($data->value) && !is_null($data->value)) {
				$wrapper->appendChild(General::createXMLDateObject(
					$wrapper->ownerDocument, DateTimeObj::fromGMT($data->value), $this->{'element-name'}
				));
			}
		}
		
		public function getParameterPoolValue($data){
			$timestamp = DateTimeObj::fromGMT($data->value);
			
     		return DateTimeObj::get('Y-m-d H:i:s', $timestamp);
		}
		
	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function getFilterTypes($data) {
			return array(
				array('is', false, 'Is'),
				array('is-not', $data->type == 'is-not', 'Is not'),
				array('earlier-than', $data->type == 'earlier-than', 'Earlier than'),
				array('earlier-than-or-equal', $data->type == 'earlier-than-or-equal', 'Earlier than or equal'),
				array('later-than', $data->type == 'later-than', 'Later than'),
				array('later-than-or-equal', $data->type == 'later-than-or-equal', 'Later than or equal')
			);
		}

		//	TODO: Revisit this.
		//	Reason being that now that you don't actually enter 'date to date' and instead that would come
		//	across as two different filters, [later than] date & [earlier than] date. Therefore this code doesn't
		//	have to be as 'split here and do magic' as it is now.
		//	The problem then is that what is a simple BETWEEN statement now is a bunch of joins and >=/<= operads
		//	which is slow
		public function buildFilterQuery($filter, &$joins, array &$where, Register $parameter_output = null) {
			$filter = $this->processFilter($filter);
			$filter_join = DataSource::FILTER_OR;
			$values = DataSource::prepareFilterValue($filter->value, $parameter_output, $filter_join);
			$db = Symphony::Database();
			$statements = array();
			
			if (!is_array($values)) $values = array();
			
			// Exact matches:
			switch ($filter->type) {
				case 'is':						$operator = '='; break;
				case 'is-not':					$operator = '!='; break;
				case 'earlier-than':			$operator = '>'; break;
				case 'earlier-than-or-equal':	$operator = '>='; break;
				case 'later-than':				$operator = '<'; break;
				case 'later-than-or-equal':		$operator = '<='; break;
			}
			
			if ($filter_join == DataSource::FILTER_OR) {
				$handle = $this->buildFilterJoin($joins);
			}
			
			foreach ($values as $index => $value) {
				if ($filter_join != DataSource::FILTER_OR) {
					$handle = $this->buildFilterJoin($joins);
				}
				
				$value = DateTimeObj::toGMT($value);
				
				$statements[] = $db->prepareQuery(
					"%d {$operator} UNIX_TIMESTAMP({$handle}.value)",
					array($value)
				);
			}

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

		public function groupRecords($records){

			if(!is_array($records) || empty($records)) return;

			$groups = array('year' => array());

			foreach($records as $r){
				$data = $r->getData($this->id);

				$info = getdate($data['local']);

				$year = $info['year'];
				$month = ($info['mon'] < 10 ? '0' . $info['mon'] : $info['mon']);

				if(!isset($groups['year'][$year])) $groups['year'][$year] = array('attr' => array('value' => $year),
																				  'records' => array(),
																				  'groups' => array());

				if(!isset($groups['year'][$year]['groups']['month'])) $groups['year'][$year]['groups']['month'] = array();

				if(!isset($groups['year'][$year]['groups']['month'][$month])) $groups['year'][$year]['groups']['month'][$month] = array('attr' => array('value' => $month),
																				  					  'records' => array(),
																				  					  'groups' => array());


				$groups['year'][$year]['groups']['month'][$month]['records'][] = $r;

			}

			return $groups;

		}

	}

	return 'fieldDate';