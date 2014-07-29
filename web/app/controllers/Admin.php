<?php

namespace controllers;

class Admin extends \Controller {
	
	const PASSWORD_LEN = 12;
	const MAX_DOWNLOAD_SPEED = 256; // in kBps
	
	private function verifyAdminPermission($show_json_error = true) {
		$user_info = $this->getUserStatus();
		if ($user_info == null || !$user_info["role"]["permissions"]["manage"])
			if ($show_json_error)
				$this->json_echo(array("error" => "permission_denied", "error_description" => "You cannot access admin panel."), true);
			else $this->base->reroute("/");
		
		$this->user = $user_info;
		return $user_info;
	}
	
	function showAdminHomepage($base) {
		$user_info = $this->verifyAdminPermission(false);
		$base->set('me', $user_info);
		$this->setView('admincp.html');
	}
	
	function addAnnouncement($base) {
		$user_info = $this->verifyAdminPermission();
	}
	
	function editAnnouncement($base) {
		
	}
	
	function deleteAnnouncement($base) {
		
	}
	
	function showAnnouncementsPage($base) {
		$user_info = $this->verifyAdminPermission();
		$Rss = new \models\Rss("data/feed.xml");
		$base->set("announcements", $Rss->get_items());
		$base->set('me', $user_info);
		$this->setView('admin/ajax_announcements.html');
	}
	
	function showAssignmentPage($base) {
		$user_info = $this->verifyAdminPermission();
		$Assignment = \models\Assignment::instance();
		
		$base->set('me', $user_info);
		$base->set('assignment_list', $Assignment->getAllAssignments());
		$base->set('blank_item', $Assignment->getDefaultAssignmentData());
		$this->setView('admin/ajax_assignments.html');
	}
	
	function createGradeBook($base, $params) {
		$assignment_id = $params['id'];
		$format = $params['format'];
	}
	
	function showSubmissionsPane($base) {
		$user_info = $this->verifyAdminPermission();
		$Assignment = \models\Assignment::instance();
		$base->set('assignment_list', $Assignment->getAllAssignments());
		
		$this->setView('admin/ajax_submissions.html');
	}
	
	function updateSubmissions($base) {
		$user_info = $this->verifyAdminPermission();
		$action = $base->get('POST.action');
		
		if (!$base->exists('POST.submission_id'))
			$this->json_echo($this->getError('no_user_selected', 'You did not select any users.'));
		
		$submission_ids = $base->get('POST.submission_id');
		
		if ($action == 'regrade') {
			$this->batchRegradeSubmissions($submission_ids);
		} else if ($action == 'delete') {
			$this->batchDeleteSubmissions($submission_ids);
		}
		
		$this->json_echo($this->getSuccess('The action is performed successfully.'));
	}
	
	/**
	 * Send delete signal to the Assignment model in batch.
	 */
	private function batchDeleteSubmissions($submission_ids) {
		$Assignment = \models\Assignment::instance();
		if (empty($submission_ids)) return;
		
		foreach ($submission_ids as $id) {
			$Assignment->deleteSubmission($id);
		}
	}
	
	/**
	 * Send the regrade signal to the related models in batch.
	 * This is not subject to quotas, deadlines, and other restrictions.
	 */
	private function batchRegradeSubmissions($submission_ids) {
		$Assignment = \models\Assignment::instance();
		$Connector = \models\Connector::instance();
		$User = \models\User::instance();
		
		if (empty($submission_ids)) return;
		
		foreach ($submission_ids as $id) {
			$submission_record = $Assignment->findSubmissionById($id);
			if ($submission_record == null) continue;
			
			$Assignment->addLog($submission_record, $this->user['user_id'] . " attempted to regrade the submission.");
			
			$user_info = $User->findById($submission_record['user_id']);
			$assignment_info = $Assignment->findById($submission_record['assignment_id']);
			$assign_result = $Connector->assignTask($submission_record, $user_info, $assignment_info);
			if ($assign_result['result'] == 'queued') {
				$Assignment->addLog($submission_record, "Queued with id " . $assign_result["queued_id"] . ".");
			}
			$Assignment->updateSubmission($submission_record);
		}
	}
	
	function updateAssignment($base) {
		$user_info = $this->verifyAdminPermission();
		$Assignment = \models\Assignment::instance();
		
		$id = $base->get('POST.id');
		$data = null;
		if ($base->get('POST.internal') == 'new') {
			$data = $Assignment->getDefaultAssignmentData();
			if ($Assignment->findById($id) != null)
				$this->json_echo($this->getError('id_used', 'The identifier is already taken.'));
		} else {
			$data = $Assignment->findById($id);
			if ($data == null)
				$this->json_echo($this->getError('id_not_found', 'The assignment is not found.'));
		}
		
		if (!$Assignment->isValidIdentifier($id))
			$this->json_echo($this->getError('invalid_id', 'The identifier contains whitespaces.'));
		
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
		
		if (!$Assignment->isValidFilePath($data['grader_script']))
			$this->json_echo($this->getError('invalid_script_path', 'The grader script path is not a valid file.'));
		
		if (!empty($data['grader_tar']) && !$Assignment->isValidFilePath($data['grader_tar']))
			$this->json_echo($this->getError('invalid_tar_path', 'The grader tar path is not a valid file.'));
		
		if ($base->get('POST.internal') == 'new') {
			$Assignment->addAssignment($id, $data);
		} else {
			$Assignment->editAssignment($id, $data);
		}
		if ($Assignment->saveAssignments() === false) 
			$this->json_echo($this->getError('write_failure', "Failed to write data to \"" . realpath($base->get("DATA_PATH") . "assignments.json") . "\"."));
		
		$this->json_echo($this->getSuccess('The assignment info is succesfully updated.'));
	}
	
