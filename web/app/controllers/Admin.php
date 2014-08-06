<?php

namespace controllers;

class Admin extends \Controller {
	
	const PASSWORD_LEN = 12;
	const MAX_DOWNLOAD_SPEED = 256; // in kBps
	
	private function verifyAdminPermission($show_json_error = true) {
		$user_info = $this->get_user_status();
		if ($user_info == null || !$user_info["role_info"]["manage"]) {
			if ($show_json_error)
				$this->echo_json(array("error" => "permission_denied", "error_description" => "You cannot access admin panel."), true);
			else $this->base->reroute("/");
		}
		$this->user = $user_info;
		return $user_info;
	}
	
	function showAdminHomepage($base) {
		$this->verifyAdminPermission(false);
		$base->set('me', $this->user);
		$this->set_view('admincp.html');
	}
	
	function addAnnouncement($base) {
		$this->verifyAdminPermission();
		
		try {
			$title = $base->get('POST.title');
			$pubDate = $base->get('POST.pubDate');
			$link = $base->get('POST.link');
			$content = $base->get('POST.content');
			
			$Rss = new \models\Rss($base->get('DATA_PATH') . 'feed.xml');
			$Rss->add_item($title, $content, $link, $pubDate);
			if ($Rss->save() === false)
				throw new \exceptions\FileError('write_failure', "Failed to write data to \"" . realpath($base->get("DATA_PATH") . "feed.xml") . "\".");
			
			$this->echo_success('Successfully created a new announcement.');
			
		} catch (\exceptions\FileError $e) {
			$this->echo_json($e->toArray());
		}
	}
	
	function editAnnouncement($base) {
		
	}
	
	function deleteAnnouncement($base) {
		
	}
	
	function showAnnouncementsPage($base) {
		$this->verifyAdminPermission();
		$Rss = new \models\Rss($base->get('DATA_PATH') . "feed.xml");
		$base->set("announcements", $Rss->get_items());
		$base->set('me', $this->user);
		$this->set_view('admin/ajax_announcements.html');
	}
	
	function showAssignmentPage($base) {
		$this->verifyAdminPermission();
		$Assignment = \models\Assignment::instance();
		
		$base->set('me', $this->user);
		$base->set('assignment_list', $Assignment->getAllAssignments());
		$base->set('blank_item', $Assignment->getDefaultAssignmentData());
		$this->set_view('admin/ajax_assignments.html');
	}
	
	/**
	 * Fetch the grades for an assignment and render the records in the given format.
	 * The query string should contain three elements:
	 * 	assignment_id: the identifier of the assignment queried
	 * 	strategy: 'highest' or 'latest'
	 * 	format: 'csv' or 'html'
	 */
	function createGradeBook($base) {
		$this->verifyAdminPermission();
		$Assignment = \models\Assignment::instance();
		
		$assignment_id = $base->get('GET.assignment_id');
		$strategy = $base->get('GET.strategy');
		$format = $base->get('GET.format');
		
		try {
			$assignment_info = $Assignment->findById($assignment_id);
			
			if (empty($assignment_info))
				throw new \exceptions\ActionError('assignment_not_found', "The target assignment '$assignment_id' is not found.");
			
			if ($strategy != 'highest' && $strategy != 'latest')
				throw new \exceptions\ProtocolError('strategy_not_supported', "The target strategy '$strategy' is not supported.");
			
			if ($format != 'csv' && $format != 'html')
				throw new \exceptions\ProtocolError('format_not_supported', "The target format '$format' is not supported.");
			
			$base->set('assignment_id', $assignment_id);
			$base->set('records', $Assignment->getGradeBookRecords($assignment_info, $strategy));
			
			if ($format == 'csv') {
				$this->set_view('admin/gradebook_csv.php');
			} else if ($format == 'html') {
				$this->set_view('admin/gradebook_html.php');
			}
			
		} catch (\exceptions\Error $e) {
			$this->echo_json($e->toArray());
		}
	}
	
