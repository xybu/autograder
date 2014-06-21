<?php

namespace controllers;

class Admin extends \Controller {

	function showAdminHomepage($base) {
		$user_info = $this->getUserStatus();
		if ($user_info == null)
			$this->json_echo(array("error" => "not_logged_in", "error_description" => "You need to log in to perform the request."), true);
		
		if (!$user_info["role"]["permissions"]["manage"])
			$this->json_echo(array("error" => "permission_denied", "error_description" => "You cannot access admin panel."), true);
			
		$this->setView("admincp.html");
	}

}
