<?php
/**
 * Controller.php
 *
 * The base controller class
 *
 * @author	Xiangyu Bu (xybu92@live.com)
 */

class Controller {
	
	protected $cache;	// Cache is a singleton
	protected $base;	// Base is also a singleton
	protected $user;
	protected $view = null;
	
	static function api_encrypt($str, $key){
		return openssl_encrypt($str, "AES-256-ECB", $key);
	}
	
	static function api_decrypt($str, $key){
		$trial = openssl_decrypt($str, "AES-256-ECB", $key);
		if (!$trial) return null;
		return $trial;
	}
	
	function __construct() {
		$this->cache = \Cache::instance();
	}
	
	function setView($filename){
		$this->view = $filename;
	}
	
	//! HTTP route pre-processor
	function beforeRoute($base) {
		$this->base=$base;
	}

	//! HTTP route post-processor
	function afterRoute($base) {
		if ($this->view)
			echo View::instance()->render($this->view);
	}
	
	function getUserStatus(){
		if ($this->base->exists("SESSION.user"))
			return $this->base->get("SESSION.user");
		return null;
	}
	
	// a SESSION-based login credential
	// because of the nature of the project, 
	// the goal is to save as little information on the client as possible
	function setUserStatus($userData){
		$this->base->set("SESSION.user", $userData);
	}
	
	function voidUserStatus(){
		$this->base->clear("SESSION.user");
	}
	
	function json_echo($array_data, $http_forbidden = false){
		$s = json_encode($array_data , JSON_PRETTY_PRINT);
		if ($http_forbidden)		
			header('HTTP/1.0 403 Forbidden');
		header("Content-Type: application/json");
		header("Cache-Control: no-cache, must-revalidate");
		header("Content-Length: " . strlen($s));
		echo $s;
		exit();
	}
}
