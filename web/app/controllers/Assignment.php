<?php

namespace controllers;

class Assignment extends \Controller {
	
	function __construct() {
		parent::__construct();
		$this->User = \models\User::instance();
	}
	
	public function listAll($base) {
		$userInfo = $this->getUserStatus();
		if ($userInfo == null) {
			// instead of raising a UserException, reroute the user
			// to homepage
			$base->reroute('/');
		}
		
		$Assignment = \models\Assignment::instance();
		$base->set("assignments", $Assignment->getAllAssignments());
		$base->set("me", $userInfo);
		$this->setView("assignments.html");
	}
	
}
