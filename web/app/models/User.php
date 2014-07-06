<?php

namespace models;

class User extends \Model {
	
	protected $users;
	protected $roles;
	
	function __construct() {
		parent::__construct();
		$this->users = json_decode(file_get_contents($this->Base->get("DATA_PATH") . "users.json"), true);
		$this->roles = json_decode(file_get_contents($this->Base->get("DATA_PATH") . "roles.json"), true);
	}
	
	function findById($id) {
		foreach ($this->users as $rolename => $members) {
			if (array_key_exists($id, $members))
				return array(
					"user_id" => $id,
					"role" => array("name" => $rolename, "permissions" => $this->roles[$rolename]),
					"password" => $members[$id]
				);
		}
		return null;
	}
	
	function findByIdAndPassword($id, $password) {
		$userinfo = $this->findById($id);
		
		if ($userinfo != null && $password == $userinfo["password"])
			return $userinfo;
		return null;
	}
	
	function getRoleMap() {
		return $this->roles;
	}
	
	/**
	 * Fix the array $role_data by sanitizing the content,
	 * adding missing keys and deleting unnecessary keys.
	 * 
	 * The array is assumed to have a valid 'display' key.
	 */
	function sanitizeRoleEntry($role) {
		
		$new_role = array('display' => $role['display']);
		
		$boolean_fields = array('submit', 'submit_prestart', 'submit_overdue', 'manage');
		
		foreach ($boolean_fields as $k) {
			if (array_key_exists($k, $role) && $role[$k] == 'true')
				$new_role[$k] = true;
			else $new_role[$k] = false;
		}
		
		if (array_key_exists('submit_priority', $role) && is_numeric($role['submit_priority']))
			$new_role['submit_priority'] = intval($role['submit_priority']);
		else $new_role['submit_priority'] = 1;
		
		return $new_role;
	}
	
	function saveRoleMap($role_data) {
		return @file_put_contents($this->Base->get("DATA_PATH") . "roles.json", json_encode($role_data), LOCK_EX);
	}
}

