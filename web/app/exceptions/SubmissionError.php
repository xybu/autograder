<?php

namespace exceptions;

class SubmissionError extends \Exception {

	protected $error;

	public function __construct($error, $message, $code = 0, \Exception $prev = null) {
		parent::__construct($message, $code, $prev);
		$this->error = $error;
	}
	
	public function toArray() {
		return array(
				"error" => $this->error,
				"error_description" => $this->message
		);
	}
}
