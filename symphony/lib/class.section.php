<?php

	require_once('class.field.php');

	Class SectionException extends Exception {}

	Class SectionFilterIterator extends FilterIterator{
		public function __construct($path) {
			parent::__construct(new DirectoryIterator($path));
		}

		public function accept(){
			if($this->isDir() == false && preg_match('/^([^.]+)\.xml$/i', $this->getFilename())){
				return true;
			}
			return false;
		}
	}
	
	Class SectionIterator implements Iterator{
		private static $sections;
		private $position;

		public function __construct(){
			$this->position = 0;
			
			if (!empty(self::$sections)) return;
			
			self::clearCachedFiles();
			
			foreach (new SectionFilterIterator(SECTIONS) as $file) {
				self::$sections[] = $file->getPathname();
			}
			
			$extensions = new ExtensionIterator(ExtensionIterator::FLAG_STATUS, Extension::STATUS_ENABLED);
			
			foreach ($extensions as $extension) {
				$path = Extension::getPathFromClass(get_class($extension));
				
				if (!is_dir($path . '/sections')) continue;
				
				foreach (new SectionFilterIterator($path . '/sections') as $file) {
					self::$sections[] = $file->getPathname();
				}
			}
		}
		
		public static function clearCachedFiles() {
			self::$sections = array();
		}

		public function length(){
			return count(self::$sections);
		}

		public function rewind(){
			$this->position = 0;
		}

		public function current(){
			return Section::load(self::$sections[$this->position]);
		}

		public function key(){
			return $this->position;
		}

		public function next(){
			++$this->position;
		}

		public function valid(){
			return isset(self::$sections[$this->position]);
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
			$this->name = null;
			$this->fields = array();
			$this->layout = array();
			$this->{'navigation-group'} = null;
			$this->{'publish-order-handle'} = null;
			$this->{'publish-order-direction'} = null;
			$this->{'hidden-from-publish-menu'} = null;
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

		public function appendField(Field $field){
			$field->section = $this->handle;
			$this->fields[] = $field;
		}

		public function appendFieldByType($type, array $data=NULL){

			$field = Field::loadFromType($type);

			if(!is_null($data)){
				$field->setPropertiesFromPostData($data);
			}

			$this->appendField($field);
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
			foreach($this->fields as $field){
				if ($field->{'element-name'} == $handle) {
					return $field;
				}
			}
			return NULL;
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

						try{
							$section->appendField(
								Field::loadFromXMLDefinition($field)
							);
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

			$section->sanitizeLayout(true);

			if (isset($doc->attributes()->guid)) {
				$section->guid = (string)$doc->attributes()->guid;
			}

			return $section;
		}

		public static function loadFromHandle($name){
			return self::load(self::__find($name) . "/{$name}.xml");
		}

		protected static function __find($name) {
		    if (is_file(SECTIONS . "/{$name}.xml")) return SECTIONS;
		    
		    else {
				foreach (new ExtensionIterator(ExtensionIterator::FLAG_STATUS, Extension::STATUS_ENABLED) as $extension) {
					$path = Extension::getPathFromClass(get_class($extension));
					$handle = Extension::getHandleFromPath($path);
					
					if (!is_file(EXTENSIONS . "/{$handle}/sections/{$name}.xml")) continue;
					
					return EXTENSIONS . "/{$handle}/sections";
				}
	    	}

		    return false;
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

			$section->sanitizeLayout();

			return file_put_contents($pathname, (string)$section);
		}

		public static function syncroniseStatistics(Section $section) {
			$new_doc = new DOMDocument('1.0', 'UTF-8');
			$new_doc->formatOutput = true;
			$new_doc->loadXML((string)$section);
			$new_xpath = new DOMXPath($new_doc);
			$new_handle = $section->handle;

			$old = $new = array();
			$result = (object)array(
				'synced'	=> true,
				'section'	=> (object)array(
					'create'	=> false,
					'rename'	=> false,
					'old'		=> (object)array(
						'handle'	=> $new_handle,
						'name'		=> $section->name
					),
					'new'		=> (object)array(
						'handle'	=> $new_handle,
						'name'		=> $section->name
					)
				),
				'remove'	=> array(),
				'rename'	=> array(),
				'create'	=> array(),
				'update'	=> array()
			);

			$res = Symphony::Database()->query(
				'
					SELECT
						s.xml
					FROM
						`tbl_sections_sync` AS s
					WHERE
						s.section = "%s"
						AND md5(s.xml) != md5("%s")
				',
				array(
					$section->guid,
					$new_doc->saveXML()
				)
			);

			if ($res->valid()) {
				$old_doc = new DOMDocument('1.0', 'UTF-8');
				$old_doc->formatOutput = true;
				$old_doc->loadXML($res->current()->xml);
				$old_xpath = new DOMXPath($old_doc);
				$old_handle = $old_xpath->evaluate('string(/section/name/@handle)');

				if ($old_handle != $new_handle) {
					$result->synced = false;
					$result->section->rename = true;
					$result->section->old->handle = $old_handle;
					$result->section->old->name = $old_xpath->evaluate('string(/section/name)');
				}

				// Build array of old and new nodes for comparison:
				foreach ($old_xpath->query('/section/fields/field') as $node) {
					$type = $old_xpath->evaluate('string(type)', $node);
					$field = Field::loadFromType($type);
					$field->loadSettingsFromSimpleXMLObject(
						simplexml_import_dom($node)
					);
					
					$old[$field->guid] = (object)array(
						'label'		=> $field->label,
						'field'		=> $field
					);
				}
			}

			else {
				$result->synced = false;
				$result->section->create = true;
			}

			foreach ($new_xpath->query('/section/fields/field') as $node) {
				$type = $new_xpath->evaluate('string(type)', $node);
				$field = Field::loadFromType($type);
				$field->loadSettingsFromSimpleXMLObject(
					simplexml_import_dom($node)
				);
				
				$new[$field->guid] = (object)array(
					'label'		=> $field->label,
					'field'		=> $field
				);
			}

			foreach ($new as $guid => $data) {
				// Field is being created:
				if (!array_key_exists($guid, $old)) {
					$result->create[$guid] = $data;
					continue;
				}

				// Field is being renamed:
				if ($result->section->rename or $old[$guid]->field->{'element-name'} != $data->field->{'element-name'}) {
					if ($old[$guid]->field->type == $data->field->type) {
						$result->rename[$guid] = (object)array(
							'label'		=> $data->label,
							'old'		=> $old[$guid]->field,
							'new'		=> $data->field
						);
					}

					// Type has changed:
					else {
						$result->remove[$guid] = $old[$guid];
						$result->create[$guid] = $data;
						continue;
					}
				}

				// Field definition has changed:
				if ($old[$guid]->field != $data->field) {
					if ($old[$guid]->field->type == $data->field->type) {
						$result->update[$guid] = (object)array(
							'label'		=> $data->label,
							'old'		=> $old[$guid]->field,
							'new'		=> $data->field
						);
					}

					// Type has changed:
					else {
						$result->remove[$guid] = $old[$guid];
						$result->create[$guid] = $data;
						continue;
					}
				}
			}

			foreach ($old as $guid => $data) {
				if (array_key_exists($guid, $new)) continue;

				$result->remove[$guid] = $data;
			}

			$result->synced = (
				$result->synced
				and empty($result->remove)
				and empty($result->rename)
				and empty($result->create)
				and empty($result->update)
			);

			return $result;
		}

		public static function synchronise(Section $section) {
			$stats = self::syncroniseStatistics($section);
			$new_handle = $stats->section->new->handle;
			$old_handle = $stats->section->old->handle;

			// Remove fields:
			foreach ($stats->remove as $guid => $data) {
				$data->field->remove();
			}

			// Rename fields:
			foreach ($stats->rename as $guid => $data) {
				$data->new->rename($data->old);
			}

			// Create fields:
			foreach ($stats->create as $guid => $data) {
				$data->field->create();
			}

			// Update fields:
			foreach ($stats->update as $guid => $data) {
				$data->new->update($data->old);
			}

			// Remove old sync data:
			Symphony::Database()->delete(
				'tbl_sections_sync',
				array($section->guid),
				'`section` = "%s"'
			);

			// Create new sync data:
			Symphony::Database()->insert('tbl_sections_sync', array(
				'section'	=> $section->guid,
				'xml'		=> (string)$section
			));
		}

		public static function rename(Section $section, $old_handle) {
			/*
			 	TODO:
				Upon renaming a section, data-sources/events attached to it must update.
				Views will also need to update to ensure they still have references to the same
				data-sources/sections
			*/

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

			// 	Remove fields:
			foreach ($section->fields as $field) {
				$field->remove();
			}

			// 	Remove sync data:
			Symphony::Database()->delete(
				'tbl_sections_sync',
				array($section->guid),
				'`section` = "%s"'
			);

			//	Remove entry metadata
			Symphony::Database()->delete(
				'tbl_entries',
				array($section->handle),
				'`section` = "%s"'
			);

			if(General::deleteFile(SECTIONS . '/' . $section->handle . '.xml')) {
				//	Cleanup Datasources
				foreach(new DataSourceIterator as $datasource) {
					$ds = DataSource::load($datasource);

					if($ds->parameters()->section == $section->handle) {
						DataSource::delete($ds);
					}
				}

				//	Cleanup Events
				foreach(new EventIterator as $event) {
					$ev = Event::load($event);

					if($ev->parameters()->source == $section->handle) {
						Event::delete($ev);
					}
				}
			}
		}

		public function sanitizeLayout($loading = false) {
			$debug = ($this->handle == 'tests' and !$loading);
			$layout = $this->layout;
			$fields_used = array();
			$fields_available = array();

			// Find available fields:
			foreach ($this->fields as $field) {
				$fields_available[] = $field->{'element-name'};
			}

			// Make sure we have at least one column:
			if (!is_array($layout) or empty($layout)) {
				$layout = array(
					(object)array(
						'size'		=> Layout::LARGE,
						'fieldsets'	=> array()
					),
					(object)array(
						'size'		=> Layout::SMALL,
						'fieldsets'	=> array()
					)
				);
			}

			// Make sure each column has a fieldset:
			foreach ($layout as &$column) {
				if (!isset($column->fieldsets) or !is_array($column->fieldsets)) {
					$column->fieldsets = array();
				}

				if (empty($column->fieldsets)) {
					$column->fieldsets = array(
						(object)array(
							'name'		=> __('Untitled'),
							'fields'	=> array()
						)
					);
				}

				foreach ($column->fieldsets as &$fieldset) {
					if (!isset($fieldset->fields) or !is_array($fieldset->fields)) {
						$fieldset->fields = array();
					}

					if (empty($fieldset->fields)) {
						$fieldset->fields = array();
					}

					foreach ($fieldset->fields as $index => $field) {
						if (in_array($field, $fields_available)) {
							$fields_used[] = $field;
						}

						else {
							unset($fieldset->fields[$index]);
						}

						$fields_used[] = $field;
					}
				}
			}

			$fields_unused = array_diff($fields_available, $fields_used);

			if (is_array($fields_unused)) foreach ($fields_unused as $field) {
				$layout[0]->fieldsets[0]->fields[] = $field;
			}

			if ($debug) {
				//$this->layout = $layout;

				//var_dump($layout);
				//exit;
			}

			$this->layout = $layout;
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
