<?php

	require_once(TOOLKIT . '/class.textformatter.php');

	Class FieldException extends Exception {}

	Class FieldFilterIterator extends FilterIterator{
		public function __construct($path){
			parent::__construct(new DirectoryIterator($path));
		}

		public function accept(){
			if($this->isDir() == false && preg_match('/^field\..+\.php$/i', $this->getFilename())){
				return true;
			}
			return false;
		}
	}

	Class FieldIterator implements Iterator{

		private $position;
		private $fields;

		public function __construct(){

			$this->fields = array();
			$this->position = 0;

			foreach(new DirectoryIterator(EXTENSIONS) as $dir){
				if(!$dir->isDir() || $dir->isDot() || !is_dir($dir->getPathname() . '/fields')) continue;

				foreach(new FieldFilterIterator($dir->getPathname() . '/fields') as $file){
					$this->fields[] = $file->getPathname();
				}
			}

		}

		public function length(){
			return count($this->fields);
		}

		public function rewind(){
			$this->position = 0;
		}

		public function current(){
			return $this->fields[$this->position]; //Datasource::loadFromPath($this->events[$this->position]);
		}

		public function key(){
			return $this->position;
		}

		public function next(){
			++$this->position;
		}

		public function valid(){
			return isset($this->fields[$this->position]);
		}
	}


	Abstract Class Field{

		protected static $key;
		protected static $loaded;

		protected $properties;

		protected $_handle;
		protected $_name;

		// Status codes
		const STATUS_OK = 'ok';
		const STATUS_ERROR = 'error';

		// Error codes
		const ERROR_MISSING = 'missing';
		const ERROR_INVALID = 'invalid';
		const ERROR_DUPLICATE = 'duplicate';
		const ERROR_CUSTOM = 'custom';
		const ERROR_INVALID_QNAME = 'invalid qname';

		// Filtering Flags
		const FLAG_TOGGLEABLE = 'toggeable';
		const FLAG_UNTOGGLEABLE = 'untoggleable';
		const FLAG_FILTERABLE = 'filterable';
		const FLAG_UNFILTERABLE = 'unfilterable';
		const FLAG_ALL = 'all';

		public function __construct(){
			if(is_null(self::$key)) self::$key = 0;

			$this->properties = new StdClass;

			$this->{'required'} = 'no';
			$this->{'show-column'} = 'yes';

			$this->_handle = (strtolower(get_class($this)) == 'field' ? 'field' : strtolower(substr(get_class($this), 5)));
		}

		public function __isset($name){
			return isset($this->properties->$name);
		}

		public function __get($name){

			if($name == 'element-name'){
				$this->{'element-name'} = Lang::createHandle($this->properties->label, '-', false, true, array('/^[^:_a-z]+/i' => NULL, '/[^:_a-z0-9\.-]/i' => NULL));
			}

			else if ($name == 'guid' and !isset($this->guid)) {
				$this->guid = Field::createGUID($this);
			}
			
			if (!isset($this->properties->$name)) {
				return null;
			}
			
			return $this->properties->$name;
		}

		public function __set($name, $value){
			$this->properties->$name = $value;
		}

		public function __clone(){
			$this->properties = new StdClass;
		}

		public function handle(){
			return $this->_handle;
		}

		public function name(){
			return ($this->_name ? $this->_name : $this->_handle);
		}

		public function allowDatasourceOutputGrouping(){
			return false;
		}

		public function allowDatasourceParamOutput(){
			return false;
		}

		public function mustBeUnique(){
			return false;
		}

		public function canFilter(){
			return false;
		}

		public function canImport(){
			return false;
		}

		public function canPrePopulate(){
			return false;
		}

		public function isSortable(){
			return false;
		}

		public function requiresSQLGrouping(){
			return false;
		}

		public function canToggleData(){
			return false;
		}

		public function getToggleStates(){
			return array();
		}

		public function update(Field $old) {
			return true;
		}

		public function fetchIncludableElements(){
			return array($this->{'element-name'});
		}

		/*-------------------------------------------------------------------------
			Database Statements:
		-------------------------------------------------------------------------*/

		public function create(){
			return Symphony::Database()->query(
				'
					CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`entry_id` int(11) unsigned NOT NULL,
						`handle` varchar(255) default NULL,
						`value` varchar(255) default NULL,
						PRIMARY KEY  (`id`),
						KEY `entry_id` (`entry_id`),
						KEY `value` (`value`)
					)
				',
				array($this->section, $this->{'element-name'})
			);
		}

		public function remove() {
			try {
				Symphony::Database()->query(
					'
						DROP TABLE
							`tbl_data_%s_%s`
					',
					array($this->section, $this->{'element-name'})
				);
			}

			catch (Exception $e) {
				return false;
			}

			return true;
		}

		public function rename(Field $old) {
			try {
				Symphony::Database()->query(
					'
						ALTER TABLE
							`tbl_data_%s_%s`
						RENAME TO
							`tbl_data_%s_%s`
					',
					array(
						$old->section,
						$old->{'element-name'},
						$this->section,
						$this->{'element-name'}
					)
				);
			}

			catch (Exception $e) {
				return false;
			}

			return true;
		}

		/*-------------------------------------------------------------------------
			Load:
		-------------------------------------------------------------------------*/

		public static function load($pathname){
			if(!is_array(self::$loaded)){
				self::$loaded = array();
			}

			if(!is_file($pathname)){
		        throw new FieldException(
					__('Could not find Field <code>%s</code>. If the Field was provided by an Extension, ensure that it is installed, and enabled.', array(basename($pathname)))
				);
			}

			if(!isset(self::$loaded[$pathname])){
				self::$loaded[$pathname] = require($pathname);
			}

			$obj = new self::$loaded[$pathname];
			$obj->type = preg_replace('%^field\.|\.php$%', '', basename($pathname));

			return $obj;
		}

		public static function loadFromType($type){
			return self::load(self::__find($type) . "/field.{$type}.php");
		}

		public static function loadFromXMLDefinition(SimpleXMLElement $xml){
			if(!isset($xml->type)){
				throw new FieldException('Section XML contains fields with no type specified.');
			}

			$field = self::loadFromType((string)$xml->type);
			$field->loadSettingsFromSimpleXMLObject($xml);

			return $field;
		}

		protected static function __find($type){

			$extensions = ExtensionManager::instance()->listInstalledHandles();

			if(is_array($extensions) && !empty($extensions)){
				foreach($extensions as $e){
					if(is_file(EXTENSIONS . "/{$e}/fields/field.{$type}.php")) return EXTENSIONS . "/{$e}/fields";
				}
			}
		    return false;
	    }

		/*-------------------------------------------------------------------------
			Utilities:
		-------------------------------------------------------------------------*/

		public static function createGUID(Field $field) {
			return uniqid();
		}

		public function cleanValue($value) {
			return html_entity_decode(Symphony::Database()->escape($value));
		}

		public function __toString(){
			$doc = $this->toDoc();

			return $doc->saveXML($doc->documentElement);
		}

		public function toDoc() {
			$doc = new XMLDocument;

			$root = $doc->createElement('field');
			$root->setAttribute('guid', $this->guid);

			foreach ($this->properties as $name => $value) {
				if ($name == 'guid') continue;

				$element = $doc->createElement($name);
				$element->setValue($value);

				$root->appendChild($element);
			}

			$doc->appendChild($root);
			return $doc;
	    }

		/*-------------------------------------------------------------------------
			Settings:
		-------------------------------------------------------------------------*/

		public function loadSettingsFromSimpleXMLObject(SimpleXMLElement $xml){
			foreach($xml as $property_name => $property_value){
				$data[(string)$property_name] = (string)$property_value;
			}

			// Set field GUID:
			if (isset($xml->attributes()->guid) and trim((string)$xml->attributes()->guid) != '') {
				$data['guid'] = (string)$xml->attributes()->guid;
			}

			$this->setPropertiesFromPostData($data);
		}

		// TODO: Rethink this function
		public function findDefaultSettings(array &$fields){}

		public function validateSettings(MessageStack $messages, $checkForDuplicates = true) {
			$parent_section = $this->{'parent-section'};

			if ($this->label == '') {
				$messages->append('label', __('This is a required field.'));
			}

			if ($this->{'element-name'} == '') {
				$messages->append('element-name', __('This is a required field.'));
			}

			else if(!preg_match('/^[A-z]([\w\d-_\.]+)?$/i', $this->{'element-name'})) {
				$messages->append('element-name', __('Invalid element name. Must be valid QName.'));
			}

			/*
			TODO: Replace this with something:
			else if($checkForDuplicates) {
				$sql_id = ($this->id ? " AND f.id != '".$this->id."' " : '');

				$query = sprintf("
						SELECT
							f.*
						FROM
							`tbl_fields` AS f
						WHERE
							f.element_name = '%s'
							%s
							AND f.parent_section = '%s'
						LIMIT
							1
					",
					$element_name,
					$sql_id,
					$parent_section
				);

				if (Symphony::Database()->query($query)->valid()) {
					$messages->append("field::{$index}::element-name", __('A field with that element name already exists. Please choose another.'));
				}
			}
			*/

			if ($messages->length() > 0) {
				return Field::STATUS_ERROR;
			}

			return Field::STATUS_OK;
		}

		public function displaySettingsPanel(SymphonyDOMElement &$wrapper, MessageStack $messages){
			$document = $wrapper->ownerDocument;

			if ($this->label) {
				$name = $document->createElement('span', $this->label);
				$name->appendChild($document->createElement('i', $this->name()));
			}

			else {
				$name = $document->createElement('span', $this->name());
			}

			$name->setAttribute('class', 'name');
			$wrapper->appendChild($name);

			$label = Widget::Label(__('Label'));
			$label->setAttribute('class', 'field-label');
			$label->appendChild(Widget::Input('label', $this->label));

			if ($messages->{'label'}) {
				$label = Widget::wrapFormElementWithError($label, $messages->{'label'});
			}

			$wrapper->appendChild($label);

			if (isset($this->guid)) {
				$wrapper->appendChild(Widget::Input('guid', $this->guid, 'hidden'));
			}

			$wrapper->appendChild(Widget::Input('type', $this->type, 'hidden'));
		}

		public function setPropertiesFromPostData($data) {
			$data['required'] = (isset($data['required']) && $data['required'] == 'yes' ? 'yes' : 'no');
			$data['show-column'] = (isset($data['show-column']) && $data['show-column'] == 'yes' ? 'yes' : 'no');
			foreach($data as $key => $value){
				$this->$key = $value;
			}
		}


		public function appendRequiredCheckbox(SymphonyDOMElement $wrapper) {
			$document = $wrapper->ownerDocument;
			$item = $document->createElement('li');
			$item->appendChild(Widget::Input('required', 'no', 'hidden'));

			$label = Widget::Label(__('Make this a required field'));
			$input = Widget::Input('required', 'yes', 'checkbox');

			if ($this->required == 'yes') {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$item->appendChild($label);
			$wrapper->appendChild($item);
		}

		public function appendShowColumnCheckbox(SymphonyDOMElement $wrapper) {
			$document = $wrapper->ownerDocument;
			$item = $document->createElement('li');
			$item->appendChild(Widget::Input('show-column', 'no', 'hidden'));

			$label = Widget::Label(__('Show column'));
			$label->setAttribute('class', 'meta');
			$input = Widget::Input('show-column', 'yes', 'checkbox');

			if ($this->{'show-column'} == 'yes') $input->setAttribute('checked', 'checked');

			$label->prependChild($input);
			$item->appendChild($label);
			$wrapper->appendChild($item);
		}

		public function appendFormatterSelect(SymphonyDOMElement $wrapper, $selected=NULL, $name='fields[format]', $label_value = null){
			require_once(TOOLKIT . '/class.textformatter.php');

			if (!$label_value) $label_value = __('Text Formatter');

			$label = Widget::Label($label_value);
			$document = $wrapper->ownerDocument;
			$options = array();

			$options[] = array(NULL, false, __('None'));

			$iterator = new TextFormatterIterator;
			if($iterator->length() > 0){
				foreach($iterator as $pathname) {
					$handle = TextFormatter::getHandleFromFilename(basename($pathname));
					$tf = TextFormatter::load($pathname);

					$options[] = array($handle, ($selected == $handle), constant(sprintf('%s::NAME', get_class($tf))));
				}
			}

			$label->appendChild(Widget::Select($name, $options));
			$wrapper->appendChild($label);
		}

		public function appendValidationSelect(SymphonyDOMElement $wrapper, $selected=NULL, $name='fields[validator]', $label_value = null, $type='input'){
			include(TOOLKIT . '/util.validators.php');

			if (!$label_value) $label_value = __('Validation Rule');

			$label = Widget::Label($label_value);
			$document = $wrapper->ownerDocument;
			$rules = ($type == 'upload' ? $upload : $validators);

			$label->setValue($document->createElement('i', __('Optional')));
			$label->appendChild(Widget::Input($name, $selected));
			$wrapper->appendChild($label);

			$ul = $document->createElement('ul', NULL, array('class' => 'tags singular'));

			foreach($rules as $name => $rule) $ul->appendChild(
				$document->createElement('li', $name, array('class' => $rule))
			);

			$wrapper->appendChild($ul);
		}


		/*-------------------------------------------------------------------------
			Publish:
		-------------------------------------------------------------------------*/

		public function prepareTableValue(StdClass $data, DOMElement $link=NULL) {
			$max_length =Symphony::Configuration()->core()->symphony->cell-truncation-length;
			$max_length = ($max_length ? $max_length : 75);

			$value = strip_tags($data->value);
			
			if ($max_length < strlen($value)) {
				$lines = explode("\n", wordwrap($value, $max_length - 1, "\n"));
				$value = array_shift($lines);
				$value = rtrim($value, "\n\t !?.,:;");
				$value .= '…';
			}
			
			if ($max_length > 75) {
				$value = wordwrap($value, 75, '<br />');
			}
			
			if (strlen($value) == 0) $value = __('None');
			
			if (!is_null($link)) {
				$link->setValue($value);
				
				return $link;
			}

			return $value;
		}

		abstract public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $error, Entry $entry = null, $data = null);
		/*-------------------------------------------------------------------------
			Input:
		-------------------------------------------------------------------------*/

		public function loadDataFromDatabase(Entry $entry, $expect_multiple = false){
			try{
				$rows = Symphony::Database()->query(
					"SELECT * FROM `tbl_data_%s_%s` WHERE `entry_id` = %s ORDER BY `id` ASC",
					array(
						$entry->section,
						$this->{'element-name'},
						$entry->id
					)
				);

				if(!$expect_multiple) return $rows->current();

				$result = array();
				foreach($rows as $r){
					$result[] = $r;
				}

				return $result;
			}
			catch(DatabaseException $e){
				// Oh oh....no data. oh well, have a smoke and then return
			}
		}

		public function processData($data, Entry $entry=NULL){

			if(isset($entry->data()->{$this->{'element-name'}})){
				$result = $entry->data()->{$this->{'element-name'}};
			}

			else {
				$result = (object)array(
					'value' => NULL
				);
			}

			$result->value = $data;

			return $result;
		}

		public function validateData(MessageStack $errors, Entry $entry=NULL, $data=NULL){
			if ($this->required == 'yes' && (!isset($data->value) || strlen(trim($data->value)) == 0)){
				$errors->append(
					null, (object)array(
					 	'message' => __("'%s' is a required field.", array($this->label)),
						'code' => self::ERROR_MISSING
					)
				);
				return self::STATUS_ERROR;
			}
			return self::STATUS_OK;
		}

		// TODO: Support an array of data objects. This is important for
		// fields like Select box or anything that allows mutliple values
		public function saveData(MessageStack $errors, Entry $entry, $data = null) {
			$data->entry_id = $entry->id;
			if(!isset($data->id)) $data->id = NULL;
			
			try{
				Symphony::Database()->insert(
					sprintf('tbl_data_%s_%s', $entry->section, $this->{'element-name'}),
					(array)$data,
					Database::UPDATE_ON_DUPLICATE
				);
				return self::STATUS_OK;
			}
			catch(DatabaseException $e){

			}
			catch(Exception $e){

			}
			return self::STATUS_ERROR;
		}

		/*-------------------------------------------------------------------------
			Output:
		-------------------------------------------------------------------------*/

		public function appendFormattedElement(DOMElement $wrapper, $data, $encode=false, $mode=NULL, $entry_id=NULL) {
			$wrapper->appendChild(
				$wrapper->ownerDocument->createElement(
					$this->{'element-name'},
					($encode ? General::sanitize($this->prepareTableValue($data)) : $this->prepareTableValue($data))
				)
			);
		}

		public function getParameterPoolValue($data){
			return $this->prepareTableValue($data);
		}
		
		/*-------------------------------------------------------------------------
			Filtering:
		-------------------------------------------------------------------------*/

		public function getFilterTypes($data) {
			return array(
				array('is', false, 'Is'),
				array('is-not', $data->type == 'is-not', 'Is not'),
				array('contains', $data->type == 'contains', 'Contains'),
				array('does-not-contain', $data->type == 'does-not-contain', 'Does not Contain'),
				array('regex-search', $data->type == 'regex-search', 'Regex Search')
			);
		}
		
		public function processFilter($data) {
			$defaults = (object)array(
				'value'		=> '',
				'type'		=> 'is'
			);

			if (empty($data)) {
				$data = $defaults;
			}

			$data = (object)$data;

			if (!isset($data->type)) {
				$data->type = $defaults->type;
			}

			if (!isset($data->value)) {
				$data->value = '';
			}

			return $data;
		}

		public function displayDatasourceFilterPanel(SymphonyDOMElement &$wrapper, $data=NULL, MessageStack $errors=NULL){
			$data = $this->processFilter($data);
			$document = $wrapper->ownerDocument;

			$name = $document->createElement('span', $this->label);
			$name->setAttribute('class', 'name');
			$name->appendChild($document->createElement('em', $this->name()));
			$wrapper->appendChild($name);

			$type_label = Widget::Label(__('Type'));
			$type_label->setAttribute('class', 'small');
			$type_label->appendChild(Widget::Select(
				sprintf('fields[filters][%s][type]', $this->{'element-name'}),
				$this->getFilterTypes($data)
			));
			$wrapper->appendChild($type_label);

			$label = Widget::Label(__('Value'));
			$label->appendChild(Widget::Input(
				sprintf('fields[filters][%s][value]', $this->{'element-name'}),
				$data->value
			));

			$wrapper->appendChild(Widget::Group(
				$type_label, $label
			));
		}
		
		public function buildJoinQuery(&$joins) {
			$db = Symphony::Database();
			
			$table = $db->prepareQuery(sprintf(
				'tbl_data_%s_%s', $this->section, $this->{'element-name'}, ++self::$key
			));
			$handle = sprintf(
				'data_%s_%s_%d', $this->section, $this->{'element-name'}, self::$key
			);
			$joins .= sprintf(
				"\nLEFT OUTER JOIN `%s` AS %s ON (e.id = %2\$s.entry_id)",
				$table, $handle
			);
			
			return $handle;
		}
		
		public function buildFilterJoin(&$joins) {
			return $this->buildJoinQuery($joins);
		}
		
		public function buildFilterQuery($filter, &$joins, &$where, Register $parameter_output) {
			$filter = $this->processFilter($filter);
			$filter_join = DataSource::FILTER_OR;
			$db = Symphony::Database();
			
			// Exact matches:
			if ($filter->type == 'is' or $filter->type == 'is-not') {
				$values = DataSource::prepareFilterValue($filter->value, $parameter_output, $filter_join);
				$statements = array();
				
				if (!is_array($values)) $values = array();
				
				if ($filter_join == DataSource::FILTER_OR) {
					$handle = $this->buildFilterJoin($joins);
				}
				
				foreach ($values as $index => $value) {
					if ($filter_join != DataSource::FILTER_OR) {
						$handle = $this->buildFilterJoin($joins);
					}
					
					$statements[] = $db->prepareQuery(
						"'%s' IN ({$handle}.value, {$handle}.handle)", array($value)
					);
				}
				
				if ($filter_join == DataSource::FILTER_OR) {
					$statement = "(\n\t" . implode("\n\tOR ", $statements) . "\n)";
				}
				
				else {
					$statement = "(\n\t" . implode("\n\tAND ", $statements) . "\n)";
				}
				
				if ($filter->type == 'is-not') {
					$statement = 'NOT ' . $statement;
				}
				
				$where .= 'AND ' . $statement;
			}
			
			else if ($filter->type == 'contains' or $filter->type == 'does-not-contain') {
				$handle = $this->buildFilterJoin($joins);
				$value = '%' . $filter->value . '%';
				$statements = array(
					$db->prepareQuery("{$handle}.value LIKE '%s'", array($value)),
					$db->prepareQuery("{$handle}.handle LIKE '%s'", array($value))
				);
				
				$statement = "(\n\t" . implode("\n\tOR ", $statements) . "\n)";
				
				if ($filter->type == 'does-not-contain') {
					$statement = 'NOT ' . $statement;
				}
				
				$where .= 'AND ' . $statement;
			}
			
			// Regex search:
			else if ($filter->type == 'regex-search') {
				$handle = $this->buildFilterJoin($joins);
				$value = trim($filter->value);
				$statements = array(
					$db->prepareQuery("{$handle}.value REGEXP '%s'", array($value)),
					$db->prepareQuery("{$handle}.handle REGEXP '%s'", array($value))
				);
				
				$where .= "AND (\n\t" . implode("\n\tOR ", $statements) . "\n)";
			}
			
			return true;
		}
		
		public function buildDSFilterSQL() {
			// TODO: Cleanup before release.
			throw new Exception('Field->buildDSFilterSQL() is obsolete, use buildFilterQuery instead.');
		}

		/*-------------------------------------------------------------------------
			Grouping:
		-------------------------------------------------------------------------*/
		
		public function groupRecords($records){
			throw new FieldException(
				__('Data source output grouping is not supported by the <code>%s</code> field', array($this->handle))
			);
		}

		/*-------------------------------------------------------------------------
			Sorting:
		-------------------------------------------------------------------------*/
		
		public function buildSortingJoin(&$joins) {
			return $this->buildJoinQuery($joins);
		}

		public function buildSortingQuery(&$joins, &$order){
			$handle = $this->buildSortingJoin($joins);
			$order = "{$handle}.value %1\$s";
		}
		
		public function buildSortingSQL() {
			// TODO: Cleanup before release.
			throw new Exception('Field->buildSortingSQL() is obsolete, use buildSortingQuery instead.');
		}
		
		/*-------------------------------------------------------------------------
			Deprecated:
		-------------------------------------------------------------------------

		public function entryDataCleanup($entry_id, $data=NULL){
			Symphony::Database()->delete('tbl_entries_data_' . $this->id, array($entry_id), "`entry_id` = %d ");

			return true;
		}

		public function removeSectionAssociation($child_field_id){
			Symphony::Database()->delete("tbl_sections_association", array($child_field_id), "`child_section_field_id` = %d");
		}

		public function createSectionAssociation($parent_section_id, $child_field_id, $parent_field_id=NULL, $cascading_deletion=false){

			if($parent_section_id == NULL && !$parent_field_id) return false;

			if($parent_section_id == NULL) $parent_section_id = Symphony::Database()->fetchVar('parent_section', 0, "SELECT `parent_section` FROM `tbl_fields` WHERE `id` = '$parent_field_id' LIMIT 1");

			$child_section_id = Symphony::Database()->fetchVar('parent_section', 0, "SELECT `parent_section` FROM `tbl_fields` WHERE `id` = '$child_field_id' LIMIT 1");

			$fields = array('parent_section_id' => $parent_section_id,
							'parent_section_field_id' => $parent_field_id,
							'child_section_id' => $child_section_id,
							'child_section_field_id' => $child_field_id,
							'cascading_deletion' => ($cascading_deletion ? 'yes' : 'no'));

			if(!Symphony::Database()->insert('tbl_sections_association', $fields)) return false;

			return true;
		}

		public function getExampleFormMarkup(){
			$label = Widget::Label($this->label);
			$label->appendChild(Widget::Input('fields['.$this->{'element-name'}.']'));

			return $label;
		}

		public function fetchAssociatedEntrySearchValue($data, $field_id=NULL, $parent_entry_id=NULL){
			return $data;
		}

		public function fetchAssociatedEntryCount($value){
		}

		public function fetchAssociatedEntryIDs($value){
		}

		protected static function isFilterRegex($string){
			if(preg_match('/^regexp:/i', $string)) return true;
		}
*/
	}
