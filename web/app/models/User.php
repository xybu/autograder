<?php

namespace models;

class User extends \Model {
	
	protected $data;
	
	function __construct() {
		parent::__construct();
		$json_data = file_get_contents("data/users.json");
		$this->data = json_decode($json_data, true);
	}
	
	function findById($id) {
		foreach ($this->data as $rolename => $members) {
			if (array_key_exists($id, $members))
				return array(
					"userid" => $id,
					"role" => $rolename,
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
	
}

