<?php

namespace models;

class Assignment extends \Model {
	
	protected $assignments;
	protected $baseDir;
	
	function __construct() {
		parent::__construct();
		$this->assignments = json_decode(file_get_contents($this->Base->get("DATA_PATH") . "/assignments.json"), true);
	}
	
	function getAllAssignments() {
		return $this->assignments;
	}
	
	function findById($key) {
		if (array_key_exists($key, $this->assignments)) {
			return $this->assignments[$key];
		}
		return null;
	}
	
	function setBaseDir($d) {
		$this->baseDir = d;
	}
	
	function getAllSubmissionsOf($userId, $assignmentId) {
		$result = $this->query("SELECT * FROM submissions WHERE user_id=:uid AND assignment_id=:aid", array(
			':uid' => $userId,
			':aid' => $assignmentId
		));
		return $result;
	}
	
	function saveSubmission($user, $assignmentId) {
		
	}
	
}
