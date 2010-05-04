<?php

	Class UserException extends Exception {}

	Class UserResult extends DBCMySQLResult{
		public function current(){
			$record = parent::current();
			
			$user = new User;
			
			foreach($record as $key => $value){
				$user->$key = $value;
			}

			return $user;
		}
	}

	Class UserIterator implements Iterator{

		private $iterator;
		private $length;
		private $position;

		public function __construct(){
			$this->iterator = Symphony::Database()->query("SELECT * FROM `tbl_users` ORDER BY `id` ASC", array(), 'UserResult');
		}

		public function current(){
			return $this->iterator->current();
		}

		public function innerIterator(){
			return $this->iterator;
		}

		public function next(){
			$this->iterator->next();
		}

		public function key(){
			return $this->iterator->key();
		}

		public function valid(){
			return $this->iterator->valid();
		}

		public function rewind(){
			$this->iterator->rewind();
		}

		public function position(){
			return $this->iterator->position();
		}

		public function length(){
			return $this->iterator->length();
		}

	}

	Class User{

		private $_fields;

		public function __construct(){
			$this->_fields = array();
		}

		public static function load($id){
			
			$user = new self;
			
			$result = Symphony::Database()->query("SELECT * FROM `tbl_users` WHERE `id` = '%s' LIMIT 1", array($id));

			if (!$result->valid()) return false;

			$row = $result->current();

			foreach ($row as $key => $value) {
				$user->$key = $value;
			}

			return $user;
		}

		public static function loadUserFromUsername($username){
			$result = Symphony::Database()->query("
					SELECT
						u.*
					FROM
						`tbl_users` AS u
					WHERE
						u.username = '%s'
					LIMIT
						1
				",
				array($username)
			);

			if(!$result->valid()) return null;

			$row = $result->current();

			foreach($row as $key => $val){
				$this->$key = $val;
			}

			return true;
		}

		public function verifyToken($token){

			if($this->auth_token_active == 'no') return false;

			$t = General::substrmin(md5($this->username . $this->password), 8);

			if($t == $token){
				return true;
			}

			return false;

		}

		public function createAuthToken(){
			return General::substrmin(md5($this->username . $this->password), 8);
		}

		public function isTokenActive(){
			return ($this->auth_token_active == 'no' ? false : true);
		}

		public function getFullName(){
			return "{$this->first_name} {$this->last_name}";
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

		public function validate(MessageStack $errors){

			if(is_null($this->first_name)) $errors->append('first_name', __('First name is required'));

			if(is_null($this->last_name)) $errors->append('last_name', __('Last name is required'));

			if(is_null($this->email)) $errors->append('email', __('E-mail address is required'));
			elseif(!General::validateString($this->email, '/^[^@]+@[^\.@]+\.[^@]+$/i')) $errors->append('email', __('E-mail address entered is invalid'));

			if(is_null($this->username)) $errors->append('username', __('Username is required'));
			elseif($this->id){
				$result = Symphony::Database()->query("SELECT `username` FROM `tbl_users` WHERE `id` = %d", array($this->id));
				$current_username = $result->current()->username;

				if($current_username != $this->username && Symphony::Database()->query("SELECT `id` FROM `tbl_users` WHERE `username` = '%s'", array($this->username))->valid())
					$errors->append('username', __('Username is already taken'));
			}

			elseif(Symphony::Database()->query("SELECT `id` FROM `tbl_users` WHERE `username` = '%s'", array($this->username))->valid()){
				$errors->append('username', __('Username is already taken'));
			}

			if(is_null($this->password)) $errors->append('password', __('Password is required'));

			return ($errors->length() == 0);
		}

		public function fields(){
			return $this->_fields;
		}

		public static function save(User $user){
			
			$fields = $user->fields();
			unset($fields['id']);
			
			try{
				if(isset($user->id) && !is_null($user->id)){
					Symphony::Database()->update('tbl_users', $fields, array($user->id), "`id` = %d");
				}
				else{
					$user->id = Symphony::Database()->insert('tbl_users', $fields);
				}
			}
			catch(DatabaseException $e){
				return false;
			}
			
			return $user->id;
		}
		

		public static function delete($id){
			return Symphony::Database()->delete('tbl_users', array($id), "`id` = %d");
		}
		
		public static function deactivateAuthToken($id){
			return Symphony::Database()->update("UPDATE `tbl_users` SET `auth_token_active` = 'no' WHERE `id` = '%d' LIMIT 1", array($id));
		}

		public static function activateAuthToken($id){
			return Symphony::Database()->update("UPDATE `tbl_users` SET `auth_token_active` = 'yes' WHERE `id` = '%d' LIMIT 1", array($id));
		}

	}