	function updateAssignment($base) {
		$this->verifyAdminPermission();
		
		$Assignment = \models\Assignment::instance();
		$id = $base->get('POST.id');
		$data = null;
		
		try {
			if ($base->get('POST.internal') == 'new') {
				if ($Assignment->findById($id) != null)
					throw new \exceptions\ActionError('identifier_taken', 'The assignment identifier is already used.');
				$data = $Assignment->getDefaultAssignmentData();
			} else {
				$data = $Assignment->findById($id);
				if ($data == null)
					throw new \exceptions\ActionError('id_not_found', 'The assignment is not found.');
			}
			
			if (!$Assignment->isValidIdentifier($id))
				throw new \exceptions\ActionError('invalid_id', 'The identifier contains whitespaces.');
		} catch (\exceptions\ActionError $e) {
			$this->echo_json($e->toArray());
		}
		
		//TODO: should add necessary sanity check here
		$data['display'] = $base->get('POST.display');
		$data['start'] = $base->get('POST.start');
		$data['close'] = $base->get('POST.close');
		$data['quota_strategy'] = $base->get('POST.quota_strategy');
		$data['quota_amount'] = $base->get('POST.quota_amount');
		$data['submit_filetype'] = str_replace(array(" ", "."), '', $base->get('POST.submit_filetype'));
		$data['submit_filesize'] = intval($base->get('POST.submit_filesize'));
		$data['submit_notes'] = $base->get('POST.submit_notes');
		$data['max_score'] = intval($base->get('POST.max_score'));
		$data['grader_script'] = $base->get('POST.grader_script');
		$data['grader_tar'] = $base->get('POST.grader_tar');
		
		try {
			
			if (!$Assignment->isValidFilePath($data['grader_script']))
				throw new \exceptions\FileError('invalid_script_path', 'The grader script path is not a valid file.');
		
			if (!empty($data['grader_tar']) && !$Assignment->isValidFilePath($data['grader_tar']))
				throw new \exceptions\FileError('invalid_tar_path', 'The grader tar path is not a valid file.');
			
			if ($base->get('POST.internal') == 'new') {
				$Assignment->addAssignment($id, $data);
			} else {
				$Assignment->editAssignment($id, $data);
			}
			
			if ($Assignment->saveAssignments() === false) 
				throw new \exceptions\FileError('write_failure', "Failed to write data to \"" . realpath($base->get("DATA_PATH") . "assignments.json") . "\".");
			
		} catch (\exceptions\Error $e) {
			$this->echo_json($e->toArray());
		}
		
		$this->echo_success('The assignment info is successfully updated.');
	}
	
	function deleteAssignment($base) {
		$this->verifyAdminPermission();
		$Assignment = \models\Assignment::instance();
		$id = $base->get('POST.id');
		if ($Assignment->deleteAssignment($id)) {
			try {
				if ($Assignment->saveAssignments() === false) 
					throw new \exceptions\FileError('write_failure', "Failed to write data to \"" . realpath($base->get("DATA_PATH") . "assignments.json") . "\".");
			} catch (\exceptions\Error $e) {
				$this->echo_json($e->toArray());
			}
		}
	}
	
	function showSubmissionsPane($base) {
		$this->verifyAdminPermission();
		
		$Assignment = \models\Assignment::instance();
		$base->set('assignment_list', $Assignment->getAllAssignments());
		$this->set_view('admin/ajax_submissions.html');
	}
	
