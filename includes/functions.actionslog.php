<?php
/**
 * Renders an action recorded on the log.
 * @todo This is really messy. Replace!!
 */
function format_action_log_record($params)
{
	$action = $params['action'];
	$timestamp = $params['timestamp'];
	$owner_id = $params['owner_id'];
	$owner_user = html_output($params['owner_user']);
	$affected_file = $params['affected_file'];
	$affected_file_name = $params['affected_file_name'];
	$affected_account = $params['affected_account'];
    $affected_account_name = html_output($params['affected_account_name']);
    $formatted = null;

	switch ($action) {
		case 0:
            $action_text = __('ProjectSend was installed','cftp_admin');
            $formatted = __('ProjectSend was installed','cftp_admin');
            $type = 'system';
			break;
		case 1:
			$part1 = $owner_user;
            $action_text = __('logged in to the system.','cftp_admin');
            $formatted = sprintf(__('%s logged in to the system','cftp_admin'), $affected_account_name);
            $type = 'auth';
			break;
		case 2:
			$part1 = $owner_user;
			$action_text = __('created the user account','cftp_admin');
			$part2 = $affected_account_name;
            $formatted = sprintf(__('%s created the user account %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'users';
			break;
		case 3:
			$part1 = $owner_user;
			$action_text = __('created the client account ','cftp_admin');
			$part2 = $affected_account_name;
            $formatted = sprintf(__('%s created the client account %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'clients';
			break;
		case 4:
			$part1 = $affected_account_name;
			$action_text = __('created a client account for themself.','cftp_admin');
            $formatted = sprintf(__('%s registered as a new client','cftp_admin'), $affected_account_name);
            $type = 'clients';
			break;
		case 5:
			$part1 = $owner_user;
			$action_text = __('(user) uploaded the file','cftp_admin');
            $part2 = $affected_file_name;
            $formatted = sprintf(__('%s (user) uploaded the file %s','cftp_admin'), $owner_user, $affected_file_name);
            $type = 'files';
			break;
		case 6:
			$part1 = $owner_user;
			$action_text = __('(client) uploaded the file','cftp_admin');
			$part2 = $affected_file_name;
            $formatted = sprintf(__('%s (client) uploaded the file %s','cftp_admin'), $owner_user, $affected_file_name);
            $type = 'files';
			break;
		case 7:
			$part1 = $owner_user;
			$action_text = __('(user) downloaded the file','cftp_admin');
            $part2 = $affected_file_name;
            $formatted = sprintf(__('%s (user) downloaded the file %s','cftp_admin'), $owner_user, $affected_file_name);
            $type = 'files';
			break;
		case 8:
			$part1 = $owner_user;
			$action_text = __('(client) downloaded the file','cftp_admin');
            $part2 = $affected_file_name;
            $formatted = sprintf(__('%s (client) downloaded the file %s','cftp_admin'), $owner_user, $affected_file_name);
            $type = 'files';
			break;
		case 9:
			$part1 = $owner_user;
            $action_text = __('generated a zip file','cftp_admin');
            $formatted = sprintf(__('%s generated a zip file','cftp_admin'), $owner_user);
            $type = 'files';
			break;
		case 10:
			$part1 = $owner_user;
			$action_text = __('unassigned the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('from the client:','cftp_admin');
            $part4 = $affected_account_name;
            $formatted = sprintf(__('%s unassigned the file %s from the client %s','cftp_admin'), $owner_user, $affected_file_name, $affected_account_name);
            $type = 'files';
			break;
		case 11:
			$part1 = $owner_user;
			$action_text = __('unassigned the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('from the group:','cftp_admin');
			$part4 = $affected_account_name;
            $formatted = sprintf(__('%s unassigned the file %s from the group %s','cftp_admin'), $owner_user, $affected_file_name, $affected_account_name);
            $type = 'files';
			break;
		case 12:
			$part1 = $owner_user;
			$action_text = __('deleted the file','cftp_admin');
            $part2 = $affected_file_name;
            $formatted = sprintf(__('%s deleted the file %s','cftp_admin'), $owner_user, $affected_file_name);
            $type = 'files';
			break;
		case 13:
			$part1 = $owner_user;
			$action_text = __('edited the user','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s edited the user %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'users';
			break;
		case 14:
			$part1 = $owner_user;
            $action_text = __('edited the client','cftp_admin');
			$part2 = $affected_account_name;
            $formatted = sprintf(__('%s edited the client %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'clients';
			break;
		case 15:
			$part1 = $owner_user;
			$action_text = __('edited the group','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s edited the group %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'groups';
			break;
		case 16:
			$part1 = $owner_user;
			$action_text = __('deleted the user','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s deleted the user %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'users';
			break;
		case 17:
			$part1 = $owner_user;
			$action_text = __('deleted the client','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s deleted the client %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'clients';
			break;
		case 18:
			$part1 = $owner_user;
			$action_text = __('deleted the group','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s deleted the group %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'groups';
			break;
		case 19:
			$part1 = $owner_user;
			$action_text = __('activated the client','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s activated the client %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'clients';
			break;
		case 20:
			$part1 = $owner_user;
			$action_text = __('deactivated the client','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s deactivated the client %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'clients';
			break;
		case 21:
			$part1 = $owner_user;
			$action_text = __('marked as hidden the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('to:','cftp_admin');
            $part4 = $affected_account_name;
            $formatted = sprintf(__('%s marked as hidden the file %s to %s','cftp_admin'), $owner_user, $affected_file_name, $affected_account_name);
            $type = 'files';
			break;
        case 40:
			$part1 = $owner_user;
			$action_text = __('marked as hidden for everyone the file','cftp_admin');
            $part2 = $affected_file_name;
            $formatted = sprintf(__('%s marked as hidden for everyone the file %s','cftp_admin'), $owner_user, $affected_file_name);
            $type = 'files';
			break;
		case 22:
			$part1 = $owner_user;
			$action_text = __('marked as visible the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('to:','cftp_admin');
            $part4 = $affected_account_name;
            $formatted = sprintf(__('%s marked as visible the file %s to %s','cftp_admin'), $owner_user, $affected_file_name, $affected_account_name);
            $type = 'files';
			break;
		case 23:
			$part1 = $owner_user;
			$action_text = __('created the group','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s created the group %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'groups';
			break;
		case 24: // cookies log in, not used
			$part1 = $owner_user;
            $action_text = __('logged in to the system.','cftp_admin');
            $formatted = sprintf(__('%s logged in to the system','cftp_admin'), $owner_user);
            $type = 'auth';
			break;
		case 25:
			$part1 = $owner_user;
			$action_text = __('assigned the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('to the client:','cftp_admin');
            $part4 = $affected_account_name;
            $formatted = sprintf(__('%s assigned the file %s to client %s','cftp_admin'), $owner_user, $affected_file_name, $affected_account_name);
            $type = 'files';
			break;
		case 26:
			$part1 = $owner_user;
			$action_text = __('assigned the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('to the group:','cftp_admin');
            $part4 = $affected_account_name;
            $formatted = sprintf(__('%s assigned the file %s to group %s','cftp_admin'), $owner_user, $affected_file_name, $affected_account_name);
            $type = 'files';
			break;
		case 27:
			$part1 = $owner_user;
			$action_text = __('activated the user','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s activated the user %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'users';
			break;
		case 28:
			$part1 = $owner_user;
			$action_text = __('deactivated the user','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s deactivated the user %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'users';
			break;
		case 29:
			$part1 = $owner_user;
            $action_text = __('uploaded a new logo on "Branding"','cftp_admin');
            $formatted = sprintf(__('%s uploaded a new logo on "Branding"','cftp_admin'), $owner_user);
            $type = 'system';
			break;
		case 30:
			$part1 = $owner_user;
			$action_text = __('updated ProjectSend to version','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s updated ProjectSend to version %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'system';
			break;
		case 31:
			$part1 = $owner_user;
            $action_text = __('logged out of the system.','cftp_admin');
            $formatted = sprintf(__('%s logged out of the system','cftp_admin'), $owner_user);
            $type = 'auth';
			break;
		case 32:
			$part1 = $owner_user;
			$action_text = __('(user) edited the file','cftp_admin');
			$part2 = $affected_file_name;
            $formatted = sprintf(__('%s (user) edited the file %s','cftp_admin'), $owner_user, $affected_file_name);
            $type = 'files';
			break;
		case 33:
			$part1 = $owner_user;
			$action_text = __('(client) edited the file','cftp_admin');
            $part2 = $affected_file_name;
            $formatted = sprintf(__('%s (client) edited the file %s','cftp_admin'), $owner_user, $affected_file_name);
            $type = 'files';
			break;
		case 34:
			$part1 = $owner_user;
			$action_text = __('created the category','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s created the category %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'categories';
			break;
		case 35:
			$part1 = $owner_user;
			$action_text = __('edited the category','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s edited the category %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'categories';
			break;
		case 36:
			$part1 = $owner_user;
			$action_text = __('deleted the category','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s deleted the category %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'categories';
			break;
		case 37:
			$part1 = __('An anonymous user','cftp_admin');
			$action_text = __('downloaded the file','cftp_admin');
            $part2 = $affected_file_name;
            $formatted = sprintf(__('An anonymous user downloaded the file %s','cftp_admin'), $affected_file_name);
            $type = 'files';
			break;
		case 38:
			$part1 = $owner_user;
			$action_text = __('processed an account request for','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s processed an account request for %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'clients';
			break;
		case 39:
			$part1 = $owner_user;
            $action_text = __('processed group memberships requests for','cftp_admin');
			$part2 = $affected_account_name;
            $formatted = sprintf(__('%s processed group memberships requests for %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'clients';
			break;
        case 41:
            $part1 = $owner_user;
            $action_text = __('requested a preview for the file','cftp_admin');
            $part2 = $affected_file_name;
            $formatted = sprintf(__('%s requested a preview for the file %s','cftp_admin'), $owner_user, $affected_file_name);
            $type = 'files';
            break;
        case 42:
            $part1 = $owner_user;
            $action_text = __('created an account with a social profile','cftp_admin');
            $formatted = sprintf(__('%s created an account with a social profile','cftp_admin'), $owner_user);
            $type = 'auth';
            break;
        case 43:
            $part1 = $owner_user;
            $action_text = __('logged in with a social profile','cftp_admin');
            $formatted = sprintf(__('%s logged in with a social profile','cftp_admin'), $owner_user);
            $type = 'auth';
            break;
        case 44:
            $part1 = $owner_user;
            $action_text = __('approved an account request for','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s approved an account request for %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'clients';
            break;
        case 45:
            $part1 = $owner_user;
            $action_text = __('denied an account request for','cftp_admin');
            $part2 = $affected_account_name;
            $formatted = sprintf(__('%s denied an account request for %s','cftp_admin'), $owner_user, $affected_account_name);
            $type = 'clients';
            break;
        }

    $date = format_date($timestamp);

	if (!empty($part1)) { $log['part1'] = $part1; }
	if (!empty($part2)) { $log['part2'] = $part2; }
	if (!empty($part3)) { $log['part3'] = $part3; }
    if (!empty($part4)) { $log['part4'] = $part4; }
    $log['type'] = (!empty($type)) ? $type : 'system';
	$log['timestamp'] = $date;
    $log['action'] = $action_text;

    $log['formatted'] = $formatted;

    return $log;
}
