<?php

namespace controllers;

class Assignment extends \Controller {
	
	function __construct() {
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
		$assignment_info = $Assignment->findById($params["id"]);
		if ($assignment_info == null) {
			header('HTTP/1.0 404 Not Found');
			die();
		}
		
		if (strtotime($assignment_info["start"]) > time()) {
			$error = array(
				"error" => "access_unopened_assignment",
				"error_description" => "The assignment is not opened yet."
			);
			$base->set("error", $error);
			$this->setView("error.html");
			return;
		}
		
		$submissionInfo = $Assignment->getAllSubmissionsOf($userInfo["user_id"], $params["id"]);
		
		$base->set("me", $userInfo);
		$base->set("assignment_info", $assignment_info);
		$base->set("submissions", $submissionInfo);
		$this->setView("assignment.html");
	}
	
	/**
	 * Save the submitted file of an assignment.
	 */
	public function submitFile($base) {
		$user_info = $this->getUserStatus();
		if ($user_info == null) {
			die();
		}
		
		$Assignment = \models\Assignment::instance();
		
		// verify assignment
		$assignment_id = $base->get("POST.assignment_id");
		$assignment_info = null;
		if (!empty($assignment_id))
			$assignment_info = $Assignment->findById($assignment_id);
		if ($assignment_info == null) {
			header('HTTP/1.0 404 Not Found');
			die();
		}
		
		// change submission dir temporarily
		$base->set("UPLOADS", $base->get("UPLOADS") . $assignment_info["id"] . "/" . $user_info["user_id"] . "/");
		$result = $Assignment->saveSubmission($user_info, $assignment_info);
		var_dump($result);
	}
	
	/**
	 * Get the source file of a submission record.
	 * 
	 */
	public function getFile($base, $params) {
		// verify user
		$user_info = $this->getUserStatus();
		try {
			if ($user_info == null) throw new AssignmentException("not_logged_in", "You need to log in to perform this operation.", 403);
			
			$submission_id = $params["submission_id"];
			if (!is_numeric($submission_id)) throw new AssignmentException("invalid_parameter", "Your request is refused because it contains invalid information.", 403);
			
			$Assignment = \models\Assignment::instance();
			
			$submission_info = $Assignment->findSubmissionById($submission_id);
			if ($submission_info == null) throw new AssignmentException("submission_not_found", "There is no record for this submission.", 404);
			
			// only the submitter and the admin can fetch the src file.
			if ($submission_info["user_id"] != $user_info["user_id"] && !$user_info["role"]["permissions"]["manage"])
				throw new AssignmentException("permission_denied", "You are not allowed to fetch this submission.", 403);
			
			if (!file_exists($submission_info["file_path"]))
				throw new AssignmentException("file_not_found", "The file you are requesting is not found in the repository. Please contact admin.", 404);
			
			$speed_limit = 16; // in KBps
			\Web::instance()->send($submission_info["file_path"], "application/octet-stream", $speed_limit);
			
		} catch (AssignmentException $ex) {
			if ($ex->getCode() == 404) header("HTTP/1.0 404 Not Found");
			else if ($ex->getCode() == 403) header("HTTP/1.0 403 Forbidden");
			
			if ($user_info != null) $base->set("me", $user_info);
			$base->set("error", $ex->toArray());
			$this->setView("error.html");
		}
		
	}
	
}