	function updateSubmissions($base) {
		$this->verifyAdminPermission();
		
		$action = $base->get('POST.action');
		$submission_ids = $base->get('POST.submission_id');
		
		try {
			if (empty($submission_ids)) throw new \exceptions\ActionError('no_user_selected', 'You did not select any users.');
		} catch (\exceptions\ActionError $e) {
			$this->echo_json($e->toArray());
		}
		
		$Assignment = \models\Assignment::instance();
		
		if ($action == 'adjust') {
			
			foreach ($submission_ids as $id) {
				$submission_record = $Assignment->findSubmissionById($id);
				if ($submission_record == null) continue;
				
				$grade_adj_raw = $base->get('POST.grade_adjustment_' . $id);
				if (!is_numeric($grade_adj_raw)) continue;
				
				$grade_adj = intval($grade_adj_raw);
				$grade_comment = $base->get('POST.comment_' . $id);
				
				$submission_record['grade_adjustment'] = $grade_adj;
				$Assignment->addLog($submission_record, $this->user['user_id'] . " changed the delta grade to " . $grade_adj . " with comment " . $grade_comment . ".");
				
				$Assignment->updateSubmission($submission_record);
			}
			
		} else if ($action == 'regrade') {
			// Send the regrade signal to the related models in batch.
			// This is not subject to quotas, deadlines, and other restrictions.
			
			$Connector = \models\Connector::instance();
			$User = \models\User::instance();
			
			foreach ($submission_ids as $id) {
				$submission_record = $Assignment->findSubmissionById($id);
				if ($submission_record == null) continue;
				
				$Assignment->addLog($submission_record, $this->user['user_id'] . " attempted to regrade the submission.");
				
				$user_info = $User->findById($submission_record['user_id']);
				$assignment_info = $Assignment->findById($submission_record['assignment_id']);
				try {
					$queued_id = $Connector->assignTask($submission_record, $user_info, $assignment_info);
					$Assignment->addLog($submission_record, "Queued with id " . $queued_id . ".");
				} catch (\exceptions\ProtocolError $e) {
					$error_info = $e->toArray();
					$Assignment->addLog($submission_record, "Encountered error: " . $error_info['error'] . ". Description: " . $error_info['error_description']);
				}
				$Assignment->updateSubmission($submission_record);
			}
			
		} else if ($action == 'delete') {
			
			foreach ($submission_ids as $id) {
				$Assignment->deleteSubmission($id);
			}
			
		}
		
		$this->echo_success('The action is performed successfully. Records with invalid data, if any, were skipped.');
	}
	
	function getSubmissionDump($base, $params) {
		$this->verifyAdminPermission();
		
		try {
			
			$submission_id = $params["id"];
			if (!is_numeric($submission_id)) throw new \exceptions\ActionError('invalid_parameter', 'Your request is refused because it contains invalid information.', 403);
			
			$Assignment = \models\Assignment::instance();
			
			$submission_info = $Assignment->findSubmissionById($submission_id);
			if ($submission_info == null) throw new \exceptions\ActionError('submission_not_found', 'There is no record for this submission.', 404);
			
			$path = $base->get("UPLOADS") . $submission_info["assignment_id"] . "/" . $submission_info["user_id"] . "/dumps";
			
			$path = $path . "/submission_" . $submission_info["id"] . "_dump.tar.gz";
			
			if (!file_exists($path)) throw new \exceptions\FileError('file_not_found', 'The file you are requesting is not found in the repository.', 404);
			
			\Web::instance()->send($path, "application/octet-stream", self::MAX_DOWNLOAD_SPEED);
			
		} catch (\exceptions\Error $ex) {
			if ($ex->getCode() == 404) header("HTTP/1.0 404 Not Found");
			else if ($ex->getCode() == 403) header("HTTP/1.0 403 Forbidden");
			
			if ($user_info != null) $base->set("me", $this->user);
			$base->set("error", $ex->toArray());
			$this->set_view("error.html");
		}
	}
	
