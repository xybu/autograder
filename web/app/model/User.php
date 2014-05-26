<?php

namespace models;

class User extends \Model {
	
	protected $data;
	
	public function __construct() {
		parent::__construct();
		$json_data = file_get_contents("data/users.json");
		$this->data = json_decode($json_data);
	}
	
	public function findByIdAndPassword($id, $password){
	}
	
	public function findById($id, $password){
	}
	
}

