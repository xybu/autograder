<?php

class Model extends \Prefab {
	
	protected $Base = null;
	protected $Cache = null;
	protected $db = null;
	
	function __construct() {
		$this->Base = \Base::instance();
		$this->Cache = \Cache::instance();
	}
	
	function query($cmds, $args = null, $ttl = 0, $log = true) {
		if ($this->db == null){
			if (\Registry::exists("db")) {
				$this->db = \Registry::get("db");
			} else {
				$this->db = new \DB\SQL("mysql:host=" . $this->Base->get("DB_HOST") . ";port=" . $this->Base->get("DB_PORT") . ";dbname=" . $this->Base->get("DB_NAME") . "", $this->Base->get("DB_USER"), $this->Base->get("DB_PASS"));
				\Registry::set('db', $this->db);
			}
		}
		return $this->db->exec($cmds, $args, $ttl, $log);
	}
	
	function to_mysql_wildcard($str) {
		$str = str_replace('*', '%', $str);
		$str = str_replace('?', '_', $str);
		$str = str_replace('"', '\"', $str);
		return $str;
	}
	
}
