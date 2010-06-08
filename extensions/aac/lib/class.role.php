<?php

	Class AdministrationForbiddenPageException extends SymphonyErrorPage{
		public function __construct(){
			parent::__construct(
				__('You do not have permission to access this page.'),
				__('Forbidden'),
				NULL,
				array('header' => 'HTTP/1.0 403 Forbidden')
			);
		}
	}

	Class AdministrationForbiddenPageExceptionHandler extends SymphonyErrorPageHandler{
		public static function render($e){
			parent::render($e);
		}
	}
	
	Class RoleException extends Exception{
	}
	
	Class RoleDatabaseResultIterator extends DBCMySQLResult{
		public function current(){
			$record = parent::current();

			$role = new Role;
			$role->id = $record->id;
			$role->name = $record->name;
			$role->description = $record->description;
			
			// Load Permissions
			$permissions = Symphony::Database()->query(
				"SELECT * FROM `tbl_aac_permissions` WHERE `role_id` = %d", array($role->id)
			);
			
			if($permissions->length() > 0){
				foreach($permissions as $p){
					$role->permissions()->{"{$p->key}.{$p->type}"} = $p->level;
				}
			}
			
			// Load Forbidden Pages

			return $role;
		}
	}
	
	Class RoleIterator implements Iterator{

	    private $rows;

	    public function __construct(){
			$this->rows = Symphony::Database()->query(
				"SELECT * FROM `tbl_aac_roles` ORDER BY `name` ASC", NULL, 
				'RoleDatabaseResultIterator'
			);
	    }

	    public function rewind(){
	        $this->rows->rewind();
	    }

	    public function current(){
	        return $this->rows->current();
	    }

	    public function key(){
	        return $this->rows->key();
	    }

	    public function next(){
	        return $this->rows->next();
	    }

	    public function valid(){
	        return $this->rows->valid();
	    }

		public function length(){
			return $this->rows->length();
		}

	}
	
	Class Role{
		
		private $fields;
		private $users;
		private $permissions;
		
		public function __construct(){
			$this->fields = array();
			$this->permissions = new StdClass;
		}
		
		public static function load($id){
			return Symphony::Database()->query(
				"SELECT * FROM `tbl_aac_roles` WHERE `id` = %d LIMIT 1", 
				array($id), 
				'RoleDatabaseResultIterator'
			)->current();
		}
		
		public static function save(self $role, MessageStack &$errors){
			// Validation
			if(strlen(trim($role->name)) == 0){
				$errors->append('name', __('Name is required.'));
			}
			
			elseif(
				Symphony::Database()->query("SELECT `id` FROM `tbl_aac_roles` WHERE `name` = '%s' %s",
				array(
					$role->name,
					(isset($role->id) ? "AND `id` != {$role->id} " : NULL)
				))->length() > 0
			){
				$errors->append('name', __('A role with that name already exists.'));
			}
			
			if($errors->length() > 0){
				throw new RoleException('Errors were encountered whist attempting to save.');
			}
			
			// Saving
			$result = Symphony::Database()->insert(
				'tbl_aac_roles',
				array(
					'id' => $role->id,
					'name' => $role->name,
					'description' => $role->description
				),
				Database::UPDATE_ON_DUPLICATE
			);
			
			if(!isset($role->id)){
				$role->id = $result;
			}
			
			Symphony::Database()->delete('tbl_aac_permissions', array($role->id), '`role_id` = %d');
			
			foreach($role->permissions as $name => $level){
				list($key, $type) = preg_split('/\./', $name, 2, PREG_SPLIT_NO_EMPTY);

				Symphony::Database()->insert('tbl_aac_permissions', array(
					'id' => NULL, 
					'role_id' => $role->id, 
					'key' => $key,
					'type' => $type,
					'level' => $level
				));
			}
			
			return $result;
			
		}
		
		public function users(){
			return Symphony::Database()->query(
				"SELECT * FROM `tbl_users` WHERE `role_id` = %d ORDER BY `id` ASC", array($this->id), 'UserResult'
			);
		}
		
		public function &permissions(){
			return $this->permissions;
		}
		
		public function flushPermissions(){
			$this->permissions = new StdClass;
		}
		
		public function __get($name){
			if(!isset($this->fields[$name]) || strlen(trim($this->fields[$name])) == 0) return NULL;
			return $this->fields[$name];
		}
		
		public function __set($name, $value){
			$this->fields[trim($name)] = $value;
		}
		
		public function __isset($name){
			return isset($this->fields[$name]);
		}
		
		public static function moveUsers($source_role_id, $destination_role_id){
			Symphony::Database()->update('tbl_users', array('role_id' => $destination_role_id), array(), sprintf('`role_id` = %d', (int)$source_role_id));
		}
		
		public static function delete($role_id, $replacement_role_id=NULL){
			if(!is_null($replacement_role_id) && is_numeric($replacement_role_id)){
				self::moveUsers($role_id, $replacement_role_id);
			}
			
			if(self::load($role_id)->users()->length() > 0){
				throw new RoleException(__('Cannot delete a role that contains users. Please move users to an existing role first.'));
			}

			Symphony::Database()->delete('tbl_aac_roles', (array)$role_id, '`id` = %d');
		}
		
	}
	