<?php

namespace controllers;

class User extends \Controller {
	
	protected $User = null;
	
	function __construct() {
		$this->User = \models\User::instance();
	}
	
	/**
	 * Sign in handler.
	 * TODO: the redirect hash does not work well for redirecting to admincp.
	 */
	function signIn($base) {
		try {
			$User = $this->User;
			$user_id = $base->get('POST.userid');
			$password = $base->get('POST.password');
			
			$user_info = $User->findByIdAndPassword($user_id, $password);
			if ($user_info == null)
				throw new \exceptions\AuthError('user_not_found', 'The user/password pair was not found.');
			
			if ($base->exists('SESSION.forgot_password'))
				$base->clear('SESSION.forgot_password');
			
			$this->set_user_status($user_info);
			
			if ($base->exists('POST.redirect_hash')) {
				$redirect_uri = $base->get('POST.redirect_hash');
				if (strpos($redirect_uri, '#') === false) $redirect_uri = "";
				else if (strpos($redirect_uri, '/admin/') !== false)
					$redirect_uri = '/admin' . $redirect_uri;
			} else $redirect_uri = '';
			
			$base->reroute('/' . $redirect_uri);
			
		} catch (\exceptions\AuthError $e) {
			$this->echo_json($e->toArray(), True);
		}
	}
	
	function logOut($base) {
		$this->set_user_status(null);
		$base->reroute('/');
	}
	
}