	function querySubmission($base) {
		$user_info = $this->verifyAdminPermission();
		$Assignment = \models\Assignment::instance();
		$cond = array();
		
		// if not specified, the following get ''
		$user_id_pattern = $base->get('POST.name_pattern');
		if ($user_id_pattern != '')
			$cond[':user_id_pattern'] = $user_id_pattern;
		
		$date_created_start = $base->get('POST.date_created_start');
		if ($date_created_start != '' && strtotime($date_created_start) !== false)
			$cond[':date_created_start'] = $date_created_start;
		
		$date_created_end = $base->get('POST.date_created_end');
		if ($date_created_end != '' && strtotime($date_created_end) !== false)
			$cond[':date_created_end'] = $date_created_end;
		
		$date_updated_start = $base->get('POST.date_updated_start');
		if ($date_updated_start != '' && strtotime($date_updated_start) !== false)
			$cond[':date_updated_start'] = $date_updated_start;
		
		$date_updated_end = $base->get('POST.date_updated_end');
		if ($date_updated_end != '' && strtotime($date_updated_end) !== false)
			$cond[':date_updated_end'] = $date_updated_end;
		
		$grade_max = $base->get('POST.grade_max');
		if ($grade_max != '' && is_numeric($grade_max))
			$cond[':grade_max'] = $grade_max;
		
		$grade_min = $base->get('POST.grade_min');
		if ($grade_min != '' && is_numeric($grade_min))
			$cond[':grade_min'] = $grade_min;
		
		// if nothing is picked, the following get null
		$assignment_ids = $base->get('POST.assignment_id');
		if ($assignment_ids != null)
			$cond[':assignment_id_set'] = $assignment_ids;
		
		$status = $base->get('POST.status');
		if ($status != null)
			$cond[':status_set'] = $status;
		
		$special_cond = $base->get('POST.special_cond');
		
		$result = $Assignment->findSubmissions($cond, $special_cond);
		$View = \View::instance();
		$ret = "";
		foreach ($result as $i => $row) {
			$base->set('row', $row);
			$ret .= $View->render('admin/ajax_submission_row.html');
		}
		$this->echo_success($ret, array('count' => count($result)));		
	}
	
	function showServerPage($base) {
		$this->verifyAdminPermission();
		$base->set('me', $this->user);
		$this->set_view('admin/ajax_server.html');
	}
	
	function addServer($base) {
	}
	
	function editServer($base) {
	}
	
	function deleteServer($base) {
	}
	
	function checkServer($base) {
	}
	
	function showStatusPage($base) {
		$user_info = $this->verifyAdminPermission();
		$base->set('me', $user_info);
		$this->set_view('admin/ajax_status.html');
	}
	
	function showUsersPage($base) {
		$User = \models\User::instance();
		
		$user_info = $this->verifyAdminPermission();
		$roles_info = $User->getRoleTable();
		$base->set('roles_info', $roles_info);
		$base->set('me', $user_info);
		$this->set_view('admin/ajax_users.html');
	}
	
