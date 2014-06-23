<?php

namespace controllers;

class Home extends \Controller {
	
	// interval between two retrieving password requests, in minutes
	const RETRIEVE_PASSWORD_INTERVAL = 60;
	
	function showHomePage($base) {
		if ($base->exists("SESSION.user")) {
			$user_info = $this->getUserStatus();
			if ($user_info == null) {
				// instead of raising a UserException, reroute the user
				// to homepage
				$base->clear("SESSION.user");
				$base->reroute('/');
			}
			
			$Assignment = \models\Assignment::instance();		
			$base->set("assignments", $Assignment->getAllAssignments());
			$base->set("me", $user_info);
			$this->setView("usercp.html");
		} else {
			$this->setView("signin.html");
		}
	}
	
	function showAnnouncements($base, $param) {
		$Rss = new \models\Rss("data/feed.xml");
		if ($param['type'] == "rss") {
			header('Content-Type: application/xml; charset=utf-8');
			echo $Rss->get_raw();
		} else {
			$base->set("announcements", $Rss->get_items());
			$this->setView("ajax_announcements.html");
		}
	}
	
	function showRetrievePasswordPage($base) {
		if ($base->exists("SESSION.user"))
			$base->reroute("/");
		$this->setView("forgot_password.html");
	}
	
	function retrievePassword($base) {
		
		if ($base->exists("SESSION.forgot_password"))
			$this->json_echo(array("error" => "request_too_frequent", "error_description" => "The wait time between two requests is " . self::RETRIEVE_PASSWORD_INTERVAL . " minute(s). Please contact admin for emergency."));
		
		if ($base->exists("SESSION.user"))
			$this->json_echo(array("error" => "user_logged_in", "error_description" => "You are already logged in to the system. Please go to assignment list page."));
		
		$user_id = $base->get("POST.user_id");
		if (empty($user_id))
			$this->json_echo(array("error" => "empty_user_id", "error_description" => "Please enter your user id."));
		
		$User = \models\User::instance();
		$user_info = $User->findById($user_id);
		if ($user_info == null)
			$this->json_echo(array("error" => "unknown_user", "error_description" => "The user id provided is not found."));
		
		$base->set("password", $user_info["password"]);
		
		$Mail = new \models\Mail();
		$Mail->addTo($user_id . $base->get("USER_EMAIL_SUFFIX"), $user_id);
		$Mail->setFrom($base->get("COURSE_ADMIN_EMAIL"), $base->get("COURSE_ID_DISPLAY") . " AutoGrader");
		$Mail->setSubject("Your " . $base->get("COURSE_ID_DISPLAY") . " AutoGrader Password");
		$Mail->setMessage(\View::instance()->render("email_forgot_password.txt"));
		$Mail->send();

		$base->set("SESSION.forgot_password", true, self::RETRIEVE_PASSWORD_INTERVAL * 60);
		
		$this->json_echo(array("status" => "success", "message" => "An email has been sent to " . $user_id . $base->get("USER_EMAIL_SUFFIX") . ". Please check your inbox."));
	}
	
	function showSupportPage($base) {
		
	}	
	
	function showHelpPage($base) {
		
	}
}
