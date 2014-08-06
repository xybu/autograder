<?php
/**
 * Controller.php
 *
 * The base controller class
 *
 * @author	Xiangyu Bu (xybu92@live.com)
 */

abstract class Controller {
	
	protected $base;
	protected $user;
	protected $view_name = null;
	
	function encrypt($str, $key){
		return openssl_encrypt($str, "AES-256-ECB", $key);
	}
	
	function decrypt($str, $key){
		$trial = openssl_decrypt($str, "AES-256-ECB", $key);
		if (!$trial) return null;
		return $trial;
	}
	
	/**
	 * Set the HTML view to render after routing.
	 */
	function set_view($viewName){
		$this->view_name = $viewName;
	}
	
	/**
	 * HTTP route pre-processor function, executed before a page is routed.
	 */
	function beforeRoute($base) {
		$this->base = $base;
	}

	/**
	 * HTTP route post-processor function, executed after a page is loaded
	 */
	function afterRoute($base) {
		if ($this->view_name) {
			echo View::instance()->render($this->view_name);
		}
	}
	
	/**
	 * The login status is stored in PHP session instead of cookies
	 * because the goal is to save as little information on clients as possible
	 */
	function get_user_status(){
		if ($this->base->exists("SESSION.user"))
			return $this->base->get("SESSION.user");
		return null;
	}
	
	/**
	 * Save the user status information.
	 * If the given data is null, log the user out.
	 */
	function set_user_status($userData){
		if ($userData == null) $this->base->clear("SESSION.user");
		else $this->base->set("SESSION.user", $userData);
	}
	
	function echo_success($msg, $extra_data = null) {
		if ($extra_data == null) $extra_data = array();
		$extra_data['status'] = 'success';
		$extra_data['message'] = $msg;
		$this->echo_json($extra_data);
	}
	
	function echo_json($array_data, $http_forbidden = false){
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
