<?php
/**
 * Search the database for unsent notifications and email them.
 *
 * @package		ProjectSend
 * @subpackage	Upload
 *
 */

/** This file MUST be included by another one */
require_once('sys.includes.php');
prevent_direct_access();

$get_file_info = array();
$get_client_info = array();
$notifications_sent = array();
$notifications_inactive = array();

/**
 * First, get the list of different files that have
 * notifications to be sent. Requires that the amount
 * of times that the system failed to send the email
 * is lees than 3, and that the user/client was not
 * inactive when first trying.
 *
 * UPDATE: User can now define a maximum of tries per
 * notification, 3 is now not the limit.
 *
 * The sent_status column stores an integer related to
 * the status of the notification. Possible values:
 * 0 - Notification is new and needs to be sent.
 * 1 - E-mail sent OK.
 * 2 - E-mail FAILED (times count stored on times_failed).
 * 3 - Unsent, client or system user were inactive.
 *
 * UPDATE: 2 is now unused.
 */
$params = array();
$query = "SELECT * FROM " . TABLE_NOTIFICATIONS . " WHERE sent_status = '0' AND times_failed < :times";
$params[':times'] = NOTIFICATIONS_MAX_TRIES;
/** Add the time limit */
if (NOTIFICATIONS_MAX_DAYS != '0') {
	$query .= " AND timestamp >= DATE_SUB(NOW(), INTERVAL :days DAY)";
	$params[':days'] = NOTIFICATIONS_MAX_DAYS;
}

$statement = $dbh->prepare( $query );
$statement->execute( $params );

$statement->setFetchMode(PDO::FETCH_ASSOC);
while( $row = $statement->fetch() ) {
	$get_file_info[]		= $row['file_id'];
	$get_client_info[]		= $row['client_id'];
	$found_notifications[]	= array(
									'id'			=> $row['id'],
									'client_id'		=> $row['client_id'],
									'file_id'		=> $row['file_id'],
									'timestamp'		=> $row['timestamp'],
									'upload_type'	=> $row['upload_type']
								);
}

$files_to_get	= implode( ',', array_unique( $get_file_info) );
$clients_to_get	= implode( ',', array_unique( $get_client_info) );

/**
 * Continue if there are notifications to be sent.
 */
