<?php

namespace controllers;

class Callback extends \Controller {
	
	/**
	 * Handle an incoming HTTP callback request.
	 * 
	 * The POST fields are: grader_key, type = {'start', 'done'}
	 * 	if type == 'start':
	 * 		submission_id, 
	 * 	if type == 'done':
	 * 		submission_id, 
	 * 		protocol_type: {'path', 'base64'}
	 * 		grade_detail (base64_encoded): the index-grade pairs, with 'total' key marking the total grade, in JSON.
	 * 		dump_file, 
	 * 		internal_log (base64_encoded), 
	 * 		formal_log (base64_encoded)
	 *		error (if any error occurs)
	 */
	
	function handle($base) {
		$Connector = \models\Connector::instance();
		$Assignment = \models\Assignment::instance();
		
		try {
			
			$grader_key = $base->get("POST.grader_key");
			if (empty($grader_key) || $Connector->findServerByKey($grader_key) == null)
				throw new \exceptions\AuthError('invalid_request', 'The grader key is not registered.');
			
			$callback_type = $base->get('POST.type');
			$submission_id = $base->get('POST.submission_id');
			$submission_info = $Assignment->findSubmissionById($submission_id);
			if ($submission_info == null)
				throw new \exceptions\ActionError('submission_not_found', 'The target submission record does not exist.');
			
			if ($callback_type == 'start') {
				// for start callback, update the status string
				$submission_info['status'] = 'being graded';
				$Assignment->addLog($submission_info, "Started grading.");
				$Assignment->updateSubmission($submission_info);
				$this->echo_success('The start callback has been successfully handled.');
			} else if ($callback_type == 'done') {
				// for finish callback, update the whole record
				$Assignment->addLog($submission_info, "Grading finished.");
				
				if ($base->exists('POST.error')) {
					// an error is reported to have occurred
					$error = $base->get('POST.error');
					$error_description = $base->get('POST.error_description');
					$submission_info['status'] = 'error';
					$Assignment->addLog($submission_info, "Grader reported error \"" . $error . "\" (" . $error_description . ").");
				} else {
					$protocol_type = $base->get("POST.protocol_type");
					$grade_detail = base64_decode($base->get("POST.grade_detail"));
					$dump_file = $base->get("POST.dump_file");
					$grader_internal_log = base64_decode($base->get("POST.internal_log"));
					$grader_formal_log = base64_decode($base->get("POST.formal_log"));
					$grade_detail_data = json_decode($grade_detail, true);
					
					$path = $base->get("UPLOADS") . '/' . $submission_info["assignment_id"] . '/' . $submission_info["user_id"] . '/dumps';
					if (!file_exists($path)) mkdir($path, 0777, true);
					
					// if the file transferred is by path
					if ($protocol_type == 'path') {
						if (!copy($dump_file, $path . "/submission_" . $submission_info["id"] . "_dump.tar.gz")) {
							$Assignment->addLog($submission_info, "Failed to copy the grading dump file \"" . $dump_file . "\" to submission pool.");
						}
					} else if ($protocol_type == "base64") {
						$fp = fopen($path . "/submission_" . $submission_info["id"] . "_dump.tar.gz");
						fwrite($fp, base64_decode($dump_file));
						fclose($fp);
					} else throw new \exceptions\ActionError('unsupported_protocol_type', 'The file transfer protocol is not supported.');
				
					$submission_info["status"] = "graded";
					$submission_info["grade"] = $grade_detail_data["total"];
					$submission_info["grade_detail"] = json_encode($grade_detail_data);
					$submission_info["grader_formal_log"] = $grader_formal_log;
					$submission_info["grader_internal_log"] = $grader_internal_log;
				}
				
				$Assignment->updateSubmission($submission_info);
				$this->echo_success('The finish callback has been successfully handled.');
			
			} else
				throw new \exceptions\ActionError('unsupported_callback_type', 'The callback type "' . $callback_type . '" is not supported.');
			
		} catch (\exceptions\AuthError $e) {
			$this->echo_json($e->toArray());
		} catch (\exceptions\ActionError $e) {
			$this->echo_json($e->toArray());
		}
	}
	
}
