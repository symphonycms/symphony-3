<?php

	require_once('class.field.php');

	Class SectionException extends Exception {}

	Class SectionFilterIterator extends FilterIterator{
		public function __construct(){
			parent::__construct(new DirectoryIterator(SECTIONS));
		}

		public function accept(){
			if($this->isDir() == false && preg_match('/^([^.]+)\.xml$/i', $this->getFilename())){
				return true;
			}
			return false;
		}
	}

	Class SectionIterator implements Iterator{

		private $_iterator;
		private $_length;
		private $_position;

		public function __construct(){
			$this->_iterator = new SectionFilterIterator;
			$this->_length = $this->_position = 0;
			foreach($this->_iterator as $f){
				$this->_length++;
			}
			$this->_iterator->getInnerIterator()->rewind();
		}

		public function current(){
			return Section::load($this->_iterator->current()->getPathname());
		}

		public function innerIterator(){
			return $this->_iterator;
		}

		public function next(){
			$this->_position++;
			$this->_iterator->next();
		}

		public function key(){
			return $this->_iterator->key();
		}

		public function valid(){
			return $this->_iterator->valid();
		}

		public function rewind(){
			$this->_position = 0;
			$this->_iterator->rewind();
		}

		public function position(){
			return $this->_position;
		}

		public function length(){
			return $this->_length;
		}

	}


	Class Section{

		const ERROR_SECTION_NOT_FOUND = 0;
		const ERROR_FAILED_TO_LOAD = 1;
		const ERROR_DOES_NOT_ACCEPT_PARAMETERS = 2;
		const ERROR_TOO_MANY_PARAMETERS = 3;

		const ERROR_MISSING_OR_INVALID_FIELDS = 4;
		const ERROR_FAILED_TO_WRITE = 5;

		protected static $sections = array();

		protected $parameters;
		protected $fields;
		
		public static function createGUID() {
			return uniqid();
		}

		public function __construct(){
			$this->parameters = new StdClass;
			$this->fields = array();
		}

		public function __isset($name){
			return isset($this->parameters->$name);
		}

		public function __get($name){

			if($name == 'handle'){

				/*
				[4]   	NameStartChar	   ::=   	":" | [A-Z] | "_" | [a-z] | [#xC0-#xD6] | [#xD8-#xF6] | [#xF8-#x2FF] | [#x370-#x37D] | [#x37F-#x1FFF] | [#x200C-#x200D] | [#x2070-#x218F] | [#x2C00-#x2FEF] | [#x3001-#xD7FF] | [#xF900-#xFDCF] | [#xFDF0-#xFFFD] | [#x10000-#xEFFFF]
				[4a]   	NameChar	   ::=   	NameStartChar | "-" | "." | [0-9] | #xB7 | [#x0300-#x036F] | [#x203F-#x2040]
				*/

				//if(!isset($this->handle) || strlen(trim($this->parameters->handle)) < 0){
					$this->handle = Lang::createHandle($this->parameters->name, '-', false, true, array('/^[^:_a-z]+/i' => NULL, '/[^:_a-z0-9\.-]/i' => NULL));
				//}
			}

			elseif($name == 'guid' and !isset($this->guid)){
				$this->parameters->guid = Section::createGUID();
			}

			elseif($name == 'fields'){
				return $this->fields;
			}

			return $this->parameters->$name;
		}

		public function __set($name, $value){
			$this->parameters->$name = $value;
		}

		//public function initialise(){
		//	if(!($this->_about instanceof StdClass)) $this->_about = new StdClass;
		//}

		/*public function __get($name){

			if($name == 'classname'){
				$classname = Lang::createHandle($this->_about->name, '-', false, true, array('@^[^a-z]+@i' => NULL, '/[^\w-\.]/i' => NULL));
				$classname = str_replace(' ', NULL, ucwords(str_replace('-', ' ', $classname)));
				return 'section' . $classname;
			}
			elseif($name == 'handle'){
				if(!isset($this->_about->handle) || strlen(trim($this->_about->handle)) > 0){
					$this->handle = Lang::createHandle($this->_about->name, '-', false, true, array('@^[\d-]+@i' => ''));
				}
				return $this->_about->handle;

			}
			elseif($name == 'guid'){
				if(is_null($this->_about->guid)){
					$this->_about->guid = uniqid();
				}
				return $this->_about->guid;
			}
			return $this->_about->$name;
		}

		public function __set($name, $value){
			//if(in_array($name, array('path', 'template', 'handle', 'guid'))){
			//	$this->{"_{$name}"} = $value;
		//	}
		//	else
			if($name == 'guid') return; //guid cannot be set manually
			$this->_about->$name = $value;
		}*/

		public function appendField($type, array $data=NULL){

			$field = Field::loadFromType($type);

			if(!is_null($data)){
				$field->setPropertiesFromPostData($data);
			}
			$field->section = $this->handle;
			
			$this->fields[] = $field;

			return $field;
		}

		public function removeAllFields(){
			$this->fields = array();
		}

		public function removeField($name){
			foreach($this->fields as $index => $f){
				if($f->label == $name || $f->{'element-name'} == $name){
					unset($this->fields[$index]);
				}
			}
		}

		/*
		**	Given an field's element name, return an object of
		**	that Field.

		**	@param $handle string
		**	@return Field
		*/
		public function fetchFieldByHandle($handle) {
			foreach($this->fields as $field) if ($field->{'element-name'} == $handle) {
				return $field;
			}
		}

		public static function fetchUsedNavigationGroups(){
			$groups = array();
			foreach(new SectionIterator as $s){
				$groups[] = $s->{'navigation-group'};
			}
			return General::array_remove_duplicates($groups);
		}

		public static function load($path){
			$section = new self;

			$section->handle = preg_replace('/\.xml$/', NULL, basename($path));
			$section->path = dirname($path);

			if(!file_exists($path)){
				throw new SectionException(__('Section `%s` could not be found.', array(basename($path))), self::ERROR_SECTION_NOT_FOUND);
			}

			$doc = @simplexml_load_file($path);

			if(!($doc instanceof SimpleXMLElement)){
				throw new SectionException(__('Failed to load section configuration file: %s', array($path)), self::ERROR_FAILED_TO_LOAD);
			}

			foreach($doc as $name => $value){
				if($name == 'fields' && isset($value->field)){
					foreach($value->field as $field){
						$data = array();
						
						foreach($field as $property_name => $property_value){
							$data[(string)$property_name] = (string)$property_value;
						}
						
						// Set field GUID:
						if (isset($field->attributes()->guid) and trim((string)$field->attributes()->guid) != '') {
							$data['guid'] = (string)$field->attributes()->guid;
						}
						
						try{
							$section->appendField($data['type'], $data);
						}
						
						catch(Exception $e){
							// Couldnt find the field. Ignore it for now
							// TODO: Might need to more than just ignore it
						}

					}
				}

				elseif($name == 'layout' && isset($value->column)){
					$data = array();

					foreach ($value->column as $column) {
						if (!isset($column->size)) {
							$size = Layout::LARGE;
						}

						else {
							$size = (string)$column->size;
						}

						$data_column = (object)array(
							'size'		=> $size,
							'fieldsets'	=> array()
						);

						foreach ($column->fieldset as $fieldset) {
							if (!isset($fieldset->name) or trim((string)$fieldset->name) == '') {
								$name = __('Untitled');
							}

							else {
								$name = (string)$fieldset->name;
							}

							$data_fieldset = (object)array(
								'name'		=> $name,
								'fields'	=> array()
							);

							foreach ($fieldset->field as $field) {
								$data_fieldset->fields[] = (string)$field;
							}

							$data_column->fieldsets[] = $data_fieldset;
						}

						$data[] = $data_column;
					}

					$section->layout = $data;
				}

				elseif(isset($value->item)){
					$stack = array();
					foreach($value->item as $item){
						array_push($stack, (string)$item);
					}
					$section->$name = $stack;
				}

				else{
					$section->$name = (string)$value;
				}
			}

			if (isset($doc->attributes()->guid)) {
				$section->guid = (string)$doc->attributes()->guid;
			}

			return $section;
		}

		public function loadFromHandle($handle){
			return self::load(SECTIONS . '/' . $handle . '.xml');
		}

		public static function save(Section $section, MessageStack $messages, $essentials = null, $simulate = false) {
			$pathname = sprintf('%s/%s.xml', $section->path, $section->handle);
			
			// Check to ensure all the required section fields are filled
			if (!isset($section->name) || strlen(trim($section->name)) == 0) {
				$messages->append('name', __('This is a required field.'));
			}

			// Check for duplicate section handle
			elseif(file_exists($pathname)){
				$existing = self::load($pathname);
				
				if (isset($existing->guid) and $existing->guid != $section->guid) {
					$messages->append('name', __('A Section with the name <code>%s</code> already exists', array($section->name)));
				}
				
				unset($existing);
			}

			## Check to ensure all the required section fields are filled
			if(!isset($section->{'navigation-group'}) || strlen(trim($section->{'navigation-group'})) == 0){
				$messages->append('navigation-group', __('This is a required field.'));
			}

			if(is_array($section->fields) && !empty($section->fields)){
				foreach ($section->fields as $index => $field) {
					$field_stack = new MessageStack;

					if ($field->validateSettings($field_stack, false, false) != Field::STATUS_OK) {
						$messages->append("field::{$index}", $field_stack);
					}
				}
			}

			if ($messages->length() > 0) {
				throw new SectionException(__('Section could not be saved. Validation failed.'), self::ERROR_MISSING_OR_INVALID_FIELDS);
			}
			
			if ($simulate) return true;
			
			$doc = $section->toDoc();
			
			return file_put_contents($pathname, $doc->saveXML());
		}
		
		public static function syncroniseStatistics(Section $section, $old_handle = null) {
			$new_handle = $section->handle;
			
			if (is_null($old_handle)) $old_handle = $new_handle;
			
			$old = $new = $create = $update = $rename = $remove = array();
			$res = Symphony::Database()->query(
				'
					SELECT
						s.guid, s.name, s.type
					FROM
						`tbl_sections_sync` AS s
					WHERE
						s.section = "%s"
				',
				array($old_handle)
			);
			
			// Get existing field GUIDs:
			if ($res->valid()) foreach ($res as $field) {
				$old[$field->guid] = $field;
			}
			
			foreach ($section->fields as $field) {
				// Field is being created:
				if (!array_key_exists($field->guid, $old)) {
					$create[$field->guid] = $field->{'element-name'};
				}
				
				// Field is being renamed or removed then created:
				else if ($old_handle != $new_handle or $old[$field->guid]->name != $field->{'element-name'}) {
					if ($old[$field->guid]->type == $field->type) {
						$rename[$field->guid] = $field->{'element-name'};
						$update[$field->guid] = $field->{'element-name'};
					}
					
					else {
						$remove[$field->guid] = $field->{'element-name'};
						$create[$field->guid] = $field->{'element-name'};
					}
				}
				
				// Field is updated:
				else {
					$update[$field->guid] = $field->{'element-name'};
				}
				
				$new[$field->guid] = $field->{'element-name'};
			}
			
			// Fields that no longer exist:
			$remove = array_merge($remove, array_diff_key($old, $new));
			
			// Fill out remove data:
			foreach ($remove as $guid => &$data) {
				if (!is_object($data)) {
					$data = Symphony::Database()->query(
						'
							SELECT
								s.name, s.type
							FROM
								`tbl_sections_sync` AS s
							WHERE
								s.guid = "%s"
							LIMIT 1
						',
						array($guid)
					)->current();
				}
				
				$field = Field::loadFromType($data->type);
				$field->guid = $guid;
				$field->label = $data->name;
				$field->section = $old_handle;
				
				$data = (object)array(
					'name'	=> $data->name,
					'field' => $field
				);
			}
			
			// Fill out rename data:
			foreach ($rename as $guid => &$data) {
				$field = $section->fetchFieldByHandle($data);
				$current = Symphony::Database()->query(
					'
						SELECT
							s.name
						FROM
							`tbl_sections_sync` AS s
						WHERE
							s.guid = "%s"
						LIMIT 1
					',
					array($guid)
				)->current();
				
				$data = (object)array(
					'from'	=> $current->name,
					'to'	=> $data,
					'field'	=> $field
				);
			}
			
			// Fill out create data:
			foreach ($create as $guid => &$data) {
				$field = $section->fetchFieldByHandle($data);
				
				$data = (object)array(
					'name'	=> $data,
					'field'	=> $field
				);
			}
			
			// Fill out update data:
			foreach ($update as $guid => &$data) {
				$field = $section->fetchFieldByHandle($data);
				
				$data = (object)array(
					'name'	=> $data,
					'field'	=> $field
				);
			}
			
			return (object)array(
				'synced'	=> (empty($remove) and empty($rename) and empty($create)),
				'remove'	=> $remove,
				'rename'	=> $rename,
				'create'	=> $create,
				'update'	=> $update
			);
		}

		public static function synchronise(Section $section, $old_handle = null) {
			$new_handle = $section->handle;
			
			if (is_null($old_handle)) $old_handle = $new_handle;
			
			$stats = self::syncroniseStatistics($section, $old_handle);
			
			// Remove fields:
			foreach ($stats->remove as $guid => $data) {
				$data->field->remove();
			}
			
			// Rename fields:
			foreach ($stats->rename as $guid => $data) {
				$data->field->rename(
					$old_handle, $data->from,
					$new_handle, $data->to
				);
			}
			
			// Create fields:
			foreach ($stats->create as $guid => $data) {
				$data->field->create();
			}
			
			// Update fields:
			foreach ($stats->update as $guid => $data) {
				$data->field->update();
			}
			
			// Remove old sync data:
			Symphony::Database()->delete(
				'tbl_sections_sync',
				array($old_handle),
				'`section` = "%s"'
			);
			
			// Create new sync data:
			foreach ($section->fields as $field) {
				Symphony::Database()->insert('tbl_sections_sync', array(
					'guid'		=> $field->guid,
					'name'		=> $field->{'element-name'},
					'type'		=> $field->type,
					'section'	=> $new_handle
				));
			}
		}
		
		public static function rename(Section $section, $old_handle) {
			/*
			 	TODO:
				Upon renaming a section, data-sources/events attached to it must update.
				Views will also need to update to ensure they still have references to the same
				data-sources/sections
			*/
			
			self::synchronise($section, $old_handle);
			
			return General::deleteFile(SECTIONS . '/' . $old_handle . '.xml');
		}
		
		public static function delete(Section $section) {
			/*
				TODO:
				Upon deletion it should update all data-sources/events attached to it.
				Either by deleting them, or making section $unknown.

				I think deletion is best because if the section is renamed, the rename()
				function will take care of moving the dependancies, so there should be
				no data-sources/events to delete anyway.

				However, if you delete page accidentally (hm, even though you clicked
				confirm), do you really want your data-sources/events to just be deleted?

				Verdict?
			*/
			
			// Remove fields:
			foreach ($section->fields as $field) {
				$field->remove();
			}
			
			// Remove sync data:
			Symphony::Database()->delete(
				'tbl_sections_sync',
				array($section->handle),
				'`section` = "%s"'
			);
			
			return General::deleteFile(SECTIONS . '/' . $section->handle . '.xml');
		}

		public function toDoc(){
			$doc = new DOMDocument('1.0', 'UTF-8');
			$doc->formatOutput = true;

			$root = $doc->createElement('section');
			$doc->appendChild($root);

			if(!isset($this->guid) || is_null($this->guid)){
				$this->guid = uniqid();
			}

			$root->setAttribute('guid', $this->guid);

			$name = $doc->createElement('name', General::sanitize($this->name));
			$name->setAttribute('handle', $this->handle);

			$root->appendChild($name);
			$root->appendChild($doc->createElement('hidden-from-publish-menu', (
				isset($this->{'hidden-from-publish-menu'})
				&& strtolower(trim($this->{'hidden-from-publish-menu'})) == 'yes'
					? 'yes'
					: 'no'
			)));
			$root->appendChild($doc->createElement('navigation-group', General::sanitize($this->{'navigation-group'})));

			$root->appendChild($doc->createElement('publish-order-handle', General::sanitize($this->{'publish-order-handle'})));
			$root->appendChild($doc->createElement('publish-order-direction', General::sanitize($this->{'publish-order-direction'})));

			if(is_array($this->fields) && !empty($this->fields)){
				$fields = $doc->createElement('fields');
				
				foreach ($this->fields as $index => $field) {
					$fields->appendChild($doc->importNode(
						$field->toDoc()->documentElement, true
					));
				}
				
				$root->appendChild($fields);
			}

			if (is_array($this->layout)) {
				$layout = $doc->createElement('layout');

				foreach ($this->layout as $data) {
					$column = $doc->createElement('column');

					if (!isset($data->size) or $data->size != Layout::LARGE) {
						$data->size = Layout::SMALL;
					}

					$size = $doc->createElement('size', $data->size);
					$column->appendChild($size);

					if (is_array($data->fieldsets)) foreach ($data->fieldsets as $data) {
						$fieldset = $doc->createElement('fieldset');

						if (!isset($data->name) or trim($data->name) == '') {
							$data->name = __('Untitled');
						}

						$name = $doc->createElement('name', $data->name);
						$fieldset->appendChild($name);

						if (is_array($data->fields)) foreach ($data->fields as $data) {
							if (!is_string($data) or trim($data) == '') continue;

							$fieldset->appendChild($doc->createElement('field', $data));
						}

						$column->appendChild($fieldset);
					}

					$layout->appendChild($column);
				}

				$root->appendChild($layout);
			}

			return $doc;
		}

		public function __toString(){
			return $this->toDoc()->saveXML();
		}

		/*public function __toString(){
			$template = file_get_contents(TEMPLATES . '/template.section.php');

			$vars = array(
				$this->classname,
				var_export($this->name, true),
				var_export($this->handle, true),
				var_export($this->{'navigation-group'}, true),
				var_export((bool)$this->hidden, true),
				var_export($this->guid, true),
			);

			return vsprintf($template, $vars);
		}*/
	}


	/*Class Section{

		var $_data;
		var $_Parent;
		var $_fields;
		var $_fieldManager;

		public function __construct(&$parent){
			$this->_Parent = $parent;
			$this->_data = $this->_fields = array();

			$this->_fieldManager = new FieldManager($this->_Parent);
		}

		public function fetchAssociatedSections(){
			return Symphony::Database()->fetch("SELECT *
													FROM `tbl_sections_association` AS `sa`, `tbl_sections` AS `s`
													WHERE `sa`.`parent_section_id` = '".$this->get('id')."'
													AND `s`.`id` = `sa`.`child_section_id`
													ORDER BY `s`.`sortorder` ASC
													");

		}

		public function set($field, $value){
			$this->_data[$field] = $value;
		}

		public function get($field=NULL){
			if($field == NULL) return $this->_data;
			return $this->_data[$field];
		}

		public function addField(){
			$this->_fields[] = new Field($this->_fieldManager);
		}

		public function fetchVisibleColumns(){
			return $this->_fieldManager->fetch(NULL, $this->get('id'), 'ASC', 'sortorder', NULL, NULL, " AND t1.show_column = 'yes' ");
		}

		public function fetchFields($type=NULL, $location=NULL){
			return $this->_fieldManager->fetch(NULL, $this->get('id'), 'ASC', 'sortorder', $type, $location);
		}

		public function fetchFilterableFields($location=NULL){
			return $this->_fieldManager->fetch(NULL, $this->get('id'), 'ASC', 'sortorder', NULL, $location, NULL, Field::FLAG_FILTERABLE);
		}

		public function fetchToggleableFields($location=NULL){
			return $this->_fieldManager->fetch(NULL, $this->get('id'), 'ASC', 'sortorder', NULL, $location, NULL, Field::FLAG_TOGGLEABLE);
		}

		public function fetchFieldsSchema(){
			return Symphony::Database()->fetch("SELECT `id`, `element_name`, `type`, `location` FROM `tbl_fields` WHERE `parent_section` = '".$this->get('id')."' ORDER BY `sortorder` ASC");
		}

		public function commit(){
			$fields = $this->_data;
			$retVal = NULL;

			if(isset($fields['id'])){
				$id = $fields['id'];
				unset($fields['id']);
				$retVal = $this->_Parent->edit($id, $fields);

				if($retVal) $retVal = $id;

			}else{
				$retVal = $this->_Parent->add($fields);
			}

			if(is_numeric($retVal) && $retVal !== false){
				for($ii = 0; $ii < count($this->_fields); $ii++){
					$this->_fields[$ii]->parent_section = $retVal;
					$this->_fields[$ii]->commit();
				}
			}
		}
	}
*/
