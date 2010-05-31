<?php

	Class RoleIterator implements Iterator{

	    private $_rows;
		private $_position;

	    public function __construct(){
			$this->_rows = array();
			$this->_position = 0;
			
			$rows = Symphony::Database()->query("SELECT * FROM `tbl_aac_roles` ORDER BY `name` ASC");
			foreach($rows as $r){
				$this->_rows[] = $r;
			}
	    }

	    public function rewind(){
	        $this->_position = 0;
	    }

	    public function current(){
	        $record = $this->_rows[$this->_position];
			
			$role = new Role;
			$role->id = $record->id;
			$role->name = $record->name;
			
			return $role;
	    }

	    public function key(){
	        return $this->_position;
	    }

	    public function next(){
	        return ++$this->_position;
	    }

	    public function valid(){
	        return isset($this->_rows[$this->_position]);
	    }

		public function length(){
			return count($this->_rows);
		}

	}
	
	Class Role{
		
		private $_fields;
		
		public function __construct(){
			$this->_fields = array();
		}
		
		public function __get($name){
			if(!isset($this->_fields[$name]) || strlen(trim($this->_fields[$name])) == 0) return NULL;
			return $this->_fields[$name];
		}
		
		public function __set($name, $value){
			$this->_fields[trim($name)] = $value;
		}
		
		public function __isset($name){
			return isset($this->_fields[$name]);
		}
		
		public static function moveUsers($source_role_id, $destination_role_id){
			Symphony::Database()->update('tbl_users', array('role_id' => $destination_role_id), array(), sprintf('`role_id` = %d', (int)$source_role_id));
		}
		
		public static function delete($role_id, $replacement_role_id=NULL){
			if(!is_null($replacement_role_id) && is_numeric($replacement_role_id)){
				self::moveUsers($role_id, $replacement_role_id);
			}
			Symphony::Database()->delete('tbl_aac_roles', (array)$role_id, '`id` = %d');
		}
		
	}
	