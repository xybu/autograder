<?php
/**
 * Controller.php
 *
 * The base controller class
 *
 * @author	Xiangyu Bu (xybu92@live.com)
 */

class Controller {

	// Cache is a singleton
	protected $cache;
	protected $base;
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
		$this->user = \models\User::instance();
		
		if (!$this->base->exists("COOKIE.ugl_user"))
			throw new \Exception("You should log in to perform the request", 1);
		
		$cookie_user = self::api_decrypt($this->base->get("COOKIE.ugl_user"), $this->base->get("API_SERVER_KEY"));
		if (empty($cookie_user)) throw new \Exception("Unauthorized request", 2);
		
		$cookie_user = unserialize($cookie_user);
		$user_id = $cookie_user["user_id"];
		$token = $cookie_user["ugl_token"];
		$user_info = $this->user->findById($user_id);
		if (empty($user_info) or !$this->user->token_verify($user_info, $token, self::TOKEN_VALID_HRS))
			throw new \Exception("Unauthorized request", 2);
		
		return array("user_id" => $user_id, "user_info" => $user_info, "ugl_token" => $token);
	}
	
	function setUserStatus($id, $token){
		$user_creds = array("user_id" => $id, "ugl_token" => $token);
		$this->base->set("COOKIE.ugl_user", self::api_encrypt(serialize($user_creds), $this->base->get("API_SERVER_KEY")), self::TOKEN_VALID_HRS * 3600);
	}
	
	function voidUserStatus(){
		$this->base->clear("COOKIE.ugl_user");
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
