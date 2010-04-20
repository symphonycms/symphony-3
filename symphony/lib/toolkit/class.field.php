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
		
		protected $_fields; //DEPRICATED
		protected $_required;
		protected $_showcolumn;
		
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
		
		// Abstract functions
		abstract public function displayPublishPanel(SymphonyDOMElement $wrapper, $data=NULL, $flagWithError=NULL, $entry_id=NULL);
		
		public function __construct(){
			if(is_null(self::$key)) self::$key = 0;
			
			$this->properties = new StdClass;

			$this->_required = false;
			$this->_showcolumn = true;

			$this->_handle = (strtolower(get_class($this)) == 'field' ? 'field' : strtolower(substr(get_class($this), 5)));

		}
		
		public function &properties(){
			return $this->properties;
		}
		
		public function __clone(){
			$this->properties = new StdClass;
		}
		
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
			return $obj;
		}

		public static function loadFromType($type){
			return self::load(self::__find($type) . "/field.{$type}.php");
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
		
		public function __toString(){

			/*
			Array
			(
			    [show_column] => yes
			    [required] => yes
			    [type] => textarea
			    [label] => Happy Days
			    [size] => 12
			    [formatter] => markdown_with_purifier
			    [element_name] => happy-days
			)
			*/

			$doc = new DOMDocument('1.0', 'UTF-8');
			$doc->formatOutput = true;

			$root = $doc->createElement('field');
			$doc->appendChild($root);

			//$root->appendChild($doc->createElement('name', General::sanitize($this->name)));
			foreach($this->properties() as $name => $value){
				$root->appendChild($doc->createElement($name, General::sanitize($value)));
			}

			return $doc->saveXML();
		}

		public function canShowTableColumn(){
			return $this->_showcolumn;
		}

		public function canToggle(){
			return false;
		}

		public function canFilter(){
			return false;
		}

		public function canImport(){
			return false;
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

		public function getToggleStates(){
			return array();
		}

		public function toggleFieldData($data, $newState){
			return $data;
		}

		public function handle(){
			return $this->_handle;
		}

		public function name(){
			return ($this->_name ? $this->_name : $this->_handle);
		}

		public function entryDataCleanup($entry_id, $data=NULL){
			Symphony::Database()->delete('tbl_entries_data_' . $this->properties()->id, array($entry_id), "`entry_id` = %d ");

			return true;
		}

		public function setFromPOST($data) {
			$data['required'] = (isset($data['required']) && $data['required'] == 'yes' ? 'yes' : 'no');
			$data['show-column'] = (isset($data['show-column']) && $data['show-column'] == 'yes' ? 'yes' : 'no');
			foreach($data as $key => $value){
				$this->properties()->$key = $value;
			}
		}
		
		// DEPRICATED
		/*public function get($field=NULL){

			if(is_null($field)){
				return $this->properties();
				//return $this->_fields;
			}

			if($field == 'element_name'
				&& (isset($this->properties()->label) && strlen(trim($this->properties()->label)) > 0)
					&& (!isset($this->properties()->$field) || strlen(trim($this->properties()->$field)) == 0)){
						$this->properties()->$field = Lang::createHandle(
							$this->properties()->label, '-', false, true, array('/^[^:_a-z]+/i' => NULL, '/[^:_a-z0-9\.-]/i' => NULL)
						);
			}

			return (isset($this->properties()->$field) ? $this->properties()->$field : NULL);
		}*/


		/*
		**	TODO: Section Association...
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
		*/

		

		public function canPrePopulate(){
			return false;
		}

		public function appendFormattedElement(DOMElement $wrapper, $data, $encode=false, $mode=NULL, $entry_id=NULL) {
			$wrapper->appendChild(
				Symphony::Parent()->Page->createElement(
					$this->properties()->{'element-name'},
					($encode ? General::sanitize($this->prepareTableValue($data)) : $this->prepareTableValue($data))
				)
			);
		}

		public function getParameterPoolValue($data){
			return $this->prepareTableValue($data);
		}

		public function cleanValue($value) {
			return html_entity_decode(Symphony::Database()->escape($value));
		}

		public function checkFields(&$errors, $checkForDuplicates = true) {
			$parent_section = $this->properties()->{'parent-section'};
			$element_name = $this->properties()->{'element-name'};

			//echo $this->properties()->id, ': ', $this->properties()->required, '<br />';

			if (!is_array($errors)) $errors = array();

			if ($this->properties()->label == '') {
				$errors['label'] = __('This is a required field.');
			}

			if ($this->properties()->{'element-name'} == '') {
				$errors['element_name'] = __('This is a required field.');

			} elseif (!preg_match('/^[A-z]([\w\d-_\.]+)?$/i', $this->properties()->{'element-name'})) {
				$errors['element_name'] = __('Invalid element name. Must be valid QName.');

			} elseif($checkForDuplicates) {
				$sql_id = ($this->properties()->id ? " AND f.id != '".$this->properties()->id."' " : '');

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
					$errors['element_name'] = __('A field with that element name already exists. Please choose another.');
				}
			}

			return (is_array($errors) && !empty($errors) ? self::STATUS_ERROR : self::STATUS_OK);
		}
		
		
		// TODO: Rethink this function
		public function findDefaults(array &$fields){
		}

		public function isSortable(){
			return false;
		}

		public function requiresSQLGrouping(){
			return false;
		}

		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
			$joins .= "LEFT OUTER JOIN `tbl_entries_data_".$this->properties()->id."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort = 'ORDER BY ' . (in_array(strtolower($order), array('random', 'rand')) ? 'RAND()' : "`ed`.`value` $order");
		}

		protected static function isFilterRegex($string){
			if(preg_match('/^regexp:/i', $string)) return true;
		}

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->properties()->id;

			if (self::isFilterRegex($data[0])){
				self::$key++;
				$pattern = str_replace('regexp:', '', $this->escape($data[0]));
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
						ON (e.id = t{$field_id}_{self::$key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{self::$key}.value REGEXP '{$pattern}'
				";

			} 
			
			elseif ($andOperation == true){
				foreach ($data as $value) {
					self::$key++;
					$value = $this->escape($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
							ON (e.id = t{$field_id}_{self::$key}.entry_id)
					";
					$where .= "
						AND t{$field_id}_{self::$key}.value = '{$value}'
					";
				}

			} 
			
			else{
				if (!is_array($data)) $data = array($data);

				foreach ($data as &$value) {
					$value = $this->escape($value);
				}

				self::$key++;
				$data = implode("', '", $data);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
						ON (e.id = t{$field_id}_{self::$key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{self::$key}.value IN ('{$data}')
				";
			}

			return true;
		}
		
		public function processFormData($data){
			return (object)array('value' => $data);
		}
		
		// TO DO: Support an array of data objects. This is important for 
		// fields like Select box or anything that allows mutliple values
		public function saveData(StdClass $data=NULL, MessageStack &$errors, Entry $entry){

			$data->entry_id = $entry->id;
			if(!isset($data->id)) $data->id = NULL;
			
			try{
				Symphony::Database()->insert(
					sprintf('tbl_data_%s_%s', $entry->section, $this->properties()->{'element_name'}),
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
		
		public function loadDataFromDatabase(Entry $entry){
			try{
				return Symphony::Database()->query(
					"SELECT * FROM `tbl_data_%s_%s` WHERE `entry_id` = %s LIMIT 1", 
					array(
						$entry->section, 
						$this->properties()->{'element_name'}, 
						$entry->id
					)
				)->current();
			}
			catch(DatabaseException $e){
				// Oh oh....no data. oh well, have a smoke and then return
			}
		}
		
		public function validateData(StdClass $data=NULL, MessageStack &$errors, Entry $entry=NULL){
			if ($this->properties()->required == 'yes' && (!isset($data->value) || strlen(trim($data->value)) == 0)){
				$errors->append(
					$this->properties()->{'element-name'}, 
					array(
					 	'message' => __("'%s' is a required field.", array($this->properties()->label)),
						'code' => self::ERROR_MISSING
					)
				);
				return self::STATUS_ERROR;
			}
			return self::STATUS_OK;
		}

		/*

			$data - post data from the entry form
			$status - refence variable. Will hold the status code
			$simulate (optional) - this will tell CF's to simulate data creation. This is important if they
								   will be deleting or adding data outside of the main entry object commit function
			$entry_id (optionsl) - Useful for identifying the current entry

		*/
		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL) {

			$status = self::STATUS_OK;

			return array(
				'value' => $data,
			);
		}

		public function prepareTableValue(StdClass $data, DOMElement $link=NULL) {
			$max_length = Symphony::Configuration()->get('cell-truncation-length', 'symphony');
			$max_length = ($max_length ? $max_length : 75);

			$value = strip_tags($data->value);
			$value = (strlen($value) <= $max_length ? $value : substr($value, 0, $max_length) . '...');

			if (strlen($value) == 0) $value = __('None');

			if (!is_null($link)) {
				$link->setValue($value);
				return $link;
			}

			return $value;
		}

		public function getExampleFormMarkup(){
			$label = Widget::Label($this->properties()->label);
			$label->appendChild(Widget::Input('fields['.$this->properties()->{'element-name'}.']'));

			return $label;
		}

		public function fetchIncludableElements(){
			return array($this->properties()->{'element-name'});
		}

		public function fetchAssociatedEntrySearchValue($data, $field_id=NULL, $parent_entry_id=NULL){
			return $data;
		}

		public function fetchAssociatedEntryCount($value){
		}

		public function fetchAssociatedEntryIDs($value){
		}

		public function displayDatasourceFilterPanel(SymphonyDOMElement &$wrapper, $data=NULL, MessageStack $errors=NULL){
			$document = $wrapper->ownerDocument;
			
			$name = $document->createElement('span', $this->properties()->label);
			$name->setAttribute('class', 'name');
			$name->appendChild($document->createElement('i', $this->name()));
			$wrapper->appendChild($name);

			$label = Widget::Label(__('Value'));
			$label->appendChild(Widget::Input(
				'fields[filter]'
				//. (!is_null($fieldnamePrefix) ? "[{$fieldnamePrefix}]" : NULL)
				. '[' . $this->properties()->{'element-name'} . ']',
				//. (!is_null($fieldnamePostfix) ? "[{$fieldnamePostfix}]" : NULL),
				(!is_null($data) ? General::sanitize($data) : NULL)
			));
			$wrapper->appendChild($label);
		}

		public function displaySettingsPanel(SymphonyDOMElement &$wrapper, $errors=NULL){
			//$wrapper->appendChild(Symphony::Parent()->Page->createElement('h3', ucwords($this->name())));
			$this->appendSummaryBlock($wrapper, $errors);
		}

		public function appendSummaryBlock(SymphonyDOMElement $wrapper, $errors=NULL) {
			$document = $wrapper->ownerDocument;
			
			if ($this->properties()->label) {
				$name = $document->createElement('span', $this->properties()->label);
				$name->appendChild($document->createElement('i', $this->name()));
			}
			
			else {
				$name = $document->createElement('span', $this->name());
			}
			
			$name->setAttribute('class', 'name');
			$wrapper->appendChild($name);
			
			$label = Widget::Label(__('Label'));
			$label->setAttribute('class', 'field-label');
			$label->appendChild(Widget::Input('label', $this->properties()->label));
			
			if (isset($errors['label'])) {
				$label = Widget::wrapFormElementWithError($label, $errors['label']);
			}
			
			$wrapper->appendChild($label);
		}

		public function appendRequiredCheckbox(SymphonyDOMElement $wrapper) {
			if (!$this->_required) return;
			
			$document = $wrapper->ownerDocument;
			$item = $document->createElement('li');
			$item->appendChild(Widget::Input('required', 'no', 'hidden'));
		
			$label = Widget::Label(__('Make this a required field'));
			$input = Widget::Input('required', 'yes', 'checkbox');

			if ($this->properties()->required == 'yes') {
				$input->setAttribute('checked', 'checked');
			}
			
			$label->prependChild($input);
			$item->appendChild($label);
			$wrapper->appendChild($item);
		}

		public function appendShowColumnCheckbox(SymphonyDOMElement $wrapper) {
			if (!$this->_showcolumn) return;

			$document = $wrapper->ownerDocument;
			$item = $document->createElement('li');
			$item->appendChild(Widget::Input('show-column', 'no', 'hidden'));

			$label = Widget::Label(__('Show column'));
			$label->setAttribute('class', 'meta');
			$input = Widget::Input('show-column', 'yes', 'checkbox');

			if ($this->properties()->{'show-column'} == 'yes') $input->setAttribute('checked', 'checked');

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

		public function groupRecords($records){
			trigger_error(__('Data source output grouping is not supported by the <code>%s</code> field', array($this->properties()->label)), E_USER_ERROR);
		}
/*
		public function commit(){

			$fields = array();

			$fields['element_name'] = Lang::createHandle($this->properties()->label);
			if(is_numeric($fields['element_name']{0})) $fields['element_name'] = 'field-' . $fields['element_name'];

			$fields['label'] = $this->properties()->label;
			$fields['parent_section'] = $this->properties()->{'parent-section'};
			$fields['required'] = $this->properties()->required;
			$fields['type'] = $this->_handle;
			$fields['show_column'] = $this->properties()->{'show-column'};
			$fields['sortorder'] = (string)$this->properties()->sortorder;

			if($id = $this->properties()->id){
				return FieldManager::instance()->edit($id, $fields);
			}

			elseif($id = FieldManager::instance()->add($fields)){
				$this->properties()->id = $id;
				$this->createTable();
				return true;
			}

			return false;

		}
*/
		public function createTable(){
			return Symphony::Database()->query(
				sprintf(
					'CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`entry_id` int(11) unsigned NOT NULL,
						`value` varchar(255) default NULL,
					PRIMARY KEY  (`id`),
					KEY `entry_id` (`entry_id`),
					KEY `value` (`value`)
					)',
					$this->properties()->section,
					$this->properties()->{'element-name'}
				)
			);
		}
	}
