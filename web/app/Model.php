<?php

class Model extends \Prefab {
	
	protected $base = null;
	protected $cache = null;
	protected $db = null;
	
	function query($cmds, $args = null, $ttl=0, $log = true) {
		if ($this->db == null){
			if (\Registry::exists("db")) {
				$this->db = \Registry::get("db");
			} else {
				$this->db = new \DB\SQL("mysql:host=" . $this->base->get("DB_HOST") . ";port=" . $this->base->get("DB_PORT") . ";dbname=" . $this->base->get("DB_NAME") . "", $this->base->get("DB_USER"), $this->base->get("DB_PASS"));
				\Registry::set('db', $this->db);
			}
		}
		return $this->db->exec($cmds, $args, $ttl, $log);
	}
	
}

