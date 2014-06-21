<?php

namespace controllers;

class Assignment extends \Controller {
	
	function __construct() {
		$this->User = \models\User::instance();
	}
	
	public function listAll($base) {
		$user_info = $this->getUserStatus();
		if ($user_info == null) {
			// instead of raising a UserException, reroute the user
			// to homepage
			$base->reroute('/');
		}
		
		$Assignment = \models\Assignment::instance();
		$base->set("assignments", $Assignment->getAllAssignments());
		$base->set("me", $user_info);
		$this->setView("assignments.html");
	}
	
	public function showDetailOf($base, $params) {
		// verify user
		$user_info = $this->getUserStatus();
		
		if ($user_info == null) {
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
		
		if (strtotime($assignment_info["start"]) > time() && !$user_info["role"]["permissions"]["manage"]) {
			$error = array(
				"error" => "access_unopened_assignment",
				"error_description" => "The assignment is not opened yet."
			);
			$base->set("error", $error);
			$this->setView("error.html");
			return;
		}
		
		$submissionInfo = $Assignment->getAllSubmissionsOf($user_info["user_id"], $params["id"]);
		
		$base->set("me", $user_info);
		$base->set("assignment_info", $assignment_info);
		$base->set("submissions", $submissionInfo);
		$this->setView("assignment.html");
	}
	
	/**
	 * Save the submitted file of an assignment.
	 * 
	 * Return JSON object to HTTP client.
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
			return;
		}
		
		// this is very inefficient, needs improving.
		$submission_info = $Assignment->getAllSubmissionsOf($user_info["user_id"], $assignment_id);
		$count = $Assignment->countSubmissions($submission_info, $assignment_info);
		$submission_record = null;
		if (!$user_info["role"]["permissions"]["submit"]) {
			$result = "permission_denied";
		} else if (strtotime($assignment_info["close"]) <= time() && !$user_info["role"]["permissions"]["submit_overdue"]) {
			$result = "assignment_closed";
		} else if ($count["remaining"] <= 0 && !$user_info["role"]["permissions"]["manage"]) {
			$result = "insufficient_quota";
		} else if (strtotime($assignment_info["start"]) > time() && !$user_info["role"]["permissions"]["submit_prestart"]) {
			$result = "assignment_unavailable";
		} else {
			// change submission dir temporarily
			$base->set("UPLOADS", $base->get("UPLOADS") . $assignment_info["id"] . "/" . $user_info["user_id"] . "/");
			$result = $Assignment->saveSubmission($user_info, $assignment_info);
			if (is_array($result)) {
				$submission_record = $result;
				$result = "success";
			} else if (empty($result)) {
				// what if $result is null?
			}
		}
		
		$data = array("error" => $result);
		switch ($result) {
			case "success":
				// call grader daemon
				$base->set("grade_str", "N/A");
				$base->set("data", $submission_record);
				$data["new_record_data"] = \View::instance()->render("submission_record.html");
				
				$Connector = \models\Connector::instance();
				$assign_result = $Connector->assignTask($submission_record, $user_info, $assignment_info);
				if ($assign_result["result"] == "queued") {
					$data["more_status"] = "queued";
					$Assignment->addLog($submission_record, "Queued with id " . $assign_result["queued_id"] . ".");
					$Assignment->updateSubmission($submission_record);
				}
				else $data["more_status"] = "error";
				break;
			case "permission_denied":
				$data["error_description"] = "You do not have the permission to submit.";
				break;
			case "assignment_closed":
				$data["error_description"] = "The assignment has closed.";
				break;
			case "insufficient_quota":
				$data["error_description"] = "You have run out of submission chances for this period.";
				break;
			case "assignment_unavailable":
				$data["error_description"] = "The assignment is not available for submission.";
				break;
			case "invalid_ext_name":
				$data["error_description"] = "The submitted file has a wrong extension name.";
				break;
			case "empty_ext_name":
				$data["error_description"] = "The submitted file does not have an extension name.";
				break;
			case "file_too_large":
				$data["error_description"] = "The submitted file is too large.";
				break;
			case "upload_error":
				$data["error_description"] = "An error occurred uploading this file. Please retry or contact admin.";
				break;
			default:
				$data["error_description"] = "An unknown error occured.";
				break;
		}
		$this->json_echo($data);
	}
	
	/**
	 * Print the source file of a submission record to HTTP client.
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