	function deleteAssignment($base) {
		$user_info = $this->verifyAdminPermission();
		$Assignment = \models\Assignment::instance();
		
		$id = $base->get('POST.id');
		if ($Assignment->deleteAssignment($id))
			$Assignment->saveAssignments();
	}
	
	function getSubmissionDump($base, $params) {
		$user_info = $this->verifyAdminPermission();
		try {
			
			$submission_id = $params["id"];
			if (!is_numeric($submission_id)) throw new \exceptions\AssignmentException("invalid_parameter", "Your request is refused because it contains invalid information.", 403);
			
			$Assignment = \models\Assignment::instance();
			
			$submission_info = $Assignment->findSubmissionById($submission_id);
			if ($submission_info == null) throw new \exceptions\AssignmentException("submission_not_found", "There is no record for this submission.", 404);
			
			$path = $base->get("UPLOADS") . "/" . $submission_info["assignment_id"] . "/" . $submission_info["user_id"] . "/dumps";
			
			$path = $path . "/submission_" . $submission_info["id"] . "_dump.tar.gz";
			
			if (!file_exists($path))
				throw new \exceptions\AssignmentException("file_not_found", "The file you are requesting is not found in the repository. Please contact admin.", 404);
			
			\Web::instance()->send($path, "application/octet-stream", self::MAX_DOWNLOAD_SPEED);
			
		} catch (\exceptions\AssignmentException $ex) {
			if ($ex->getCode() == 404) header("HTTP/1.0 404 Not Found");
			else if ($ex->getCode() == 403) header("HTTP/1.0 403 Forbidden");
			
			if ($user_info != null) $base->set("me", $user_info);
			$base->set("error", $ex->toArray());
			$this->setView("error.html");
		}
	}
	
	function regradeSubmission($base) {
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
		
		$result = $Assignment->findSubmissions($cond);
		$View = \View::instance();
		$ret = "";
		foreach ($result as $i => $row) {
			$base->set('row', $row);
			$ret .= $View->render('admin/ajax_submission_row.html');
		}
		$ret = $this->getSuccess($ret);
		$ret['count'] = count($result);
		$this->json_echo($ret);
		
	}
	
	function showServerPage($base) {
		$user_info = $this->verifyAdminPermission();
		$base->set('me', $user_info);
		$this->setView('admin/ajax_server.html');
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
		$this->setView('admin/ajax_status.html');
	}
	
	function showUsersPage($base) {
		$User = \models\User::instance();
		
		$user_info = $this->verifyAdminPermission();
		$roles_info = $User->getRoleTable();
		$base->set('roles_info', $roles_info);
		$base->set('me', $user_info);
		$this->setView('admin/ajax_users.html');
	}
	
