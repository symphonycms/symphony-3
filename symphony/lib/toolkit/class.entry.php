<?php
	/*
	**	NO DBC INTEGRATION HAS BEEN DONE ON THIS PAGE
	*/
	
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
		
		public static function save(self $entry, MessageStack &$errors){
			
			if(!isset($entry->section) || strlen(trim($entry->section)) == 0){
				throw new EntryException('A section must be specified before attempting to save.');
			}
			
			// Create a new ID if one is not already set
			if(!isset($entry->id) || is_null($entry->id)){
				$entry->id = self::generateID($entry->section, $entry->user_id);
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
			
			// Check the data
			foreach($section->fields as $field){
				$data = $entry->data()->{$field->properties()->{'element_name'}};
				$field->validateData($data, $errors, $entry);
			}
			
			// Attempt the saving part
			if($errors->length() == 0){
				foreach($section->fields as $field){
					$status = $field->saveData($entry->data()->{$field->properties()->{'element_name'}}, $errors, $entry);
					
					// Cannot continue if a field failed to save
					if($status != Field::STATUS_OK){
						break;
					}
				}
			}
			
			// Cleanup due to failure
			if($errors->length() > 0){
				Symphony::Database()->delete('tbl_entries', array(), " `id` = {$entry->id} LIMIT 1");
				return self::STATUS_ERROR;
			}
			
			return self::STATUS_OK;
		}	
		
		/*function set($field, $value){
			$this->_fields[$field] = $value;
		}

		function get($field=NULL){
			if($field == NULL) return $this->_fields;
			return $this->_fields[$field];
		}*/

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
			
			Symphony::Database()->insert('tbl_entries', array(
				'section' => $section,
				'user_id' => $user_id,
				'creation_date' => DateTimeObj::get('c'),
				'creation_date_gmt' => DateTimeObj::getGMT('c'),
				'modification_date' => DateTimeObj::get('c'),
				'modification_date_gmt' => DateTimeObj::getGMT('c')	
			));

			if(!$entry_id = Symphony::Database()->getInsertID()) return null;
			
			return $entry_id;
		}

		public function setDataFromPost($data, &$error, $simulate=false, $ignore_missing_fields=false){

			$error = NULL;

			$status = self::STATUS_ERROR;

			// Entry has no ID, create it:
			if (!$this->get('id') && $simulate == false) {
				$entry_id = $this->assignEntryId();

				if (is_null($entry_id)) return self::STATUS_ERROR;
			}


			$section = Section::loadFromHandle($entry->get('section'));
			$schema = $section->fetchFieldsSchema();

			foreach($schema as $info){
				$result = NULL;

				$field = FieldManager::instance()->fetch($info['id']);

				if($ignore_missing_fields && !isset($data[$field->get('element_name')])) continue;

				$result = $field->processRawFieldData(
					(isset($data[$info['element_name']]) ? $data[$info['element_name']] : NULL), $s, $simulate, $this->get('id')
				);

				if($s != Field::STATUS_OK){
					$status = self::STATUS_ERROR;
					$error = array('field_id' => $info['id'], 'message' => $m);
				}

				$this->setData($info['id'], $result);
			}

			// Failed to create entry, cleanup
			if($status != self::STATUS_ERROR and !is_null($entry_id)) {
				Symphony::Database()->delete('tbl_entries', " `id` = '$entry_id' ");
			}

			return $status;
		}
/*
		function setData($field_id, $data){
			$this->_data[$field_id] = $data;
		}

		function getData($field_id=NULL, $asObject=false){
			if(!$field_id) return $this->_data;
			return ($asObject == true ? (object)$this->_data[$field_id] : $this->_data[$field_id]);
		}
*/
		public function findDefaultData(){

			$section = Section::loadFromHandle($entry->get('section'));

			foreach($section->fields as $field){
				$element = $field->properties()->{'element_name'};
				
				if(isset($this->data()->$element)) continue;

				$field->processRawFieldData(NULL, $result, $status, false, $this->id);
				$this->data()->$element = $result;
			}

		}
/*
		function commit(){
			$this->findDefaultData();
			return ($this->get('id') ? EntryManager::instance()->edit($this) : EntryManager::instance()->add($this));
		}
*/
	}

