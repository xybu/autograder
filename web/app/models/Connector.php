<?php
/**
 * The PHP-daemon connector layer.
 * 
 * 
 * @author	Xiangyu Bu <xybu92@live.com>
 */

namespace models;

class Connector extends \Model {
	
	protected $server_list;
	
	function __construct() {
		parent::__construct();
		$this->server_list = json_decode(file_get_contents($this->Base->get("DATA_PATH") . "/daemons.json"), true);
	}
	
	/**
	 * Choose a proper server from the list given the user role and assignment info.
	 * This function is unfinished.
	 * 
	 */
	protected function chooseServer($user_info = null, $assignment_info = null) {
		foreach ($this->server_list as $alias => $info) {
			return $info;
		}
	}
	
	function assignTask($submission_record, $user_info, $assignment_info) {
		$server_info = $this->chooseServer($user_info, $assignment_info);
		if (true) {
			return $this->assignTaskByPath($server_info, $submission_record, $user_info, $assignment_info);
		}
	}
	
	/**
	 * Send the grading task to a grader daemon.
	 * 
	 * @param	$submission_record: a record from submissions table.
	 * @param	$assignment_info: the assignment data array.
	 * 
	 * @return	an array with keys 'result' and 'description' where ['result'] equals '*_error'
	 * 		if any error occurs.
	 */
	function assignTaskByPath($server_info, $submission_record, $user_info, $assignment_info) {
		$errno = 0;
		$errstr = "";
		$fp = @fsockopen($server_info["host"], $server_info["port"], $errno, $errstr);
		
		if (!$fp) {
			// failed to connect to the daemon
			return array("result" => "connection_error", "description" => $errstr . "(" . $errno . ")");
		}
		
		$data = array(
			"submission_id" => $submission_record["id"],
			"api_key" => $this->Base->get("API_KEY"),
			"protocol_type" => "path",	// assuming path for now
			"src_file" => $submission_record["file_path"],
			"user_id" => $user_info["user_id"],
			"priority" => $user_info["role"]["permissions"]["submit_priority"],
			"assignment" => $assignment_info
		);
		fwrite($fp, json_encode($data) . "\r\n");
		
		$ret = "";
		while (!feof($fp)) {
			$ret = $ret . fgets($fp, 256);
		}
		fclose($fp);
		
		$ret = json_decode($ret, true);
		if (is_array($ret)) {
			if (array_key_exists("error", $ret)) {
				// there is an error
				return array("result" => "error");
			} else {
				// successfully queued.
				$this->query(
					"UPDATE submissions SET status='queued', log=CONCAT(log, :new_log) WHERE id=:id", 
					array(
						":new_log" => "[" . date('c') . "] submission is queued as Task " . $ret["queued_id"] . " on server " . $server_info["host"] . ":" . $server_info["port"] . ".\n", 
						":id" => $submission_record["id"]
					)
				);
				return array("result" => "queued");
			}
		}
	}
	
	function assignTaskByFile($submission_record, $assignment_info) {
	}
}
