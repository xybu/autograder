<?php

namespace controllers;

class Admin extends \Controller {
	
	private function verifyAdminPermission($show_json_error = true) {
		$user_info = $this->getUserStatus();
		if ($user_info == null || !$user_info["role"]["permissions"]["manage"])
			if ($show_json_error)
				$this->json_echo(array("error" => "permission_denied", "error_description" => "You cannot access admin panel."), true);
			else $this->base->reroute("/");
		
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
		$base->set('me', $user_info);
		$this->setView('admin/ajax_assignments.html');
	}
	
	function addAssignment($base) {
	}
	
	function editAssignment($base) {
	}
	
	function deleteAssignment($base) {
	}
	
	function regradeSubmission($base) {
	}
	
	function querySubmission($base) {
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
		$roles_info = $User->getRoleMap();
		$base->set('roles_info', $roles_info);
		$base->set('me', $user_info);
		$this->setView('admin/ajax_users.html');
	}
	
	function addUsers($bsae) {
	}
	
	function deleteUsers($base) {
	}
	
	function editUser($base) {
	}
	
	function queryUser($base) {
	}
	
	function sendEmailToUsers($base) {
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
		
		if ($User->saveRoleMap($role_data) === false) {
			$this->json_echo($this->getError('write_failure', "Failed to write data to \"" . realpath($base->get("DATA_PATH") . "roles.json") . "\"."));
		}
		
		$this->json_echo($this->getSuccess('Successfully saved role data.'));
	}
	
}
