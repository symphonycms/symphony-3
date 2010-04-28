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

		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry = null, $data = null){
			$name = $this->{'element-name'};
			$value = null;

			// New entry:
			if (is_null($data) && $this->{'pre-populate'} == 'yes') {
				$value = DateTimeObj::get(__SYM_DATETIME_FORMAT__, null);
			}

			// Empty entry:
			else if (isset($data->gmt) && !is_null($data->gmt)) {
				$value = DateTimeObj::get(__SYM_DATETIME_FORMAT__, $data->gmt);
			}

			$label = Widget::Label($this->label, Widget::Input("fields[{$name}]", $value), array(
				'class' => 'date')
			);

			if ($errors->valid()){
				$label = Widget::wrapFormElementWithError($label, $errors->current()->message);
			}

			$wrapper->appendChild($label);
		}

		public function checkPostFieldData($data, &$message, $entry_id=NULL){

			if(empty($data)) return self::STATUS_OK;

			$message = NULL;

			if(!self::__isValidDateString($data)){
				$message = __("The date specified in '%s' is invalid.", array($this->label));
				return self::ERROR_INVALID;
			}

			return self::STATUS_OK;
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			$status = self::STATUS_OK;
			$timestamp = null;

			if (is_null($data) || $data == '') {
				if ($this->{'pre-populate'} == 'yes') {
					$timestamp = strtotime(DateTimeObj::get(__SYM_DATETIME_FORMAT__, null));
				}
			}

			else  {
				$timestamp = strtotime($data);
			}

			if (!is_null($timestamp)) {
				return array(
					'value' => DateTimeObj::get('c', $timestamp),
					'local' => strtotime(DateTimeObj::get('c', $timestamp)),
					'gmt' => strtotime(DateTimeObj::getGMT('c', $timestamp))
				);
			}

			return array(
				'value'		=> null,
				'local'		=> null,
				'gmt'		=> null
			);
		}

		public function appendFormattedElement(&$wrapper, $data, $encode = false) {
			if (isset($data->gmt) && !is_null($data->gmt)) {
				$wrapper->appendChild(General::createXMLDateObject($wrapper->ownerDocument, $data->local, $this->{'element-name'}));
			}
		}

		public function prepareTableValue(StdClass $data, SymphonyDOMElement $link=NULL) {
			$value = null;

			if (isset($data->gmt) && !is_null($data->gmt)) {
				$value = DateTimeObj::get(__SYM_DATETIME_FORMAT__, $data->gmt);
			}

			return parent::prepareTableValue((object)array('value' => $value), $link);
		}

		public function getParameterPoolValue($data){
     		return DateTimeObj::get('Y-m-d H:i:s', $data->local);
		}

		function groupRecords($records){

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

		//	TODO: Revisit this.
		public function buildDSRetrivalSQL($filter, &$joins, &$where, $operation_type=DataSource::FILTER_OR) {
			
			self::$key++;

			$value = DataSource::prepareFilterValue($filter['value']);
			
			if(self::isFilterRegex($value)) return parent::buildDSRetrivalSQL($data, $joins, $where, $operation_type);			

			$joins .= sprintf('
				LEFT OUTER JOIN `tbl_data_%2$s_%3$s` AS t%1$s ON (e.id = t%1$s.entry_id)
			', self::$key, $this->section, $this->{'element-name'});

			if ($operation_type == DataSource::FILTER_AND) {
				foreach ($value as $v) {
					$where .= sprintf(
						" AND (t%1\$s.value %2\$s '%3\$s') ",
						self::$key,
						$filter['type'] == 'is-not' ? '<>' : '=',
						$v
					);
				}

			}

			else {
				$where .= sprintf(
					" AND (t%1\$s.value %2\$s IN ('%3\$s')) ",
					self::$key,
					$filter['type'] == 'is-not' ? 'NOT' : NULL,
					implode("', '", $value)
				);
			}
/*
			if(self::isFilterRegex($data[0])) return parent::buildDSRetrivalSQL($data, $joins, $where, $andOperation);

			$parsed = array();

			foreach($data as $string){
				$type = self::__parseFilter($string);

				if($type == self::ERROR) return false;

				if(!is_array($parsed[$type])) $parsed[$type] = array();

				$parsed[$type][] = $string;
			}

			foreach($parsed as $type => $value){

				switch($type){

					case self::RANGE:
						$this->__buildRangeFilterSQL($value, $joins, $where, $andOperation);
						break;

					case self::SIMPLE:
						$this->__buildSimpleFilterSQL($value, $joins, $where, $andOperation);
						break;

				}
			}
*/
			return true;
		}

		protected function __buildSimpleFilterSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->id;

			if($andOperation):

				foreach($data as $date){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id".self::$key."` ON `e`.`id` = `t$field_id".self::$key."`.entry_id ";
					$where .= " AND DATE_FORMAT(`t$field_id".self::$key."`.value, '%Y-%m-%d') = '".DateTimeObj::get('Y-m-d', strtotime($date))."' ";

					self::$key++;
				}

			else:

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id".self::$key."` ON `e`.`id` = `t$field_id".self::$key."`.entry_id ";
				$where .= " AND DATE_FORMAT(`t$field_id".self::$key."`.value, '%Y-%m-%d %H:%i:%s') IN ('".@implode("', '", $data)."') ";
				self::$key++;

			endif;


		}

		protected function __buildRangeFilterSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->id;

			if(empty($data)) return;

			if($andOperation):

				foreach($data as $date){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id".self::$key."` ON `e`.`id` = `t$field_id".self::$key."`.entry_id ";
					$where .= " AND (DATE_FORMAT(`t$field_id".self::$key."`.value, '%Y-%m-%d') >= '".DateTimeObj::get('Y-m-d', strtotime($date['start']))."'
								     AND DATE_FORMAT(`t$field_id".self::$key."`.value, '%Y-%m-%d') <= '".DateTimeObj::get('Y-m-d', strtotime($date['end']))."') ";

					self::$key++;
				}

			else:

				$tmp = array();

				foreach($data as $date){

					$tmp[] = "(DATE_FORMAT(`t$field_id".self::$key."`.value, '%Y-%m-%d') >= '".DateTimeObj::get('Y-m-d', strtotime($date['start']))."'
								     AND DATE_FORMAT(`t$field_id".self::$key."`.value, '%Y-%m-%d') <= '".DateTimeObj::get('Y-m-d', strtotime($date['end']))."') ";
				}

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id".self::$key."` ON `e`.`id` = `t$field_id".self::$key."`.entry_id ";
				$where .= " AND (".@implode(' OR ', $tmp).") ";

				self::$key++;

			endif;

		}

		protected static function __cleanFilterString($string){
			$string = trim($string);
			$string = trim($string, '-/');

			return $string;
		}

		protected static function __parseFilter(&$string){

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
		}

		protected static function __isValidDateString($string){

			$string = trim($string);

			if(empty($string)) return false;

			## Its not a valid date, so just return it as is
			if(!$info = getdate(strtotime($string))) return false;
			elseif(!checkdate($info['mon'], $info['mday'], $info['year'])) return false;

			return true;
		}


/*
		Deprecated
		function commit(){

			if(!parent::commit()) return false;

			$field_id = $this->id;
			$handle = $this->handle();

			if($field_id === false) return false;

			$fields = array(
				'field_id' => $field_id,
				'pre-populate' => ($this->{'pre-populate'} ? $this->{'pre-populate'} : 'no')
			);

			Symphony::Database()->delete('tbl_fields_' . $handle, array($field_id), "`field_id` = %d LIMIT 1");
			$field_id = Symphony::Database()->insert('tbl_fields_' . $handle, $fields);

			return ($field_id == 0 || !$field_id) ? false : true;
		}
*/
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

			$this->appendShowColumnCheckbox($options_list);

			$wrapper->appendChild($options_list);

		}

		public function create(){
			return Symphony::Database()->query(
				sprintf(
					'CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`entry_id` int(11) unsigned NOT NULL,
						`value` varchar(80) default NULL,
						`local` int(11) default NULL,
						`gmt` int(11) default NULL,
						PRIMARY KEY  (`id`),
						KEY `entry_id` (`entry_id`),
						KEY `value` (`value`)
					)',
					$this->section,
					$this->{'element-name'}
				)
			);
		}

		public function processFormData($data, Entry $entry=NULL){

			if(isset($entry->data()->{$this->{'element-name'}})){
				$result = $entry->data()->{$this->{'element-name'}};
			}

			else {
				$result = (object)array(
					'value' => null,
					'local' => null,
					'gmt' => null
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
				$result->value = DateTimeObj::get('c', $timestamp);
				$result->local = strtotime(DateTimeObj::get('c', $timestamp));
				$result->gmt = strtotime(DateTimeObj::getGMT('c', $timestamp));
			}

			return $result;
		}
	}

	return 'fieldDate';