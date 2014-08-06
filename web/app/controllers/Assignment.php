<?php

namespace controllers;

class Assignment extends \Controller {
	
	const MAX_DOWNLOAD_SPEED = 16; // in kBps
	
	function __construct() {
		$this->User = \models\User::instance();
	}
	
	public function showDetailOf($base, $params) {
		// verify user
		$user_info = $this->get_user_status();
		
		try {
			if ($user_info == null)
				throw new \exceptions\AuthError('not_logged_in', 'You need to log in to perform the request.');
			
			$Assignment = \models\Assignment::instance();
			
			// verify assignment
			$assignment_info = $Assignment->findById($params["id"]);
			if ($assignment_info == null)
				throw new \exceptions\ActionError('assignment_not_found', 'The assignment requested does not exist.');
			
			// if the assignment has opened
			if (strtotime($assignment_info['start']) > time() && !$user_info["role_info"]['submit_prestart']) 
				throw new \exceptions\PermissionError('invalid_request', 'The assignment is not open.');
			
			$submission_info = $Assignment->getAllSubmissionsOf($user_info["user_id"], $params["id"]);
			$count = $Assignment->countSubmissions($submission_info, $assignment_info);
		
			$base->set("me", $user_info);
			$base->set("count", $count);
			$base->set("assignment_info", $assignment_info);
			$base->set("submissions", $submission_info);
			$this->set_view("ajax_assignment.html");
			
		} catch (\exceptions\AuthError $e) {
			$this->echo_json($e->toArray(), true);
		} catch (\exceptions\ActionError $e) {
			$this->echo_json($e->toArray(), true);
		} catch (\exceptions\PermissionError $e) {
			$base->set("error", $e->toArray());
			$this->set_view("error.html");
		}
	}
	
	/**
	 * Save the submitted file of an assignment.
	 * 
	 * Return JSON object to HTTP client.
	 */
	public function submitFile($base) {
		$user_info = $this->get_user_status();
		$new_record = null;
		try {
			if ($user_info == null)
				throw new \exceptions\AuthError('not_logged_in', 'You need to log in to perform the request.');
			
			$Assignment = \models\Assignment::instance();
			
			// the assignment should exist
			$assignment_id = $base->get('POST.assignment_id');
			$assignment_info = $Assignment->findById($params['id']);
			if ($assignment_info == null)
				throw new \exceptions\ActionError('assignment_not_found', 'The assignment requested does not exist.');
			
			// the user should have submit permission
			if (!$user_info['role_info']['submit'])
				throw new \exceptions\PermissionError('permission_denied', 'You do not have the permission to submit.');
			
			// should be able to submit pastdue if needed
			if (strtotime($assignment_info["close"]) <= time() && !$user_info["role_info"]["submit_overdue"])
				throw new \exceptions\PermissionError('permission_denied', 'The assignment has closed.');
			
			// should be able to submit prior to start if needed
			if (strtotime($assignment_info['start']) > time() && !$user_info["role_info"]['submit_prestart']) 
				throw new \exceptions\PermissionError('permission_denied', 'The assignment is not open.');
			
			$submission_info = $Assignment->getAllSubmissionsOf($user_info["user_id"], $assignment_id);
			$count = $Assignment->countSubmissions($submission_info, $assignment_info);
			
			// should have enough quota
			if ($count["remaining"] <= 0 && !$user_info["role_info"]["override_quota"])
				throw new \exceptions\PermissionError('insufficient_quota', 'You have used up your submission quota. Please wait for the quota to renew.');
			
			// change submission dir temporarily
			$base->set("UPLOADS", $base->get("UPLOADS") . $assignment_info["id"] . "/" . $user_info["user_id"] . "/");
			
			$new_record = $Assignment->saveSubmission($user_info, $assignment_info);
			
			if (!is_array($new_record))
				throw new \exceptions\ActionError('record_not_found', 'The submission record was not created correctly.');
			
			// saved successfully, contact grader daemon
			$Connector = \models\Connector::instance();
			$queued_id = $Connector->assignTask($new_record, $user_info, $assignment_info);
			$Assignment->addLog($new_record, "Queued with id " . $queued_id . ".");
			$Assignment->updateSubmission($new_record);
			
			$this->echo_success('The file has been successfully submitted and queued.');
			
		} catch (\exceptions\AuthError $e) {
			$this->echo_json($e->toArray());
		} catch (\exceptions\ActionError $e) {
			$this->echo_json($e->toArray());
		} catch (\exceptions\PermissionError $e) {
			$this->echo_json($e->toArray());
		} catch (\exceptions\FileError $e) {
			$this->echo_json($e->toArray());
		} catch (\exceptions\ProtocolError $e) {
			$error_info = $e->toArray();
			$Assignment->addLog($new_record, "Encountered error: " . $error_info['error'] . ". Description: " . $error_info['error_description']);
			$Assignment->updateSubmission($new_record);
			
			$this->echo_success('The file has been successfully submitted. However, there is some trouble to have the grading work queued. Please contact admin.');
		}
	}
	
