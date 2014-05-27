<?php

namespace controllers;

class Home extends \Controller {

	function showHomePage($base) {
		$this->setView("signin.html");
	}
	
}
