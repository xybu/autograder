<?php
/**
 * \models\User.php
 *
 * The User and Role model.
 * @author	Xiangyu Bu <xybu92@live.com>
 */

namespace models;

class User extends \Model {
	
	private $roles = null;
	
	function __construct() {
		parent::__construct();
		$this->roles = json_decode(file_get_contents($this->Base->get("DATA_PATH") . "roles.json"), true);
	}
	
	function getRoleTable() {
		return $this->roles;
	}
	
	function saveRoleTable($role_data = null) {
		if ($role_data == null) $role_data = $this->getRoleTable();
		return @file_put_contents($this->Base->get("DATA_PATH") . "roles.json", json_encode($role_data), LOCK_EX);
	}
	
	function findRoleByName($str) {
		if (array_key_exists($str, $this->getRoleTable()))
			return $this->getRoleTable()[$str];
		return null;
	}
	
	function findById($id) {
		$result = $this->query('SELECT user_id, password, role_id FROM users WHERE user_id=?', $id);
		
		if (count($result) == 1) {
			$role_id = $result[0]['role_id'];
			$result[0]['role_info'] = $this->getRoleTable()[$role_id];
			return $result[0];
		}
		
		return null;
	}
	
	function findByIdAndPassword($id, $password) {
		$user_info = $this->findById($id);
		
		if ($user_info != null && password_verify($password, $user_info['password']))
			return $user_info;
		return null;
	}
	
	function addUser($id, $password, $role) {
		$this->query('INSERT INTO users (user_id, password, role_id) VALUES (:user_id, :password, :role_id)', array(
			':user_id' => $id,
			':password' => password_hash($password, PASSWORD_DEFAULT),
			':role_id' => $role
		));
	}
	
	/**
	 * Edit the user $old_user with the given new information.
	 * The items with value null will not be updated.
	 * The data source $old_user is not updated.
	 */
	function editUser($old_user, $new_id = null, $new_role = null, $new_pass = null) {
		if ($new_id == null) $new_id = $old_user['user_id'];
		if ($new_role == null) $new_role = $old_user['role_id'];
		if ($new_pass == null) $new_pass = $old_user['password'];
		else $new_pass = password_hash($new_pass, PASSWORD_DEFAULT);
		
		$this->query('UPDATE users SET user_id=:new_user_id, password=:new_pass, role_id=:new_role_id WHERE user_id=:old_user_id', array(
			':old_user_id' => $old_user['user_id'],
			':new_user_id' => $new_id,
			':new_pass' => $new_pass,
			':new_role_id' => $new_role
		));
	}
	
	function deleteUserById($id) {
		$this->query('DELETE FROM users WHERE user_id=?', $id);
	}
	
	/**
	 * Return an array of users in 3-tuple () format
	 * whose id and role matche the given wildcard patterns.
	 */
	function matchByPatterns($id_pattern = '*', $role_pattern = '*') {
		$result = $this->query('SELECT * FROM users WHERE user_id LIKE :id AND role_id LIKE :role', array(
			':id' => $this->to_mysql_wildcard($id_pattern),
			':role' => $this->to_mysql_wildcard($role_pattern)
		));
		return $result;
	}
	
	/**
	 * Generate an array of $num random strings each with a length of $len chars.
	 * After hashing the password, there is no way to check if the password has been used.
	 */
	function getPasswordPool($num, $len) {
		$i = 0;
		$pool = array();
		while ($i < $num) {
			$str = $this->getRandomStr($len);
			if (in_array($str, $pool)) continue;
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
	
	/**
	 * Fix the array $role_data by sanitizing the content,
	 * adding missing keys and deleting unnecessary keys.
	 * 
	 * The array is assumed to have a valid 'display' key.
	 */
	function sanitizeRoleEntry($role) {
		$new_role = array('display' => $role['display']);
		$boolean_fields = array('submit', 'submit_prestart', 'submit_overdue', 'override_quota', 'manage');
		
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
	
	/**
	 * Replace the tokens {user_id}, {password}, {role_id}, {role_name}
	 * with the corresponding user data.
	 */
	function replaceTokens($user_info, $str) {
		$str = str_replace('{user_id}', $user_info['user_id'], $str);
		$str = str_replace('{role_id}', $user_info['role_id'], $str);
		$str = str_replace('{role_name}', $user_info['role_info']['display'], $str);
		return $str;
	}

}
