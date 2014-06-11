<?php

namespace controllers;

class User extends \Controller {
	
	protected $User = null;
	
	function __construct() {
		$this->User = \models\User::instance();
	}
	
	function signIn($base) {
		try {
			$User = $this->User;
			$userId = $base->get("POST.userid");
			$password = $base->get("POST.password");
			
			$userInfo = $User->findByIdAndPassword($userId, $password);
			if ($userInfo == null)
				throw new UserException("user_not_found", "The user/password pair was not found.");
			
			$this->setUserStatus($userInfo);
			
			$base->reroute('/assignments');
			
		} catch (UserException $e) {
			$this->json_echo($e->toArray(), True);
		}
	}
	
	function logOut($base) {
		$this->voidUserStatus();
		$base->reroute('/');
	}
	
}
