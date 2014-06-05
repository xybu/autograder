<?php

class Model extends Prefab {
	
	protected $Base = null;
	protected $cache = null;
	protected $db = null;
	
	function __construct() {
		$this->Base = Base::instance();
	}
	
}
