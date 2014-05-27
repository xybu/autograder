<?php

namespace models;

class User extends \Model {
	
	protected $data;

	function __construct() {
		parent::__construct();
		$json_data = file_get_contents("data/users.json");
		$this->data = json_decode($json_data);
	}
	
	function findByIdAndPassword($id, $password) {
		print $this->data;
	}

}

