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
	
	function addUser($id, $role, $password) {
		$this->users[$role][$id] = $password;
	}
	
	/**
	 * Edit the user $old_user with the given new information.
	 * Do not change the null fields.
	 * The data source $old_user is not updated.
	 */
	function editUser($old_user, $new_id = null, $new_role = null, $new_pass = null) {
		if ($new_id == null) $new_id = $old_user['user_id'];
		if ($new_pass == null) $new_pass = $old_user['password'];
		if ($new_role == null) $new_role = $old_user['role']['name'];
		unset($this->users[$old_user['role']['name']][$old_user['user_id']]);
		$this->users[$new_role][$new_id] = $new_pass;
	}
	
	function deleteUserById($id) {
		foreach ($this->users as $rolename => &$members) {
			if (array_key_exists($id, $members)) {
				unset($members[$id]);
				return;
			}
		}
	}
	
	function getUserTable() {
		return $this->users;
	}
	
	function saveUserTable($user_data = null) {
		if ($user_data == null) $user_data = $this->users;
		return @file_put_contents($this->Base->get("DATA_PATH") . "users.json", json_encode($user_data), LOCK_EX);
	}
	
	/**
	 * Return an array of users in 3-tuple ('user_id', 'role', 'password') format
	 * whose id and role matches the given wildcard patterns.
	 */
	function matchByPatterns($id_pattern = '*', $role_pattern = '*', $pass_pattern = '*') {
		$result = array();
		foreach ($this->users as $rolename => $members) {
			if (!fnmatch($role_pattern, $rolename)) continue;
			foreach($members as $id => $pass) {
				if (fnmatch($id_pattern, $id) && fnmatch($pass_pattern, $pass))
					$result[] = array('user_id' => $id, 'role' => $rolename, 'password' => $pass);
			}
		}
		return $result;
	}
	
	/**
	 * Generate an array of $num random strings each with a length of $len chars.
	 * Each string occurs once in the pool, and is not used for other users.
	 */
	function getPasswordPool($num, $len) {
		$user_raw = json_encode($this->users);
		$i = 0;
		$pool = array();
		while ($i < $num) {
			$str = $this->getRandomStr($len);
			if (in_array($str, $pool) || strpos('"' . $str . '"', $user_raw) !== false) continue;
			$pool[] = $str;
			++$i;
		}
		return $pool;
	}
	
	/**
	 * Return a randomly generated string.
	 */
	function getRandomStr($len) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		return substr(str_shuffle(substr(str_shuffle($chars), 0, $len / 2 + 1) . substr(str_shuffle($chars), 0, $len / 2 + 1)), 0, $len);
	}
	
	function findRoleByName($str) {
		if (array_key_exists($str, $this->roles)) return $this->roles[$str];
		return null;
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
	
	function getRoleTable() {
		return $this->roles;
	}
	
	function saveRoleTable($role_data = null) {
		if ($role_data == null) $role_data = $this->roles;
		return @file_put_contents($this->Base->get("DATA_PATH") . "roles.json", json_encode($role_data), LOCK_EX);
	}
	
	/**
	 * Replace the tokens {user_id}, {password}, {role_id}, {role_name}
	 * with the corresponding user data.
	 */
	function replaceTokens($user_info, $str) {
		$str = str_replace('{user_id}', $user_info['user_id'], $str);
		$str = str_replace('{password}', $user_info['password'], $str);
		$str = str_replace('{role_id}', $user_info['role']['name'], $str);
		$str = str_replace('{role_name}', $user_info['role']['permissions']['display'], $str);
		return $str;
	}
}

