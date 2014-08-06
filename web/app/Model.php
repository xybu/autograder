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
	
	function get_rand_str($len) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		return substr(str_shuffle(substr(str_shuffle($chars), 0, $len / 2 + 1) . substr(str_shuffle($chars), 0, $len / 2 + 1)), 0, $len);
	}
	
}
