<?php

	include_once('lib/class.role.php');

	Class extensionAdministrationAccessControl implements iExtension{
		
		public function about(){
			return (object)array(
				'name' => 'Administration Access Control',
				'version' => '1.0',
				'release-date' => '2010-05-26',
				'author' => (object)array(
					'name' => 'Alistair Kearney',
					'website' => 'http://alistairkearney.com',
					'email' => 'hi@alistairkearney.com'
				)
	 		);
		}

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/administration/',
					'delegate' => 'AdminPagePreGenerate',
					'callback' => 'cbModifyPages'
				),
			);
		}

		public function fetchNavigation(){
			return array(
				array(
					'location' => 'System',
					'name' => 'User Roles',
					'link' => '/roles/'
				)
			);
		}
		
		public function cbModifyPages($context=NULL){
			$callback = Administration::instance()->getPageCallback();

			// Users
			if($callback['pageroot'] == '/system/users/'){

				// Index
				if(is_null($callback['context'])){
					
					if(isset($_POST['with-selected']) && isset($_POST['items']) && preg_match('/^aac-role::(\d+)/i', $_POST['with-selected'], $match)){
						$checked = @array_keys($_POST['items']);
						if(is_array($checked) && !empty($checked)){
							Symphony::Database()->query(sprintf(
								"UPDATE `tbl_users` SET `role_id` = %d WHERE `id` IN (%s)",
								(int)$match[1],
								implode(',', $checked)
							));
						}
					}
					
					$this->modifyUsersPageIndex($context);
				}
				
				// New
				elseif(isset($callback['context'][0]) && $callback['context'][0] == 'new'){
					$this->modifyUsersPageNew($context);
				}

				// Edit
				elseif(isset($callback['context'][0]) && $callback['context'][0] == 'edit'){
					$this->modifyUsersPageEdit((int)$callback['context'][1], $context);
				}
			}
			
		}
		
		private function modifyUsersPageIndex($context=NULL){
			$doc = $context['page'];
	
			// Add the 'Role' column to the thead
	        $tr = $doc->xpath('//table/thead/tr')->item(0);

			$th_role = $doc->createElement('th', 'Role');
			$th_role->setAttribute('scope', 'col');
			$tr->appendChild($th_role);

			// Find all the users
		    $users = $doc->xpath('//table/tbody/tr');

			foreach($users as $position => $u){
				
				// Figure out the user's ID based on the url for editing. Would be nice if there was a more direct way
				$edit_url = trim((string)$u->getElementsByTagName('td')->item(0)->getElementsByTagName('a')->item(0)->getAttribute('href'));
				preg_match('/edit\/(\d+)\/$/i', $edit_url, $match);
				$user_id = (int)$match[1];
				
				// Fine the role title
				$role = Symphony::Database()->query(
					sprintf(
						"SELECT r.title FROM `tbl_aac_roles` AS `r`
						LEFT JOIN `tbl_users` AS `u` ON r.id = u.role_id
						WHERE u.id = %d
						LIMIT 1",
						$user_id
					)
				)->current()->title;
				
				// Append the new td using the role we discovered above
				$td = $doc->createElement('td',  General::sanitize((string)$role));
				$u->appendChild($td);
			}

			// Update the with-selected
		    $with_selected = $doc->xpath("//select[@name = 'with-selected']")->item(0);
			
			//$roles = Symphony::Database()->query("SELECT * FROM `tbl_aac_roles` ORDER BY `title` ASC");
			
			$optgroup = $doc->createElement('optgroup');
			$optgroup->setAttribute('label', 'Set Role');

			foreach(new RoleIterator as $r){
				$option = $doc->createElement('option', General::sanitize($r->title));
				$option->setAttribute('value', "aac-role::{$r->id}");
				$optgroup->appendChild($option);
			}
			
			$with_selected->appendChild($optgroup);
			
	        $context['page'] = $doc;
		}
		
		private function modifyUsersPageNew($context=NULL){
			$doc = $context['page'];

			$element = $doc->xpath("//div[@id='layout']/div[1]/fieldset[1]")->item(0);
			
			$group = $doc->createElement('div');
			$group->setAttribute('class', 'group');
			
			$label = $doc->createElement('label', 'Role');
			
			$select = $doc->createElement('select');
			$select->setAttribute('name', 'fields[role_id]');

			foreach(new RoleIterator as $r){
				$option = $doc->createElement('option', General::sanitize($r->title));
				$option->setAttribute('value', $r->id);
				
				if(isset($_POST['fields']) && isset($_POST['fields']['role_id']) && (int)$_POST['fields']['role_id'] == $r->id){
					$option->setAttribute('selected', 'selected');
				}
				
				$select->appendChild($option);
			}
			
			$label->appendChild($select);
			$group->appendChild($label);
				
			$element->appendChild($group);
			
			$context['page'] = $doc;
		}
		
		private function modifyUsersPageEdit($user_id, $context=NULL){

			$user = User::load($user_id);

			$doc = $context['page'];
			
			$element = $doc->xpath("//div[@id='layout']/div[1]/fieldset[1]")->item(0);
			
			$group = $doc->createElement('div');
			$group->setAttribute('class', 'group');
			
			$label = $doc->createElement('label', 'Role');
			
			$select = $doc->createElement('select');
			$select->setAttribute('name', 'fields[role_id]');

			$role_selected = false;

			foreach(new RoleIterator as $r){
				$option = $doc->createElement('option', General::sanitize($r->title));
				$option->setAttribute('value', $r->id);
				
				if($role_selected == false){
					if(isset($_POST['fields']) && isset($_POST['fields']['role_id']) && (int)$_POST['fields']['role_id'] == $r->id){
						$option->setAttribute('selected', 'selected');
						$role_selected = true;
					}
					elseif($user->role_id == $r->id){
						$option->setAttribute('selected', 'selected');
						$role_selected = true;
					}
				}
				
				$select->appendChild($option);
			}
			
			$label->appendChild($select);
			$group->appendChild($label);
				
			$element->appendChild($group);
			
			$context['page'] = $doc;
		}		
		
		public function uninstall(){
			Symphony::Database()->query("DROP TABLE `tbl_aac_roles`");
			return true;
		}

		public function install(){

			Symphony::Database()->query("CREATE TABLE `tbl_aac_roles` (
				`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`title` VARCHAR(255) NOT NULL,
				INDEX (`title`)
			)");

			// Default roles
			Symphony::Database()->query(
				"INSERT INTO `tbl_aac_roles` (`id`, `title`) VALUES
				(1, 'Developer'),
				(2, 'Author');"
			);
			
			Symphony::Database()->query(
				"ALTER TABLE `tbl_users` ADD `role_id` INT(11) NOT NULL"
			);
			
			// Set existing users to 'Developer' role
			Symphony::Database()->query(
				"UPDATE `tbl_users` SET `role_id` = 1"
			);				
			
			return true;

		}

	}
	
	return 'extensionAdministrationAccessControl';