if (!empty($found_notifications)) {
	/**
	 * Get the information of each file
	 */
	$statement = $dbh->prepare("SELECT id, filename, description FROM " . TABLE_FILES . " WHERE FIND_IN_SET(id, :files)");
	$statement->bindParam(':files', $files_to_get);
	$statement->execute();
	$statement->setFetchMode(PDO::FETCH_ASSOC);
	while ( $row = $statement->fetch() ) {
		$file_data[$row['id']] = array(
									'id'			=> $row['id'],
									'filename'		=> $row['filename'],
									'description'	=> htmlentities_allowed($row['description'])
								);
	}
	
	/**
	 * Get the information of each client
	 */
	$creators = array();
	$statement = $dbh->prepare("SELECT id, user, name, email, level, notify, created_by, active FROM " . TABLE_USERS . " WHERE FIND_IN_SET(id, :clients)");
	$statement->bindParam(':clients', $clients_to_get);
	$statement->execute();
	$statement->setFetchMode(PDO::FETCH_ASSOC);
	while ( $row = $statement->fetch() ) {
		$clients_data[$row['id']] = array(
									'id'			=> $row['id'],
									'user'			=> $row['user'],
									'name'			=> $row['name'],
									'email'			=> $row['email'],
									'level'			=> $row['level'],
									'notify'		=> $row['notify'],
									'created_by'	=> $row['created_by'],
									'active'		=> $row['active']
								);
		$creators[] = $row['created_by'];
		$mail_by_user[$row['user']] = $row['email'];
	}
	
	/**
	 * Add the creatros of the previous clients to the mails array.
	 */
	$creators = implode( ',', $creators);
	if (!empty($creators)) {
		$statement = $dbh->prepare("SELECT id, name, user, email, active FROM " . TABLE_USERS . " WHERE FIND_IN_SET(user, :users)");
		$statement->bindParam(':users', $creators);
		$statement->execute();
		$statement->setFetchMode(PDO::FETCH_ASSOC);
		while ( $row = $statement->fetch() ) {
			$creators_data[$row['user']] = array(
										'id' => $row['id'],
										'user' => $row['user'],
										'name' => $row['name'],
										'email' => $row['email'],
										'active' => $row['active']
									);
			$mail_by_user[$row['user']] = $row['email'];
		}
	}
	
	/**
	 * Prepare the list of clients and admins that will be
	 * notified, adding to each one the corresponding files.
	 */
	if (!empty($clients_data)) {
		foreach ($clients_data as $client) {
			$email_body = '';
			/**
			 * Upload types values:
			 * 0 - File was uploaded by a client	-> notify admin
			 * 1 - File was uploaded by a user		-> notify client/s
			 */
			foreach ($found_notifications as $notification) {
				if ($notification['client_id'] == $client['id']) {
					if ($notification['upload_type'] == '0') {
						/** Add the file to the account's creator email */
						$use_id = $notification['file_id'];
						$notes_to_admin[$client['created_by']][$client['name']][] = array(
																		'notif_id' => $notification['id'],
																		'file_name' => $file_data[$use_id]['filename'],
																		'description' => make_excerpt($file_data[$use_id]['description'],200)
																	);
					}
					elseif ($notification['upload_type'] == '1') {
						if ($client['notify'] == '1') {
							if ($client['active'] == '1') {
								/** If file is uploaded by user, add to client's email body */
								$use_id = $notification['file_id'];
								$notes_to_clients[$client['user']][] = array(
																			'notif_id' => $notification['id'],
																			'file_name' => $file_data[$use_id]['filename'],
																			'description' => make_excerpt($file_data[$use_id]['description'],200)
																		);
							}
							else {
								$notifications_inactive[] = $notification['id'];
							}
						}
					}
				}
			}
		}
	}

	/** Prepare the emails for CLIENTS */
	if (!empty($notes_to_clients)) {
		foreach ($notes_to_clients as $mail_username => $mail_files) {

			/** Reset the files list UL contents */
			$files_list = '';
			foreach ($mail_files as $mail_file) {
				/** Make the list of files */
				$files_list.= '<li style="margin-bottom:11px;">';
				$files_list.= '<p style="font-weight:bold; margin:0 0 5px 0; font-size:14px;">'.$mail_file['file_name'].'</p>';
				if (!empty($mail_file['description'])) {
					$files_list.= '<p>'.$mail_file['description'].'</p>';
				}
				$files_list.= '</li>';
				/**
				 * Add each notification to an array
				 */
				$this_client_notifications[] = $mail_file['notif_id'];
			}

			$address = $mail_by_user[$mail_username];
			/** Create the object and send the email */
			$notify_client = new PSend_Email();
			$email_arguments = array(
										'type' => 'new_files_for_client',
										'address' => $address,
										'files_list' => $files_list
									);
			$try_sending = $notify_client->psend_send_email($email_arguments);
			if ($try_sending == 1) {
				$notifications_sent = array_merge($notifications_sent, $this_client_notifications);
			}
			else {
				$notifications_failed = array_merge($notifications_failed, $this_client_notifications);
			}
		}
	}
	
	/** Prepare the emails for ADMINS */
	
	if (!empty($notes_to_admin)) {
		foreach ($notes_to_admin as $mail_username => $admin_files) {
			
			/** Check if the admin is active */
			if (isset($creators_data[$mail_username]) && $creators_data[$mail_username]['active'] == '1') {
				/** Reset the files list UL contents */
				$files_list = '';
				foreach ($admin_files as $client_uploader => $mail_files) {
	
					$files_list.= '<li style="font-size:15px; font-weight:bold; margin-bottom:5px;">'.$client_uploader.'</li>';
					foreach ($mail_files as $mail_file) {
						/** Make the list of files */
						$files_list.= '<li style="margin-bottom:11px;">';
						$files_list.= '<p style="font-weight:bold; margin:0 0 5px 0;">'.$mail_file['file_name'].'</p>';
						if (!empty($mail_file['description'])) {
							$files_list.= '<p>'.$mail_file['description'].'</p>';
						}
						$files_list.= '</li>';
						/**
						 * Add each notification to an array
						 */
						$this_admin_notifications[] = $mail_file['notif_id'];
					}
	
					$address = $mail_by_user[$mail_username];
					/** Create the object and send the email */
					$notify_admin = new PSend_Email();
					$email_arguments = array(
												'type' => 'new_file_by_client',
												'address' => $address,
												'files_list' => $files_list
											);
					$try_sending = $notify_admin->psend_send_email($email_arguments);
					if ($try_sending == 1) {
						$notifications_sent = array_merge($notifications_sent, $this_admin_notifications);
					}
					else {
						$notifications_failed = array_merge($notifications_failed, $this_admin_notifications);
					}
				}
			}
			else {
				/** Admin is not active */
				foreach ($admin_files as $mail_files) {
					foreach ($mail_files as $mail_file) {
						$notifications_inactive[] = $mail_file['notif_id'];
					}
				}
			}
		}
	}


	//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ CC al mail admin principal  */

	/**
	 * Mark the notifications as correctly sent.
	 */
	if (!empty($notifications_sent) && count($notifications_sent) > 0) {
		$notifications_sent = implode(',',array_unique($notifications_sent));
		$statement = $dbh->prepare("UPDATE " . TABLE_NOTIFICATIONS . " SET sent_status = '1' WHERE FIND_IN_SET(id, :sent)");
		$statement->bindParam(':sent', $notifications_sent);
		$statement->execute();

		$msg = __('E-mail notifications have been sent.','cftp_admin');
		echo system_message('ok',$msg);
	}

	/**
	 * Mark the notifications as ERROR, and increment
	 * the amount of times it failed by 1.
	 */
	if (!empty($notifications_failed) && count($notifications_failed) > 0) {
		$notifications_failed = implode(',',array_unique($notifications_failed));
		$statement = $dbh->prepare("UPDATE " . TABLE_NOTIFICATIONS . " SET sent_status = '0', times_failed = times_failed + 1 WHERE FIND_IN_SET(id, :failed)");
		$statement->bindParam(':failed', $notifications_failed);
		$statement->execute();

		$msg = __("One or more notifications couldn't be sent.",'cftp_admin');
		echo system_message('error',$msg);
	}

	/**
	 * There are notifications that will not be sent because
	 * the user for which the file is, or the admin who created
	 * the client that just uploaded a file is marked as INACTIVE
	 */
	if (!empty($notifications_inactive) && count($notifications_inactive) > 0) {
		$notifications_inactive = implode(',',array_unique($notifications_inactive));
		$statement = $dbh->prepare("UPDATE " . TABLE_NOTIFICATIONS . " SET sent_status = '3' WHERE FIND_IN_SET(id, :inactive)");
		$statement->bindParam(':inactive', $notifications_inactive);
		$statement->execute();

		if (CURRENT_USER_LEVEL == 0) {
			/**
			 * Clients do not need to know about the status of the
			 * creator's account. Show the ok message instead.
			 */
			$msg = __('E-mail notifications have been sent.','cftp_admin');
			echo system_message('ok',$msg);
		}
		else {
			$msg = __('E-mail notifications for inactive clients were not sent.','cftp_admin');
			echo system_message('error',$msg);
		}
	}
	
	
	
	/**
	 * DEBUG
	 */
	 /*
	 echo '<h2>Notifications Found</h2><br /><pre>';
	 print_r($notes_to_admin);
	 echo '</pre><br /><br />';

	 echo '<h2>Notifications sent query</h2><br />' . $notifications_sent_query . '<br /><br />';
	 */
}