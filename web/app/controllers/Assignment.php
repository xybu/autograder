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
	
	public function showDetailOf($base, $params) {
		// verify user
		$userInfo = $this->getUserStatus();
		if ($userInfo == null) {
			// instead of raising a UserException, reroute the user
			// to homepage
			$base->reroute('/');
		}
		
		$Assignment = \models\Assignment::instance();
		
		// verify assignment
		$assignmentInfo = $Assignment->findById($params["id"]);
		if ($assignmentInfo == null) {
			header('HTTP/1.0 404 Not Found');
			die();
		}
		if ($assignmentInfo["start"] > time()) {
			header('HTTP/1.0 403 Forbidden');
			die();
		}
		
		
		$submissionInfo = $Assignment->getAllSubmissionsOf($userInfo["userid"], $params["id"]);
		
		$base->set("me", $userInfo);
		$base->set("assignment", $assignmentInfo);
		$base->set("submissions", $submissionInfo);
		$this->setView("assignment.html");
	}
	
	public function submitFile($base, $params) {
		
	}

	public function getFile($base, $params) {
		
	}
	
}
