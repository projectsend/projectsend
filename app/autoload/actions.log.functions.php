<?php
/**
 * Renders an action recorded on the log.
 */
function render_log_action($params)
{
	$action = $params['action'];
	$timestamp = $params['timestamp'];
	$owner_id = $params['owner_id'];
	$owner_user = html_output($params['owner_user']);
	$affected_file = $params['affected_file'];
	$affected_file_name = $params['affected_file_name'];
	$affected_account = $params['affected_account'];
	$affected_account_name = html_output($params['affected_account_name']);

	switch ($action) {
		case 0:
			$action_ico = 'install';
			$action_text = __('ProjectSend was installed','cftp_admin');
			break;
		case 1:
			$action_ico = 'login';
			$part1 = $owner_user;
			$action_text = __('logged in to the system.','cftp_admin');
			break;
		case 2:
			$action_ico = 'user-add';
			$part1 = $owner_user;
			$action_text = __('created the user account','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 3:
			$action_ico = 'client-add';
			$part1 = $owner_user;
			$action_text = __('created the client account ','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 4:
			$action_ico = 'client-add';
			$part1 = $affected_account_name;
			$action_text = __('created a client account for themself.','cftp_admin');
			break;
		case 5:
			$action_ico = 'file-add';
			$part1 = $owner_user;
			$action_text = __('(user) uploaded the file','cftp_admin');
			$part2 = $affected_file_name;
			break;
		case 6:
			$action_ico = 'file-add';
			$part1 = $owner_user;
			$action_text = __('(client) uploaded the file','cftp_admin');
			$part2 = $affected_file_name;
			break;
		case 7:
			$action_ico = 'file-download';
			$part1 = $owner_user;
			$action_text = __('(user) downloaded the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('assigned to:','cftp_admin');
			$part4 = $affected_account_name;
			break;
		case 8:
			$action_ico = 'file-download';
			$part1 = $owner_user;
			$action_text = __('(client) downloaded the file','cftp_admin');
			$part2 = $affected_file_name;
			break;
		case 9:
			$action_ico = 'download-zip';
			$part1 = $owner_user;
			$action_text = __('generated a zip file','cftp_admin');
			break;
		case 10:
			$action_ico = 'file-unassign';
			$part1 = $owner_user;
			$action_text = __('unassigned the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('from the client:','cftp_admin');
			$part4 = $affected_account_name;
			break;
		case 11:
			$action_ico = 'file-unassign';
			$part1 = $owner_user;
			$action_text = __('unassigned the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('from the group:','cftp_admin');
			$part4 = $affected_account_name;
			break;
		case 12:
			$action_ico = 'file-delete';
			$part1 = $owner_user;
			$action_text = __('deleted the file','cftp_admin');
			$part2 = $affected_file_name;
			break;
		case 13:
			$action_ico = 'user-edit';
			$part1 = $owner_user;
			$action_text = __('edited the user','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 14:
			$action_ico = 'client-edit';
			$part1 = $owner_user;
			$action_text = __('edited the client','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 15:
			$action_ico = 'group-edit';
			$part1 = $owner_user;
			$action_text = __('edited the group','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 16:
			$action_ico = 'user-delete';
			$part1 = $owner_user;
			$action_text = __('deleted the user','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 17:
			$action_ico = 'client-delete';
			$part1 = $owner_user;
			$action_text = __('deleted the client','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 18:
			$action_ico = 'group-delete';
			$part1 = $owner_user;
			$action_text = __('deleted the group','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 19:
			$action_ico = 'client-activate';
			$part1 = $owner_user;
			$action_text = __('activated the client','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 20:
			$action_ico = 'client-deactivate';
			$part1 = $owner_user;
			$action_text = __('deactivated the client','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 21:
			$action_ico = 'file-hidden';
			$part1 = $owner_user;
			$action_text = __('marked as hidden the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('to:','cftp_admin');
			$part4 = $affected_account_name;
			break;
		case 22:
			$action_ico = 'file-visible';
			$part1 = $owner_user;
			$action_text = __('marked as visible the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('to:','cftp_admin');
			$part4 = $affected_account_name;
			break;
		case 23:
			$action_ico = 'group-add';
			$part1 = $owner_user;
			$action_text = __('created the group','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 24:
			$action_ico = 'login';
			$part1 = $owner_user;
			$action_text = __('logged in to the system.','cftp_admin');
			break;
		case 25:
			$action_ico = 'file-assign';
			$part1 = $owner_user;
			$action_text = __('assigned the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('to the client:','cftp_admin');
			$part4 = $affected_account_name;
			break;
		case 26:
			$action_ico = 'file-assign';
			$part1 = $owner_user;
			$action_text = __('assigned the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('to the group:','cftp_admin');
			$part4 = $affected_account_name;
			break;
		case 27:
			$action_ico = 'user-activate';
			$part1 = $owner_user;
			$action_text = __('activated the user','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 28:
			$action_ico = 'user-deactivate';
			$part1 = $owner_user;
			$action_text = __('deactivated the user','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 29:
			$action_ico = 'branding-change';
			$part1 = $owner_user;
			$action_text = __('uploaded a new logo on "Branding"','cftp_admin');
			break;
		case 30:
			$action_ico = 'update';
			$part1 = $owner_user;
			$action_text = __('updated ProjectSend to version','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 31:
			$action_ico = 'logout';
			$part1 = $owner_user;
			$action_text = __('logged out of the system.','cftp_admin');
			break;
		case 32:
			$action_ico = 'file-edit';
			$part1 = $owner_user;
			$action_text = __('(user) edited the file','cftp_admin');
			$part2 = $affected_file_name;
			break;
		case 33:
			$action_ico = 'file-edit';
			$part1 = $owner_user;
			$action_text = __('(client) edited the file','cftp_admin');
			$part2 = $affected_file_name;
			break;
		case 34:
			$action_ico = 'category-add';
			$part1 = $owner_user;
			$action_text = __('created the category','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 35:
			$action_ico = 'category-edit';
			$part1 = $owner_user;
			$action_text = __('edited the category','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 36:
			$action_ico = 'category-delete';
			$part1 = $owner_user;
			$action_text = __('deleted the category','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 37:
			$action_ico = 'download-anonymous';
			$part1 = __('An anonymous user','cftp_admin');
			$action_text = __('downloaded the file','cftp_admin');
			$part2 = $affected_file_name;
			break;
		case 38:
			$action_ico = 'client-request-processed';
			$part1 = $owner_user;
			$action_text = __('processed an account request for','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 39:
			$action_ico = 'client-request-processed';
			$part1 = $owner_user;
			$action_text = __('processed group memberships requests for','cftp_admin');
			$part2 = $affected_account_name;
			break;
	}

	$date = date(TIMEFORMAT,strtotime($timestamp));

	if (!empty($part1)) { $log['1'] = $part1; }
	if (!empty($part2)) { $log['2'] = $part2; }
	if (!empty($part3)) { $log['3'] = $part3; }
	if (!empty($part4)) { $log['4'] = $part4; }
	$log['icon'] = $action_ico;
	$log['timestamp'] = $date;
	$log['text'] = $action_text;

	return $log;
}