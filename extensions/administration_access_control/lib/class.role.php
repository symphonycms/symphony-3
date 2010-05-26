<?php

	Class RoleIterator implements Iterator{

	    private $_rows;
		private $_position;

	    public function __construct(){
			$this->_rows = array();
			$this->_position = 0;
			
			$rows = Symphony::Database()->query("SELECT * FROM `tbl_aac_roles` ORDER BY `title` ASC");
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
			$role->title = $record->title;
			
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
		
	}
	