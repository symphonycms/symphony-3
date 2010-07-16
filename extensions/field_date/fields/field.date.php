<?php

	Class fieldDate extends Field{

		const SIMPLE = 0;
		const REGEXP = 1;
		const RANGE = 3;
		const ERROR = 4;
		
		protected $join_handle;

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

		protected static function __isValidDateString($string){
			$string = trim($string);
			
			if(empty($string)) return false;

			$timestamp = strtotime($string);
			if($timestamp === false) return false;

			## Its not a valid date, so just return it as is
			if(!$info = getdate($timestamp)) return false;
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
				$value = DateTimeObj::get(__SYM_DATETIME_FORMAT__, $timestamp);
			}

			$label = Widget::Label(
				(isset($this->{'publish-label'}) && strlen(trim($this->{'publish-label'})) > 0 
					? $this->{'publish-label'} 
					: $this->name),
				Widget::Input("fields[{$name}]", $value), array(
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
				
				$result->value = NULL;
				
				if ($this->{'pre-populate'} == 'yes') {
					$timestamp = strtotime(DateTimeObj::get('c', null));
				}
			}
			
			else{
				$timestamp = strtotime($data);
			}

			if(!is_null($timestamp) && $timestamp !== false){
				$result->value = DateTimeObj::getGMT('Y-m-d H:i:s', $timestamp);
			}
			
			else{
				$result->value = $data;
			}
			
			return $result;
		}

		public function validateData(MessageStack $errors, Entry $entry = null, $data = null) {

			if(empty($data)) return self::STATUS_OK;

			if(self::STATUS_OK != parent::validateData($errors, $entry, $data)) {
				return self::STATUS_ERROR;
			}

			if(!is_null($data->value) && strlen(trim($data->value)) > 0 && !self::__isValidDateString($data->value)){
				$errors->append(
					null, (object)array(
					 	'message' => __("The date specified in '%s' is invalid.", array($this->label)),
						'code' => self::ERROR_INVALID
					)
				);
				
				return self::STATUS_ERROR;
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
		
		public function getParameterOutputValue(StdClass $data, Entry $entry=NULL){
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

		public function buildFilterQuery($filter, &$joins, array &$where, Register $parameter_output = null) {
			$filter = $this->processFilter($filter);
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
			
			if (empty($this->last_handle)) {
				$this->join_handle = $this->buildFilterJoin($joins);
			}
			
			$handle = $this->join_handle;
			
			$value = DataSource::replaceParametersInString(
				trim($filter->value), $parameter_output
			);
			$value = DateTimeObj::toGMT($value);
			
			$statements[] = $db->prepareQuery(
				"%d {$operator} UNIX_TIMESTAMP({$handle}.value)",
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