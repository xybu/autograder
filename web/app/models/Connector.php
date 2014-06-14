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
	
	function assignTask($submission_record, $assignment_info = null) {
		$server_info = $this->server_list[0];
		$errno = 0;
		$errstr = "";
		$fp = fsockopen($server_info["host"], $server_info["port"], $errno, $errstr);
		
		if ($errno > 0 || $fp === false) {
			// failed to connect to the daemon
			return false;
		}
		
		$data = array(
			"api_key" => $this->Base->get("API_KEY"),
			"protocol_type" => "path",	// assuming path for now
			"src_file" => $submission_record["file_path"],
			"priority" => 100,
			"assignment" => $assignment_info
		);
		
		fwrite($fp, json_encode($data));
		while (!feof($fp)) {
			echo fgets($fp, 128);
		}
		fclose($fp);
		
	}
	
}
