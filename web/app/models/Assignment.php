<?php

namespace models;

class Assignment extends \Model {
	
	protected $assignments;
	protected $baseDir;
	
	function __construct() {
		parent::__construct();
		$this->assignments = json_decode(file_get_contents("data/assignments.json"), true);
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
	
	function getListOfSubmissions($user, $assignmentId) {
		/*	
		$fileList = scandir($dir);
		if ($todayOnly == 1) $today = date("Y-m-d");
		else $today = "";
	
		foreach($fileList as $fileName) {
			if (strpos($fileName, "submission.archive." . $today) === 0) {
				++$i;
			}
		}
		*/
	}
	
	function saveSubmission($user, $assignmentId) {
		
	}
	
}