	function updateUser($base) {
		$User = \models\User::instance();
		
		$user_info = $this->verifyAdminPermission();
		
		$action = $base->get('POST.action');
		if ($action == 'query') {
			$id_pattern = $base->get('POST.name_pattern');
			$role_pattern = $base->get('POST.role_pattern');
			$result = $User->matchByPatterns($id_pattern, $role_pattern);
			if (count($result) == 0)
				$this->json_echo($this->getError('empty_result', 'There is no user matching the given patterns.'));
			else {
				$base->set('user_list', $result);
				$data = \View::instance()->render('admin/ajax_user_rows.html');
				$this->json_echo($this->getSuccess($data));
			}
		} else if ($action == 'add') {
			$role_name = $base->get('POST.role');
			if ($User->findRoleByName($role_name) == null)
				$this->json_echo($this->getError('invalid_data', 'The role "' . $role_name . '" is not defined.'));
			$user_list = str_replace("\r", "", $base->get('POST.user_list'));
			$users = explode("\n", $user_list);
			
			//TODO: here we are assuming the length is large enough, which is bad.
			$password_pool = $User->getPasswordPool(count($users), static::PASSWORD_LEN);
			
			$c = 0;
			$skip_list = array();
			
			foreach ($users as $i => $name) {
				if (!empty($name)) {
					// skip existing users
					if ($User->findById($name) != null) {
						$skip_list[] = $name;
					} else {
						$User->addUser($name, $role_name, $password_pool[$i]);
						++$c;
					}
				}
			}
			
			if (count($skip_list) > 0) $skip_str = ' Skipped existing users: ' . implode(', ', $skip_list) . '.';
			else $skip_str = '';
			
			if ($User->saveUserTable() === false) {
				$this->json_echo($this->getError('write_failure', "Failed to write data to \"" . realpath($base->get("DATA_PATH") . "users.json") . "\"."));
			}
			
			$this->json_echo($this->getSuccess('Added ' . $c . ' user(s) to role "' . $role_name . '".' . $skip_str));
		
		} else if ($action == 'delete') {
			$users = $base->get('POST.users');
			foreach ($users as $name => $item)
				if (array_key_exists('selected', $item)) $User->deleteUserById($name);
			
			if ($User->saveUserTable() === false) {
				$this->json_echo($this->getError('write_failure', "Failed to write data to \"" . realpath($base->get("DATA_PATH") . "users.json") . "\"."));
			}
			
			$this->json_echo($this->getSuccess('Successfully deleted the selected user(s).'));
		} else if ($action == 'change_role') {
			$role_name = $base->get('POST.role');
			if ($User->findRoleByName($role_name) == null)
				$this->json_echo($this->getError('invalid_data', 'The role "' . $role_name . '" is not defined.'));
			
			$users = $base->get('POST.users');
			foreach ($users as $name => $item) {
				if (array_key_exists('selected', $item)) {
					$user_info = $User->findById($name);
					if ($user_info == null || $user_info['role']['name'] == $role_name) continue;
					$User->editUser($user_info, null, $role_name, null);
				}
			}
			
			if ($User->saveUserTable() === false) {
				$this->json_echo($this->getError('write_failure', "Failed to write data to \"" . realpath($base->get("DATA_PATH") . "users.json") . "\"."));
			}
			
			$this->json_echo($this->getSuccess('Successfully updated the role of the selected user(s).'));
				
		} else if ($action == 'reset_password') {
			$users = $base->get('POST.users');
			$password_pool = $User->getPasswordPool(count($users), static::PASSWORD_LEN);
			$i = 0;
			foreach ($users as $name => $item) {
				if (array_key_exists('selected', $item)) {
					$user_info = $User->findById($name);
					if ($user_info != null)
						$User->editUser($user_info, null, null, $password_pool[$i++]);
				}
			}
			
			if ($User->saveUserTable() === false) {
				$this->json_echo($this->getError('write_failure', "Failed to write data to \"" . realpath($base->get("DATA_PATH") . "users.json") . "\"."));
			}
			
			$this->json_echo($this->getSuccess('Successfully generated new password for the selected user(s).'));
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
			
			if ($User->saveUserTable() === false) {
				$this->json_echo($this->getError('write_failure', "Failed to write data to \"" . realpath($base->get("DATA_PATH") . "users.json") . "\"."));
			}
			
			$this->json_echo($this->getSuccess('Successfully renamed ' . $i . ' users.' . $skip_str));
			
		} else if ($action == 'send_email') {
			$subject = $base->get('POST.subject');
			$body = $base->get('POST.body');
			$users = $base->get('POST.users');
			$i = 0;
			
			if (empty($subject) || empty($body))
				$this->json_echo($this->getError('empty_fields', "Subject and body should not be empty."));
			
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
			
			$this->json_echo($this->getSuccess('Successfully sent email to ' . $i . ' user(s).'));
			
		} else 
			$this->json_echo($this->getError('undefined_action', 'The action you are performing is not defined.'));
	}
	
	function updateRole($base) {
		$user_info = $this->verifyAdminPermission();
		
		$User = \models\User::instance();
		$role_data = array();
		$current_roles = $base->get('POST.current');
		$new_role = $base->get('POST.new');
		
		foreach ($current_roles as $i => $role) {
			
			if (array_key_exists('delete', $role)) continue;
			
			if (!array_key_exists('key', $role) || !array_key_exists('display', $role))
				$this->json_echo($this->getError('invalid_data', 'ID and name are required fields.'));
			
			if (!array_key_exists('submit_priority', $role) || !is_numeric($role['submit_priority']))
				$this->json_echo($this->getError('invalid_data', 'Priority should be an integer value.'));
			
			$role_data[$role['key']] = $User->sanitizeRoleEntry($role);
		}
		
		if (array_key_exists('key', $new_role) && !empty($new_role['key'])) {
			if (!array_key_exists('display', $new_role) || empty($new_role['display']) || array_key_exists($new_role['key'], $role_data))
				$this->json_echo($this->getError('invalid_data', 'To add a new role, please provide an unused ID and a non-empty name.'));
			
			$role_data[$new_role['key']] = $User->sanitizeRoleEntry($new_role);		
		}
		
		if ($User->saveRoleTable($role_data) === false) {
			$this->json_echo($this->getError('write_failure', "Failed to write data to \"" . realpath($base->get("DATA_PATH") . "roles.json") . "\"."));
		}
		
		$this->json_echo($this->getSuccess('Successfully saved role data.'));
	}
	
}