	function updateUser($base) {
		$User = \models\User::instance();
		
		$user_info = $this->verifyAdminPermission();
		
		$action = $base->get('POST.action');
		if ($action == 'query') {
			$id_pattern = $base->get('POST.name_pattern');
			$role_pattern = $base->get('POST.role_pattern');
			$result = $User->matchByPatterns($id_pattern, $role_pattern);
			
			try {
				if (count($result) == 0)
					throw new \exceptions\ActionError('empty_result', 'There is no user matching the given patterns.');
				
				$base->set('user_list', $result);
				$data = \View::instance()->render('admin/ajax_user_rows.html');
				$this->echo_success($data);
			} catch (\exceptions\ActionError $e) {
				$this->echo_json($e->toArray());
			}
			
		} else if ($action == 'add') {
			$role_name = $base->get('POST.role');
			
			try {
				if ($User->findRoleByName($role_name) == null)
					throw new \exceptions\ActionError('invalid_data', 'The role "' . $role_name . '" is not defined.');
			} catch (\exceptions\ActionError $e) {
				$this->echo_json($e->toArray());
				return;
			}
			
			$notify_all = $base->get('POST.notify');
			if ($notify_all != 'true') $notify_all = false;
			else $notify_all = true;
			
			$user_list = str_replace("\r", "", $base->get('POST.user_list'));
			$users = explode("\n", $user_list);
			
			//TODO: here we are assuming the length is large enough, which is bad.
			$password_pool = $User->getPasswordPool(count($users), static::PASSWORD_LEN);
			
			$c = 0;
			$skip_list = array();
			$View = \View::instance();
			foreach ($users as $i => $name) {
				if (!empty($name)) {
					// skip existing users
					if ($User->findById($name) != null) {
						$skip_list[] = $name;
					} else {
						$User->addUser($name, $password_pool[$i], $role_name);
						
						if ($notify_all) {
							$base->set('password', $password_pool[$i]);
							$mail = new \models\Mail();
							$mail->addTo($name . $base->get("USER_EMAIL_DOMAIN"), $name);
							$mail->setFrom($base->get("COURSE_ADMIN_EMAIL"), $base->get("COURSE_ID_DISPLAY") . " AutoGrader");
							$mail->setSubject("Your " . $base->get("COURSE_ID_DISPLAY") . " AutoGrader Password");
							$mail->setMessage($View->render("email_forgot_password.txt"));
							$mail->send();
						}
						++$c;
					}
				}
			}
			
			if (count($skip_list) > 0) $skip_str = ' Skipped existing users: ' . implode(', ', $skip_list) . '.';
			else $skip_str = '';
			
			$this->echo_success('Added ' . $c . ' user(s) to role "' . $role_name . '".' . $skip_str);
		
		} else if ($action == 'delete') {
			$users = $base->get('POST.users');
			foreach ($users as $name => $item)
				if (array_key_exists('selected', $item)) $User->deleteUserById($name);
			
			$this->echo_success('Successfully deleted the selected user(s).');
		} else if ($action == 'change_role') {
			$role_name = $base->get('POST.role');
			
			try {
				if ($User->findRoleByName($role_name) == null)
					throw new \exceptions\ActionError('invalid_data', 'The role "' . $role_name . '" is not defined.');
			} catch (\exceptions\ActionError $e) {
				$this->echo_json($e->toArray());
				return;
			}
			
			$users = $base->get('POST.users');
			foreach ($users as $name => $item) {
				if (array_key_exists('selected', $item)) {
					$user_info = $User->findById($name);
					if ($user_info == null || $user_info['role_id'] == $role_name) continue;
					$User->editUser($user_info, null, $role_name, null);
				}
			}
			
			$this->echo_success('Successfully updated the role of the selected user(s).');
				
		} else if ($action == 'reset_password') {
			$users = $base->get('POST.users');
			$password_pool = $User->getPasswordPool(count($users), static::PASSWORD_LEN);
			$i = 0;
			$View = \View::instance();
			foreach ($users as $name => $item) {
				if (array_key_exists('selected', $item)) {
					$user_info = $User->findById($name);
					if ($user_info != null) {
						$User->editUser($user_info, null, null, $password_pool[$i]);
						$base->set('password', $password_pool[$i++]);
						$mail = new \models\Mail();
						$mail->addTo($name . $base->get("USER_EMAIL_DOMAIN"), $name);
						$mail->setFrom($base->get("COURSE_ADMIN_EMAIL"), $base->get("COURSE_ID_DISPLAY") . " AutoGrader");
						$mail->setSubject("Your " . $base->get("COURSE_ID_DISPLAY") . " AutoGrader Password");
						$mail->setMessage($View->render("email_forgot_password.txt"));
						$mail->send();
					}
				}
			}
			
			$this->echo_success('Successfully generated new password for the selected user(s).');
		} else if ($action == 'update') {
			$users = $base->get('POST.users');
			$skip_list = array();
			$i = 0;
			
			foreach ($users as $name => $item) {
				if (array_key_exists('selected', $item) && array_key_exists('new_name', $item) && !empty($item['new_name']) && $item['new_name'] != $name) {
					$user_info = $User->findById($name);
					if ($user_info != null) {
						$new_user_info = $User->findById($item['new_name']);
						if ($new_user_info != null) $skip_list[] = $item['new_name'];
						else {
							$User->editUser($user_info, $item['new_name'], null, null);
							++$i;
						}
					}
				}
			}
			
			if (count($skip_list) > 0) $skip_str = ' The following ids already exist and cannot be renamed to: ' . implode(', ', $skip_list) . '.';
			else $skip_str = '';
			
			$this->echo_success('Successfully renamed ' . $i . ' users.' . $skip_str);
			
		} else if ($action == 'send_email') {
			$subject = $base->get('POST.subject');
			$body = $base->get('POST.body');
			$users = $base->get('POST.users');
			$i = 0;
			
			try {
				if (empty($subject) || empty($body))
					throw new \exceptions\ActionError('empty_fields', "Subject and body should not be empty.");
			} catch (\exceptions\ActionError $e) {
				$this->echo_json($e->toArray());
				return;
			}
			
			foreach ($users as $name => $item) {
				if (array_key_exists('selected', $item)) {
					$user_info = $User->findById($name);
					if ($user_info == null) continue;
					$mail_subject = $User->replaceTokens($user_info, $subject);
					$mail_body = $User->replaceTokens($user_info, $body);
					
					$mail = new \models\Mail();
					$mail->addTo($name . $base->get('USER_EMAIL_DOMAIN'), $name);
					$mail->setFrom($base->get('COURSE_ADMIN_EMAIL'), $base->get('COURSE_ID_DISPLAY') . ' No-Reply');
					$mail->setSubject($mail_subject);
					$mail->setMessage($mail_body);
					$mail->send();
					++$i;
				}
			}
			
			$this->echo_success('Successfully sent email to ' . $i . ' user(s).');
			
		} else {
			throw new \exceptions\ActionError('undefined_action', 'The action is not defined.');
		}
	}
	
