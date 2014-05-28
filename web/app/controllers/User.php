<?php

namespace controllers;

class User extends \Controller {
	
	protected $User = null;
	
	function __construct() {
		parent::__construct();
		$this->User = \models\User::instance();
	}
	
	function signIn($base) {
		try {
			$User = $this->User;
			$userid = $base->get("POST.userid");
			$password = $base->get("POST.password");
			$remember = $base->get("POST.remember");
			
			$userinfo = $User->findByIdAndPassword($userid, $password);
			var_dump($userinfo);
			//throw new UserException("user_not_found", "The user was not found.");
		} catch (UserException $e) {
			$this->json_echo($e->toArray(), True);
		}
	}
	
}

class UserException extends \Exception {

	protected $error;

	public function __construct($error, $message, $code = 0, \Exception $prev = null) {
		parent::__construct($message, $code, $prev);
		$this->error = $error;
	}
	
	public function toArray() {
		return array(
				"error" => $this->error,
				"error_description" => $this->message
		);
	}
}
