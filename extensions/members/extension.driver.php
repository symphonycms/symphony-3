<?php
	
	require_once 'lib/class.login-event.php';
	require_once 'lib/class.logout-event.php';
	
	class Extension_Members implements iExtension {
		const PASSWORD_WEAK = 3;
		const PASSWORD_GOOD = 7;
		const PASSWORD_STRONG = 10;
		
		public static $member_sections;
		
		public function about() {
			return (object)array(
				'name'			=> 'Members',
				'version'		=> '1.0.1',
				'release-date'	=> '2010-06-17',
				'type'			=> array(
					'Event', 'Members'
				),
				'author'		=> (object)array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://rowanlewis.com/',
					'email'			=> 'me@rowanlewis.com'
				)
			);
		}
		
	/*-------------------------------------------------------------------------
		Events:
	-------------------------------------------------------------------------*/
		
		public function getEventTypes() {
			return array(
				(object)array(
					'class'		=> 'Member_Login_Event',
					'name'		=> __('Login')
				),
				(object)array(
					'class'		=> 'Member_Logout_Event',
					'name'		=> __('Logout')
				)
			);
		}
		
	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/
		
		public function repairEntities($value) {
			return preg_replace('/&(?!(#[0-9]+|#x[0-9a-f]+|amp|lt|gt);)/i', '&amp;', trim($value));
		}
		
		public function checkPasswordStrength($password) {
			$mixture = array(
				'/[a-z]+/'			=> 1.3,
				'/[A-Z]+/'			=> 1.3,
				'/[0-9]+/'			=> 1.1,
				'/[^a-zA-Z0-9]+/'	=> 1.6
			);
			$repeat = array(
				'/(.)(?:\1+)/'		=> 1.1,
				'/([a-z])(?:\1+)/i'	=> 1.3
			);
			$length = strlen($password);
			
			if ($length == 0) return 0;
			
			// Use the password length to find the total:
			$min = $max = $length;
			
			// Short passwords raise the max:
			$max *= min(max(8 / $length, 1), 10);
			
			// A a bad mixture raise the max:
			foreach ($mixture as $expression => $factor) {
				if (preg_match($expression, $password)) continue;
				
				$max *= $factor;
			}
			
			// Repeating characters raise the max:
			foreach ($repeat as $expression => $factor) {
				if (!preg_match($expression, $password, $match)) continue;
				
				$max += strlen($match[0]);
				$max *= $factor;
			}
			
			return round(10 * $min / $max, 1);
		}
		
		public function getMemberSections() {
			if (is_null(self::$member_sections)) foreach (new SectionIterator() as $section) {
				$fields = array(
					'FieldMemberEmail'		=> false,
					'FieldMemberPassword'	=> false
				);
				
				foreach ($section->fields as $field) if (isset($fields[get_class($field)])) {
					$fields[get_class($field)] = true;
				}
				
				if (array_sum($fields) == count($fields)) {
					self::$member_sections[] = $section;
				}
			}
			
			return self::$member_sections;
		}
		
	/*-------------------------------------------------------------------------
		Special Tokens:
	-------------------------------------------------------------------------*/
		
		public function createToken($code, $key) {
			$code = hexdec($code);
			$key = intval($key, 36);
			
			return base_convert($code - $key, 10, 36);
		}
		
		public function extractToken($code, $key) {
			$code = intval($code, 36);
			$key = intval($key, 36);
			
			return dechex($code + $key);
		}
		
	/*-------------------------------------------------------------------------
		Unique Handles:
	-------------------------------------------------------------------------*/
		
		/**
		* Create a new unique handle.
		*/
		public function createHandle(Field $field, Entry $entry, $value) {
			$handle = Lang::createHandle(strip_tags(html_entity_decode($value)));
			$count = 0;
			
			// Handle is in use by another entry:
			if ($this->isHandleTaken($field, $entry, $handle)) while (++$count) {
				$current = "{$handle}-{$count}";
				
				// Handle is not taken:
				if (!($this->isHandleTaken($field, $entry, $current))) {
					$handle = $current; break;
				}
				
				// Too much iteration, use a unique id:
				else if ($count > 30) {
					$handle = "{$handle}-" . uniqid(); break;
				}
			}
			
			// Handle is different to 
			else if ($this->isHandleFresh($field, $entry, $value)) {
				$handle = $this->getCurrentHandle($field, $entry);
			}
			
			return $handle;
		}
		
		/**
		* Get current handle.
		*/
		public function getCurrentHandle(Field $field, Entry $entry) {
			$result = Symphony::Database()->query(
				"
					SELECT
						f.handle
					FROM
						`tbl_data_%s_%s` AS f
					WHERE
						f.entry_id = %d
					LIMIT 1
				",
				array(
					$field->{'section'},
					$field->{'element-name'},
					$entry->{'id'}
				)
			);
			
			return $result->current()->handle;
		}
		
		/**
		* Check to see if an existing handle exists for the current value.
		*/
		public function isHandleFresh(Field $field, Entry $entry, $value) {
			$result = Symphony::Database()->query(
				"
					SELECT
						f.id
					FROM
						`tbl_data_%s_%s` AS f
					WHERE
						f.value = '%s'
						AND f.entry_id = %d
					LIMIT 1
				",
				array(
					$field->{'section'},
					$field->{'element-name'},
					General::sanitize($value),
					$entry->{'id'}
				)
			);
			
			return $result->valid();
		}
		
		/**
		* Check to see if a handle is currently in use by another entry.
		*/
		public function isHandleTaken(Field $field, Entry $entry, $handle) {
			$result = Symphony::Database()->query(
				"
					SELECT
						f.id
					FROM
						`tbl_data_%s_%s` AS f
					WHERE
						f.handle = '%s'
						AND f.entry_id != %d
					LIMIT 1
				",
				array(
					$field->{'section'},
					$field->{'element-name'},
					$handle,
					$entry->{'id'}
				)
			);
			
			return $result->valid();
		}
	}
	
	return 'Extension_Members';
	
?>