<?php

namespace controllers;

class Home extends \Controller {
	
	const RETRIEVE_PASSWORD_INTERVAL = 60;	// in minutes
	const DEFAULT_PASSWORD_LEN = 12;
	
	/**
	 * Homepage route handler.
	 */
	function showHomePage($base) {
		$user_info = $this->get_user_status();
		
		if ($user_info == null) {
			$this->set_view("signin.html");
		} else {
			$Assignment = \models\Assignment::instance();		
			$base->set("assignments", $Assignment->getAllAssignments());
			$base->set("me", $user_info);
			$this->set_view("usercp.html");
		}
	}
	
	/**
	 * Announcement RSS route handler.
	 * This handler does not check if the xml file exists.
	 */
	function showAnnouncements($base, $param) {
		$Rss = new \models\Rss($base->get('DATA_PATH') . 'feed.xml');
		
		if ($param['type'] == "rss") {
			header('Content-Type: application/xml; charset=utf-8');
			echo $Rss->get_raw();
		} else {
			$base->set("announcements", $Rss->get_items());
			$this->set_view("ajax_announcements.html");
		}
	}
	
	/**
	 * Forgot password page handler.
	 */
	function showRetrievePasswordPage($base) {
		if ($this->get_user_status() != null) $base->reroute("/");
		$this->set_view("forgot_password.html");
	}
	
	/**
	 * Forgot password form processor handler.
	 */
	function retrievePassword($base) {
		
		try {
			
			if ($this->get_user_status() != null)
				throw new \exceptions\ActionError('user_logged_in', 'You are logged in and cannot perform this operation.');
			
			if ($base->exists('SESSION.forgot_password'))
				throw new \exceptions\ActionError('request_too_frequent', 'The wait time between two requests is ' . self::RETRIEVE_PASSWORD_INTERVAL . ' minute(s). Please contact admin for emergency.');
			
			$User = \models\User::instance();
			$user_id = $base->get('POST.user_id');
			$user_info = $User->findById($user_id);
			if (empty($user_id))
				throw new \exceptions\ActionError('empty_user_id', 'Please enter your user id.');
			
			if (empty($user_info))
				throw new \exceptions\ActionError('user_not_found', 'The user id you entered is not registered.');
			
			$base->set('SESSION.forgot_password', true, self::RETRIEVE_PASSWORD_INTERVAL);
			
			$password_pool = $User->getPasswordPool(1, self::DEFAULT_PASSWORD_LEN);
			$User->editUser($user_info, null, null, $password_pool[0]);
			$base->set("password", $password_pool[0]);
			
			$mail = new \models\Mail();
			$mail->addTo($user_id . $base->get("USER_EMAIL_DOMAIN"), $user_id);
			$mail->setFrom($base->get("COURSE_ADMIN_EMAIL"), $base->get("COURSE_ID_DISPLAY") . " AutoGrader");
			$mail->setSubject("Your " . $base->get("COURSE_ID_DISPLAY") . " AutoGrader Password");
			$mail->setMessage(\View::instance()->render("email_forgot_password.txt"));
			$mail->send();
			
			$this->echo_success('An email has been sent to ' . $user_id . $base->get("USER_EMAIL_DOMAIN") . '. Please check your inbox.');
			
		} catch (\exceptions\ActionError $e) {
			$this->echo_json($e->toArray());
		}
		
	}
	
	function showHelpPage($base) {
		$user_info = $this->get_user_status();
		
		if ($user_info != null) $base->set("me", $user_info);
		
		$this->set_view('help.html');
	}
}
