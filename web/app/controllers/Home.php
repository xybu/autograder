<?php

namespace controllers;

class Home extends \Controller {

	function showHomePage($base) {
		if ($base->exists("SESSION.user"))
			$base->reroute("/assignments");
		$this->setView("signin.html");
	}
	
	function showRetrievePasswordPage($base) {
		
	}
	
	function retrievePassword($base) {
		
	}
	
	function showSupportPage($base) {
		
	}	
	
}
