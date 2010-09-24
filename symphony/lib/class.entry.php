<?php

	Class EntryResult extends DBCMySQLResult{
		public $schema = array();

		public function current(){
			$record = parent::current();
			$entry = new Entry;
			foreach($record as $key => $value){
				$entry->$key = $value;
			}

			// Load the section
			try{
				$section = Section::loadFromHandle($entry->section);
			}
			catch(SectionException $e){
				throw new EntryException('Section specified, "'.$entry->section.'", in Entry object is invalid.');
			}
			catch(Exception $e){
				throw new EntryException('The following error occurred during saving: ' . $e->getMessage());
			}

			foreach ($section->fields as $field) {
				if(!empty($this->schema) && !in_array($field->{'element-name'}, $this->schema)) continue;

				$entry->data()->{$field->{'element-name'}} = $field->loadDataFromDatabase($entry);
			}

			return $entry;
		}

		public function setSchema(Array $schema = array()) {
			$this->schema = $schema;
		}
	}

	Class EntryException extends Exception {}

	Class Entry{

		const STATUS_OK = 0;
		const STATUS_ERROR = 1;

		protected $data;
		protected $meta;

		public function __construct(){
			$this->data = new StdClass;
			$this->meta = (object)array(
				'id' => NULL,
				'section' => NULL,
				'user_id' => NULL,
				'creation_date' => DateTimeObj::get('c'),
				'creation_date_gmt' => DateTimeObj::getGMT('c'),
				'modification_date' => DateTimeObj::get('c'),
				'modification_date_gmt' => DateTimeObj::getGMT('c')
			);
		}

		public function __set($name, $value){
			if(!isset($name, $this->meta)) throw new Exception("Cannot set Entry::{$name}, no such property exists.");
			$this->meta->$name = $value;
		}

		public function __get($name){
			if(!isset($name, $this->meta)) throw new Exception("Cannot get Entry::{$name}, no such property exists.");
			return $this->meta->$name;
		}

		public function __isset($name){
			return isset($this->meta->$name);
		}

		public function &data(){
			return $this->data;
		}

		public function meta(){
			return $this->meta;
		}

		public static function loadFromID($id, $schema = array()){
			$result = Symphony::Database()->query("SELECT * FROM `tbl_entries` WHERE `id` = %d LIMIT 1", array($id), 'EntryResult');

			$result->setSchema($schema);

			if (!$result->valid()) return null;

			return $result->current();
		}

		public function setFieldDataFromFormArray(array $data){

			// Load the section
			try{
				$section = Section::loadFromHandle($this->section);
			}
			catch(SectionException $e){
				throw new EntryException('Section specified, "'.$this->section.'", in Entry object is invalid.');
			}
			catch(Exception $e){
				throw new EntryException('The following error occurred during saving: ' . $e->getMessage());
			}

			foreach($section->fields as $field){
				$field_handle = $field->{'element-name'};

				//	The current behaviour is stupid, nulling fields if they are omitting from the form
				//	as it breaks Frontend form submissions that don't have the complete set of fields for
				//	that section.. The problem I forsee from my line here is that it will break image
				//	upload fields when you try to remove a file from them (but not replace). I don't have
				//	time to test, so for now, it's just gotta be known that you must put all yours field
				//	in your frontend forms, or be prepared to have their values NULL'd ^BA.
				//	if(!isset($data[$field_handle]) continue;

				$result = $field->processData((!isset($data[$field_handle]) ? NULL : $data[$field_handle]), $this);

				$this->data()->$field_handle = $result;
			}
		}

		public static function delete($id){
			$entry = self::loadFromID($id);
			$section = Section::loadFromHandle($entry->section);

			foreach($section->fields as $field){
				Symphony::Database()->delete(
					sprintf('tbl_data_%s_%s', $section->handle, $field->{'element-name'}),
					array($entry->id),
					'`entry_id` = %d'
				);
			}

			Symphony::Database()->delete('tbl_entries', array($entry->id), " `id` = %d LIMIT 1");
		}

		public static function save(self $entry, MessageStack &$errors){

			if(!isset($entry->section) || strlen(trim($entry->section)) == 0){
				throw new EntryException('A section must be specified before attempting to save.');
			}

			// Create a new ID if one is not already set
			$purge_meta_on_error = false;
			if(!isset($entry->id) || is_null($entry->id)){
				$purge_meta_on_error = true;
				$entry->id = self::generateID($entry->section, $entry->user_id);
			}

			// Update the modification details
			$entry->modification_date = DateTimeObj::get('c');
			$entry->modification_date_gmt = DateTimeObj::getGMT('c');

			// Load the section
			try{
				$section = Section::loadFromHandle($entry->section);
			}
			catch(SectionException $e){
				throw new EntryException('Section specified, "'.$entry->section.'", in Entry object is invalid.');
			}
			catch(Exception $e){
				throw new EntryException('The following error occurred during saving: ' . $e->getMessage());
			}

			$entry->findDefaultFieldData();
			$status = Field::STATUS_OK;

			// Check the data
			foreach($section->fields as $field){
				$field_data = $entry->data()->{$field->{'element-name'}};
				$field_errors = new MessageStack;
				$field_status = $field->validateData($field_errors, $entry, $field_data);

				if ($field_status != Field::STATUS_OK) {
					$status = $field_status;
				}

				$errors->append($field->{'element-name'}, $field_errors);
			}

			// Attempt the saving part
			if ($status == Field::STATUS_OK){
				// Update the meta row
				Symphony::Database()->insert('tbl_entries', (array)$entry->meta(), Database::UPDATE_ON_DUPLICATE);

				foreach ($section->fields as $field) {
					if (!isset($entry->data()->{$field->{'element-name'}})) continue;

					$field_data = $entry->data()->{$field->{'element-name'}};
					$field_errors = $errors->{$field->{'element-name'}};

					$status = $field->saveData($field_errors, $entry, $field_data);

					// Cannot continue if a field failed to save
					if ($status != Field::STATUS_OK) break;
				}
			}

			// Cleanup due to failure
			if ($status != Field::STATUS_OK && $purge_meta_on_error == true){
				Symphony::Database()->delete('tbl_entries', array(), " `id` = {$entry->id} LIMIT 1");

				return self::STATUS_ERROR;
			}

			/*
				TODO: 	Implement Cleanup when a Field's value becomes null (ie. clears a field)
						This will arise if you enter a value in a field, save, then come back
						and clear the field.
			*/

			if ($status != Field::STATUS_OK) {
				return self::STATUS_ERROR;
			}

			return self::STATUS_OK;
		}

		public function fetchAllAssociatedEntryCounts($associated_sections=NULL) {
			/*
			if(is_null($this->get('section_id'))) return NULL;

			if(is_null($associated_sections)) {
				$section = SectionManager::instance()->fetch($this->get('section_id'));
				$associated_sections = $section->fetchAssociatedSections();
			}

			if(!is_array($associated_sections) || empty($associated_sections)) return NULL;

			$counts = array();

			foreach($associated_sections as $as){

				$field = FieldManager::instance()->fetch($as['child_section_field_id']);

				$parent_section_field_id = $as['parent_section_field_id'];

				$search_value = NULL;

				if(!is_null($parent_section_field_id)){
					$search_value = $field->fetchAssociatedEntrySearchValue(
							$this->getData($as['parent_section_field_id']),
							$as['parent_section_field_id'],
							$this->get('id')
					);
				}

				else{
					$search_value = $this->get('id');
				}

				$counts[$as['child_section_id']] = $field->fetchAssociatedEntryCount($search_value);

			}

			return $counts;
			*/

			return array();

		}

		public function checkPostData($data, MessageStack &$errors, $ignore_missing_fields=false){
			$errors = NULL;
			$status = self::STATUS_ERROR;

			$section = Section::loadFromHandle($entry->get('section'));
			$schema = $section->fetchFieldsSchema();

			foreach($schema as $info){
				$result = NULL;

				$field = FieldManager::instance()->fetch($info['id']);

				if($ignore_missing_fields && !isset($data[$field->get('element_name')])) continue;

				if(Field::STATUS_OK != $field->checkPostFieldData((isset($data[$info['element_name']]) ? $data[$info['element_name']] : NULL), $message, $this->get('id'))){
					$strict = false;
					$status = self::STATUS_ERROR;

					$errors[$info['id']] = $message;
				}

			}

			return $status;
		}

		public static function generateID($section, $user_id=NULL) {

			if(is_null($user_id)){
				$user_id = Symphony::Database()->query("SELECT `id` FROM `tbl_users` ORDER BY `id` ASC LIMIT 1")->current()->id;
			}

			return Symphony::Database()->insert('tbl_entries', array(
				'section' => $section,
				'user_id' => $user_id,
				'creation_date' => DateTimeObj::get('c'),
				'creation_date_gmt' => DateTimeObj::getGMT('c'),
				'modification_date' => DateTimeObj::get('c'),
				'modification_date_gmt' => DateTimeObj::getGMT('c')
			));
		}

		public function findDefaultFieldData(){

			$section = Section::loadFromHandle($this->section);

			foreach($section->fields as $field){
				$element = $field->{'element-name'};

				if(isset($this->data()->$element)) continue;

				$this->data()->$element = $field->processData(NULL, $this);
			}

		}

	}

