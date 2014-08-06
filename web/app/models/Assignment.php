<?php
/**
 * \models\Assignment.php
 * 
 * The assignment and submission model.
 * Note that it throws FileError in some methods.
 * 
 * @author	Xiangyu Bu <xybu92@live.com>
 */

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
	
	function getDefaultAssignmentData() {
		return array(
			'display' => '',
			'start' => '',
			'close' => '',
			'quota_strategy' => 'daily',
			'quota_amount' => 15,
			'submit_filetype' => 'c',
			'submit_filesize' => 10240,
			'submit_notes' => '',
			'max_score' => 100,
			'grader_script' => '/',
			'grader_tar' => ''
		);
	}
	
	function findById($key) {
		if (array_key_exists($key, $this->assignments)) {
			$data = $this->assignments[$key];
			$data["id"] = $key;
			return $data;
		}
		return null;
	}
	
	function addAssignment($id, $data) {
		if (array_key_exists('id', $data)) unset($data['id']);
		$this->assignments[$id] = $data;
		return count($this->assignments);
	}
	
	function editAssignment($id, $data) {
		if (array_key_exists('id', $data)) unset($data['id']);
		$this->assignments[$id] = $data;
	}
	
	function deleteAssignment($id) {
		if (array_key_exists($id, $this->assignments)) {
			unset($this->assignments[$id]);
			return true;
		}
		return false;
	}
	
	function saveAssignments($table = null) {
		if ($table == null) $table = $this->assignments;
		return @file_put_contents($this->Base->get("DATA_PATH") . "assignments.json", json_encode($table), LOCK_EX);
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
		$result = $this->query("SELECT * FROM submissions WHERE user_id=:uid AND assignment_id=:aid ORDER BY date_created DESC", array(
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
	
	function findSubmissionByPath($p) {
		$result = $this->query("SELECT * FROM submissions WHERE file_path=?", $p);
		if (count($result) == 1) return $result[0];
		return null;
	}
	
	/**
	 * Fetch submission records of a specific assignment, one record per user,
	 * and the record is decided by $strategy, which is either 'highest' or 'latest'.
	 * Returns an array of 5-tuple of user_id, grade, grade_adjustment, grade_detail, id
	 */
	function getGradeBookRecords($assignment_info, $strategy) {
		$nested_sql = "";
		if ($strategy == 'highest') {
			$nested_sql = "grade=(SELECT MAX(s2.grade) FROM submissions s2 WHERE s1.user_id=s2.user_id AND s2.assignment_id=:assignment_id)";
		} else {
			$nested_sql = "date_created=(SELECT s2.date_created FROM submissions s2 WHERE s1.user_id=s2.user_id AND s2.assignment_id=:assignment_id ORDER BY s2.date_created DESC LIMIT 1)";
		}
		return $this->query("SELECT user_id, grade, grade_adjustment, grade_detail, id  FROM submissions s1 WHERE $nested_sql AND s1.assignment_id=:assignment_id ORDER BY user_id ASC", array(
			':assignment_id' => $assignment_info['id']
		));
	}
	
	/**
	 * Update a submission record in database given the submission data array.
	 * 
	 * @param	$s: an array previously returned by a findSubmission* function.
	 */
	function updateSubmission(&$s) {
		$this->query("UPDATE submissions SET " . 
				"user_id=:user_id, " . 
				"assignment_id=:assignment_id, " .
				"file_path=:file_path, " . 
				"status=:status, " . 
				"date_updated=NOW(), " . 
				"grade=:grade, " .
				"grade_adjustment=:grade_adjustment, " .
				"grade_detail=:grade_detail, " .
				"grader_formal_log=:grader_formal_log, " . 
				"grader_internal_log=:grader_internal_log, " . 
				"web_internal_log=:web_internal_log " . 
				"WHERE id=:id LIMIT 1;", 
			array(
				":id" => $s["id"],
				":user_id" => $s["user_id"],
				":assignment_id" => $s["assignment_id"],
				":file_path" => $s["file_path"],
				":status" => $s["status"],
				":grade" => $s["grade"],
				":grade_adjustment" => $s["grade_adjustment"],
				":grade_detail" => $s["grade_detail"],
				":grader_formal_log" => $s["grader_formal_log"],
				":grader_internal_log" => $s["grader_internal_log"],
				":web_internal_log" => $s["web_internal_log"]
			)
		);
	}
	
	function addLog(&$s, $log_str) {
		$s["web_internal_log"] = $s["web_internal_log"] . "[" . date("c") . "] " . $log_str . "\n";
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
	
	/**
	 * Save the submitted file to disk.
	 * 
	 * @param	$user_info: the array of the submitter (returned by User::findById).
	 * @param	$assignment_info: the data array of the assignment (returned by findById).
	 *
	 * @throws	\exceptions\FileError if any error occurs.
	 * 
	 * @return	the newly created submission record.
	 */
	function saveSubmission($user_info, $assignment_info) {
		
		$Web = \Web::instance();
		$overwrite = true;
		$slug = true;
		$count = 0;
		$error = '';
		
		$files = $Web->receive(
			function($file) use ($user_info, $assignment_info, &$count, &$error) {
				/* $file looks like:
					array(5) {
						["name"] =>     string(19) "csshat_quittung.png"
						["type"] =>     string(9) "image/png"
						["tmp_name"] => string(14) "/tmp/php2YS85Q"
						["error"] =>    int(0)
						["size"] =>     int(172245)
						}
					and $file['name'] already contains the slugged name
				*/
				
				if ($file['error'] > 0) {
					$error = 'upload_error';
					return false;
				}
				
				// accept at most ONE file per submission request
				if ($count > 0) return false;
				
				// if there is file size limit, check it
				if (array_key_exists('submit_filesize', $assignment_info) && $file['size'] > $assignment_info['submit_filesize']) {
					$error = 'file_too_large';
					return false;
				}
				
				$file_ext = pathinfo($file["name"], PATHINFO_EXTENSION);
				// files are required to have an extension name
				if ($file_ext == '') {
					$error = 'empty_ext_name';
					return false;
				}
				
				// there is file extension name limit, check it
				if (array_key_exists("submit_filetype", $assignment_info)) {
					$accepted_ext = "," . str_replace(" ", "", $assignment_info["submit_filetype"]) . ",";
					$accepted_ext = str_replace(",,", ",", $accepted_ext);
					if (strpos($accepted_ext, "," . $file_ext . ",") === false) {
						$error = "invalid_ext_name";
						return false;
					}
				}
				
				// move the file from php tmp to upload tmp
				++$count;
				return true;
			},
			$overwrite,
			$slug
		);
		
		if ($error != '') {
			$error_desc = '';
			switch ($error) {
				case 'upload_error':
					$error_desc = 'File was refused by the server.';
					break;
				case 'file_too_large':
					$error_desc = 'The file size exceeds limit.';
					break;
				case 'empty_ext_name':
					$error_desc = 'The file does not have an extension name.';
					break;
				case 'invalid_ext_name':
					$error_desc = 'The file extension is not accepted.';
					break;
			}
			throw new \exceptions\FileError($error, $error_desc);
		}
		
		if ($count != 1)
			throw new \exceptions\FileError('unknown_error', 'You can submit only one file at a time.');
		
		// $files is an array of filename-status pairs
		foreach ($files as $name => $status) {
			if ($status) {
				// 'history' is safe because $name must have a '.' in the file name
				$path = dirname($name) . "/history/";
				if (!file_exists($path)) mkdir($path, 0777);
				
				$file_ext = pathinfo($name, PATHINFO_EXTENSION);
				$file_name_new = "archive." . date('c') . "." . $file_ext;
				rename($name, $path . $file_name_new);
				copy($path . $file_name_new, $path . "../latest.archive");
				
				// add to database
				$this->query(
					"INSERT INTO submissions (user_id, assignment_id, file_path, status, date_created, date_updated) VALUES (:user_id, :assignment_id, :file_path, 'submitted', NOW(), NOW()); ",
					array(
						':user_id' => $user_info["user_id"],
						':assignment_id' => $assignment_info["id"],
						':file_path' => $path . $file_name_new
					)
				);
				return $this->findSubmissionByPath($path . $file_name_new);
			}
		}
		
		return null;
	}
	
	/**
	 * Fetch all submission records that satisfy the $cond.
	 * 
	 */
	function findSubmissions($cond, $strategy = '') {
		
		$sql_cond = array();
		
		if (array_key_exists(':user_id_pattern', $cond)) {
			$sql_cond[] = "s1.user_id LIKE :user_id_pattern";
			$cond[':user_id_pattern'] = $this->to_mysql_wildcard($cond[':user_id_pattern']);
		}
		
		if (array_key_exists(':date_created_start', $cond))
			$sql_cond[] = "s1.date_created >= :date_created_start";
		
		if (array_key_exists(':date_updated_start', $cond))
			$sql_cond[] = "s1.date_updated >= :date_updated_start";
		
		if (array_key_exists(':date_created_end', $cond))
			$sql_cond[] = "s1.date_created <= :date_created_end";
		
		if (array_key_exists(':date_updated_end', $cond))
			$sql_cond[] = "s1.date_updated <= :date_updated_end";
		
		if (array_key_exists(':grade_max', $cond))
			$sql_cond[] = "s1.grade <= :grade_max";
		
		if (array_key_exists(':grade_min', $cond))
			$sql_cond[] = "s1.grade >= :grade_min";
		
		if (array_key_exists(':assignment_id_set', $cond)) {
			$sql_cond[] = "s1.assignment_id IN (" . '"' . implode('","', $cond[':assignment_id_set']) . '"' . ")";
			unset($cond[':assignment_id_set']);
		}
		
		if (array_key_exists(':status_set', $cond)) {
			$sql_cond[] = "s1.status IN (" . '"' . implode('","', $cond[':status_set']) . '"' . ")";
			unset($cond[':status_set']);
		}
		
		if ($strategy == 'highest') {
			$sql_cond[] = "s1.grade=(SELECT MAX(s2.grade) FROM submissions s2 WHERE s1.user_id=s2.user_id AND s2.assignment_id=s1.assignment_id)";
		} else if ($strategy == 'latest') {
			$sql_cond[] = "s1.date_created=(SELECT s2.date_created FROM submissions s2 WHERE s1.user_id=s2.user_id AND s2.assignment_id=s1.assignment_id ORDER BY s2.date_created DESC LIMIT 1)";
		}
		
		$where = '';
		if (count($sql_cond) > 0) $where = ' WHERE ' . implode(' AND ', $sql_cond);
		
		$sql = "SELECT * FROM submissions s1" . $where . " ORDER BY s1.user_id ASC";
		
		return $this->query($sql, $cond);
	}
	
	/**
	 * Remove the submission record from database, but do not remove the files.
	 */
	function deleteSubmission($id) {
		$this->query("DELETE FROM submissions WHERE id=? LIMIT 1;", $id);
	}
	
	function isValidIdentifier($str) {
		return preg_match("/\\s/", $str) == 0;
	}
	
	function isValidFilePath($str) {
		return is_file($str);
	}
}