	function updateRole($base) {
		$user_info = $this->verifyAdminPermission();
		
		$User = \models\User::instance();
		$role_data = array();
		$current_roles = $base->get('POST.current');
		$new_role = $base->get('POST.new');
		
		foreach ($current_roles as $i => $role) {
			
			if (array_key_exists('delete', $role)) continue;
			
			try {
				if (!array_key_exists('key', $role) || !array_key_exists('display', $role))
					throw new \exceptions\ActionError('invalid_data', 'ID and name are required fields.');
			
				if (!array_key_exists('submit_priority', $role) || !is_numeric($role['submit_priority']))
					throw new \exceptions\ActionError('invalid_data', 'Priority should be an integer value.');
			
				$role_data[$role['key']] = $User->sanitizeRoleEntry($role);
			} catch (\exceptions\ActionError $e) {
				$this->echo_json($e->toArray());
				return;
			}
		}
		
		try {
			if (array_key_exists('key', $new_role) && !empty($new_role['key'])) {
				if (!array_key_exists('display', $new_role) || empty($new_role['display']) || array_key_exists($new_role['key'], $role_data))
					throw new \exceptions\ActionError('invalid_data', 'To add a new role, please provide an unused ID and a non-empty name.');
				
				$role_data[$new_role['key']] = $User->sanitizeRoleEntry($new_role);		
			}

			if ($User->saveRoleTable($role_data) === false) {
				throw new \exceptions\FileError('write_failure', "Failed to write data to \"" . realpath($base->get("DATA_PATH") . "roles.json") . "\".");
			}
			
			$this->echo_success('Successfully saved role data.');
			
		} catch (\exceptions\ActionError $e) {
			$this->echo_json($e->toArray());
		} catch (\exceptions\FileError $e) {
			$this->echo_json($e->toArray());
		}
	}
	
}