	/**
	 * Print the source file of a submission record to HTTP client.
	 * 
	 */
	function getFile($base, $params) {
		$user_info = $this->get_user_status();
		try {
			if ($user_info == null)
				throw new \exceptions\AuthError('not_logged_in', 'You need to log in to perform the request.', 403);
			
			$submission_id = $params["submission_id"];
			if (!is_numeric($submission_id))
				throw new \exceptions\ActionError("invalid_request", "Your request is refused because it contains invalid information.", 403);
			
			$Assignment = \models\Assignment::instance();
			
			$submission_info = $Assignment->findSubmissionById($submission_id);
			if ($submission_info == null)
				throw new \exceptions\ActionError("submission_not_found", "There is no record for this submission.", 404);
			
			// only the submitter and the admin can fetch the src file.
			if ($submission_info["user_id"] != $user_info["user_id"] && !$user_info["role_info"]["manage"])
				throw new \exceptions\PermissionError("permission_denied", "You are not allowed to fetch this submission.", 403);
			
			if (!file_exists($submission_info["file_path"]))
				throw new \exceptions\FileError("file_not_found", "The file you are requesting is not found in the repository.", 404);
			
			\Web::instance()->send($submission_info["file_path"], "application/octet-stream", self::MAX_DOWNLOAD_SPEED);
			
		} catch (\exceptions\Error $e) {
			if ($e->getCode() == 404) header("HTTP/1.0 404 Not Found");
			else if ($e->getCode() == 403) header("HTTP/1.0 403 Forbidden");
			
			if ($user_info != null) $base->set("me", $user_info);
			$base->set("error", $e->toArray());
			$this->set_view("error.html");
		}
	}
	
	function getLog($base, $params) {
		$user_info = $this->get_user_status();
		try {
			if ($user_info == null)
				throw new \exceptions\AuthError('not_logged_in', 'You need to log in to perform the request.', 403);
			
			$submission_id = $params["submission_id"];
			if (!is_numeric($submission_id))
				throw new \exceptions\ActionError("invalid_request", "Your request is refused because it contains invalid information.", 403);
			
			$Assignment = \models\Assignment::instance();
			
			$submission_info = $Assignment->findSubmissionById($submission_id);
			if ($submission_info == null)
				throw new \exceptions\ActionError("submission_not_found", "There is no record for this submission.", 404);
			
			$assignment_info = $Assignment->findById($submission_info["assignment_id"]);
			
			if ($submission_info["user_id"] != $user_info["user_id"] && !$user_info["role_info"]["manage"])
				throw new \exceptions\PermissionError("permission_denied", "You are not allowed to fetch this submission.", 403);
			
			$base->set("me", $user_info);
			$base->set("assignment_info", $assignment_info);
			$base->set("submission_info", $submission_info);
			$this->set_view("ajax_log.html");
			
		} catch (\exceptions\Error $e) {
			if ($e->getCode() == 404) header("HTTP/1.0 404 Not Found");
			else if ($e->getCode() == 403) header("HTTP/1.0 403 Forbidden");
			
			$error = $e->toArray();
			$error['error_level'] = 'danger';
			$base->set("error", $error);
			$this->set_view("ajax_error_modal.html");
		}
	}
	
}
