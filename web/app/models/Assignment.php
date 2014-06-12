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
	
	/**
	 * Fetch all the submissions of a user to an assignment.
	 * 
	 * 
	 */
	function getAllSubmissionsOf($userId, $assignmentId) {
		$result = $this->query("SELECT * FROM submissions WHERE user_id=:uid AND assignment_id=:aid", array(
			':uid' => $userId,
			':aid' => $assignmentId
		));
		return $result;
	}
	
	/**
	 * Fetch the info of a submission record given its ID.
	 *
	 * @param	$sid: the ID of a submission record, corresponding to the 'id' field
	 * 		      of the 'submissions' table in the database.
	 * 
	 * @return	a row in the 'submissions' table whose id equals $sid; otherwise, null.
	 */
	function findSubmissionById($sid) {
		$result = $this->query("SELECT * FROM submissions WHERE id=?", $sid);
		if (count($result) == 1) return $result[0];
		return null;
	}
	
	/**
	 * Given a list of submissions (queried from db), count them by date, week, etc.
	 *
	 * @param	$submissions: a query result from submission history. 
	 * 		              It must contain a key named "date_created" for evaluating.
	 * @param	$assignment_info: the assignment information array. If not null,
	 * 		                  function will calculate the remaining submission chances.
	 * 		$assignment_info["quota_strategy"]: one of 'daily', 'weekly', 'total'.
	 * 		$assignment_info["quota_amount"]: the quota amount.
	 * 
	 * @return	An array with keys 'today', 'this_week", 'total', whose values are the
	 * 		counts within their time frames.
	 * 		If $strategy is not null, the key named 'remaining' will be given.
	 */
	function countSubmissions($submissions, $assignment_info = null) {
		$count = array(
			"today" => 0,
			"this_week" => 0,
			"total" => 0
		);
		foreach ($submissions as $item) {
			$t = strtotime($item["date_created"]);
			if ($t > strtotime("yesterday")) {
				$count["today"]++;
			}
			if ($t > strtotime("Monday this week")) {
				$count["this_week"]++;
			}
			$count["total"]++;
		}
		$strategy = $assignment_info["quota_strategy"];
		if ($strategy != null) {
			if ($strategy == "daily") {
				$count["remaining"] = $assignment_info["quota_amount"] - $count["today"];
			} else if ($strategy == "weekly") {
				$count["remaining"] = $assignment_info["quota_amount"] - $count["this_week"];
			} else if ($strategy == "total") {
				$count["remaining"] = $assignment_info["quota_amount"] - $count["total"];
			}
		}
		return $count;
	}
	
	function saveSubmission($user, $assignmentId) {
		
	}
}
