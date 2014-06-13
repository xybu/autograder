<?php
/**
 * The PHP-daemon layer.
 * 
 * 
 * @author	Xiangyu Bu <xybu92@live.com>
 */

namespace models;

class GraderDaemon extends \Model {
	
	protected $server_list;
	
	function __construct() {
		parent::__construct();
		$this->server_list = json_decode(file_get_contents($this->Base->get("DATA_PATH") . "/daemons.json"), true);
	}
	
	function assignTask($submission_record) {
	}
	
}
