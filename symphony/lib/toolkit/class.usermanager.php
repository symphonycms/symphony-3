<?php

	Class UserManager{

		public static function fetch($sortby=NULL, $sortdirection='ASC', $limit=NULL, $start=NULL){
			$query = sprintf(
				"
					SELECT
						u.*
					FROM
						`tbl_users` AS u
					GROUP BY
						u.id
					ORDER BY
						%s %s
					%s
				",
				($sortby ? $sortby : 'u.id'),
				$sortdirection,
				($limit ? "LIMIT $limit ": '') . ($start && $limit ? ', ' . $start : '')
			);

			$result = Symphony::Database()->query($query);

			if(!$result->valid()) return null;

			$users = array();

			foreach($result as $row){
				$user = new User;

				foreach($row as $field => $val)
					$user->$field = $val;

				$users[] = $user;
			}

			return $users;
		}

		// TODO: Remove this redundant function and integrate into the fetch() above
		public static function fetchByID($id, $sortby=NULL, $sortdirection='ASC', $limit=NULL, $start=NULL){

			$return_single = false;
			$result = array();

			if(!is_array($id)){
				$return_single = true;
				$id = array($id);
			}

			if(empty($id)) return;

			$query = sprintf(
				"
					SELECT
						u.*
					FROM
						`tbl_users` AS u
					WHERE
						u.id IN ('%%s')
					ORDER BY
						%s %s
					%s
				",
				($sortby ? $sortby : 'u.id'),
				$sortdirection,
				($limit ? "LIMIT $limit ": '') . ($start && $limit ? ', ' . $start : '')
			);

			$rows = Symphony::Database()->query($query, array(
				implode("','", $id)
			));

			if(!$rows->valid()) return null;

			foreach($rows as $rec){

				$user = new User;

				foreach($rec as $field => $val)
					$user->$field = $val;

				$result[] = $user;

			}

			return ($return_single ? $result[0] : $result);

		}


	}

