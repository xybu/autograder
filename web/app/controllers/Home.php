<?php

namespace controllers;

class Home extends \Controller {

	function showHomePage($base) {
		if ($base->exists("SESSION.user"))
			$base->reroute("/assignments");
		$this->setView("signin.html");
	}
	
	function showRetrievePasswordPage($base) {
		if ($base->exists("SESSION.user"))
			$base->reroute("/assignments");
		$this->setView("forgot_password.html");
	}
	
	function retrievePassword($base) {
		if ($base->exists("SESSION.user")) return;
		$user_id = $base->get("POST.user_id");
		if (empty($user_id)) {
			$base->set("error", array("error" => "empty_user_id", "error_description" => "Please enter your user id."));
			$this->setView("error.html");
			return;
		}
		
		$User = \models\User::instance();
		$user_info = $User->findById($user_id);
		if ($user_info == null) {
			$base->set("error", array("error" => "user_not_found", "error_description" => "The user id you provided is not registered in the system. Please contact admin."));
			$this->setView("error.html");
			return;
		}
		
		
	}
	
	function showSupportPage($base) {
		
	}	
	
	function showHelpPage($base) {
		
	}
}
