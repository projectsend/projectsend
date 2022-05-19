<?php
/**
 * @package		ProjectSend
 * @subpackage	Classes
 *
 * @todo: completely remake this class. Right now It's just a cleaner port of upload-send-notifications.php
 */
namespace ProjectSend\Classes;
use \PDO;

class EmailNotifications
{
    private $notifications_sent;
    private $notifications_failed;
    private $notifications_inactive_accounts;

    private $mail_by_user;
    private $clients_data;
    private $files_data;
    private $creators;

    private $dbh;

    public function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;

        $this->mail_by_user = [];
        $this->clients_data = [];
        $this->files_data = [];
        $this->creators = [];
    }

    public function getNotificationsSent()
    {
        return $this->notifications_sent;
    }

    public function getNotificationsFailed()
    {
        return $this->notifications_failed;
    }

    public function getNotificationsInactiveAccounts()
    {
        return $this->notifications_inactive_accounts;
    }

    public function getPendingNotificationsFromDatabase($parameters = [])
    {
        $notifications = [
            'pending' => [],
            'to_admins' => [],
            'to_clients' => [],
        ];

        // Get notifications
        $params = [];
        $query = "SELECT * FROM " . TABLE_NOTIFICATIONS . " WHERE sent_status = '0' AND times_failed < :times";
        $params[':times'] = get_option('notifications_max_tries');

        // In case we manually want to send specific notifications
        if (!empty($parameters['notification_id_in'])) {
            $notification_id_in = implode(',', array_map( 'intval', $parameters['notification_id_in'] ));
            if (!empty($notification_id_in)) {
                $query .= " AND FIND_IN_SET(id, :notification_id_in)";
                $params[':notification_id_in'] = $notification_id_in;
            }
        }

        // Add the time limit
        if (get_option('notifications_max_days') != '0') {
            $query .= " AND timestamp >= DATE_SUB(NOW(), INTERVAL :days DAY)";
            $params[':days'] = get_option('notifications_max_days');
        }

        if (get_option('notifications_max_emails_at_once') != '0') {
            $query .= " LIMIT :limit";
            $params[':limit'] = get_option('notifications_max_emails_at_once');
        }

        $statement = $this->dbh->prepare( $query );
        $statement->execute( $params );
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        while ($row = $statement->fetch()) {
            $notifications['pending'][] = array(
                'id' => $row['id'],
                'client_id' => $row['client_id'],
                'file_id' => $row['file_id'],
                'timestamp' => $row['timestamp'],
                'uploader_type' => ($row['upload_type'] == '0') ? 'client' : 'user',
            );

            // Add the file data to the global array
            if (!array_key_exists($row['file_id'], $this->files_data)) {
                $file = new \ProjectSend\Classes\Files();
                $file->get($row['file_id']);
                $this->files_data[$file->id] = array(
                    'id'=> $file->id,
                    'filename' => $file->filename_original,
                    'title' => $file->title,
                    'description' => $file->description,
                );
            }

            // Add the file data to the global array
            if (!array_key_exists($row['client_id'], $this->clients_data)) {
                $client = get_client_by_id($row['client_id']);
                if (!empty($client)) {
                    $this->clients_data[$row['client_id']] = $client;
                    $this->mail_by_user[$client['username']] = $client['email'];
            
                    if (!array_key_exists($client['created_by'], $this->creators)) {
                        $user = get_user_by_username($client['created_by']);
                        if (!empty($user)) {
                            $this->creators[$client['created_by']] = $user;
                            $this->mail_by_user[$client['created_by']] = $user['email'];
                        }
                    }
                }
            }
        }

        // Prepare the list of clients and admins that will be notified, adding to each one the corresponding files.
        if (!empty($this->clients_data)) {
            foreach ($this->clients_data as $client) {
                foreach ($notifications['pending'] as $notification) {
                    if ($notification['client_id'] == $client['id']) {
                        // Set up file data
                        $notification_data = [
                            'notification_id' => $notification['id'],
                            'file_id' => $notification['file_id'],
                        ];

                        if ($notification['uploader_type'] == 'client') {
                            // Add the file to the account's creator email
                            $notifications['to_admins'][$client['created_by']][$client['name']][] = $notification_data;
                        }
                        elseif ($notification['uploader_type'] == 'user') {
                            if ($client['notify_upload'] == '1') {
                                if ($client['active'] == '1') {
                                    // If file is uploaded by user, add to client's email body
                                    $notifications['to_clients'][$client['username']][] = $notification_data;
                                }
                                else {
                                    $this->notifications_inactive_accounts[] = $notification['id'];
                                }
                            }
                        }
                    }
                }
            }
        }

        return $notifications;
    }

    public function sendNotifications()
    {
        $this->notifications_sent = [];
        $this->notifications_failed = [];

        $notifications = $this->getPendingNotificationsFromDatabase();

        if (empty($notifications['pending'])) {
            $return = [
                'status' => 'success',
                'message' => __('No pending notifications found', 'cftp_admin'),
            ];

            return $return;
        }

        // Send the emails to ADMINS
        $this->sendNotificationsToAdmins($notifications['to_admins']);

        // Send the emails to CLIENTS
        $this->sendNotificationsToClients($notifications['to_clients']);
        
        // Update the database
        $this->updateDatabaseNotificationsSent($this->notifications_sent);
        $this->updateDatabaseNotificationsFailed($this->notifications_failed);
        $this->updateDatabaseNotificationsInactiveAccounts($this->notifications_inactive_accounts);
    }

    private function sendNotificationsToAdmins($notifications = [])
    {
        if (!empty($notifications)) {
            foreach ($notifications as $mail_username => $admin_files) {
                // Check if the admin is active
                if (isset($this->creators[$mail_username]) && $this->creators[$mail_username]['active'] == '1') {
                    $processed_notifications = [];

                    foreach ($admin_files as $client_uploader => $files) {
                        $files_list_html = $this->makeFilesListHtml($files, $client_uploader);

                        // Add each notification to an array
                        foreach ($files as $file) {
                            $processed_notifications[] = $file['notification_id'];
                        }

                        // Create the object and send the email
                        $email = new \ProjectSend\Classes\Emails;
                        if ($email->send([
                            'type' => 'new_files_by_client',
                            'address' => $this->mail_by_user[$mail_username],
                            'files_list' => $files_list_html
                        ])) {
                            $this->notifications_sent = array_merge($this->notifications_sent, $processed_notifications);
                        }
                        else {
                            $this->notifications_failed = array_merge($this->notifications_failed, $processed_notifications);
                        }
                    }
                }
                else {
                    // Admin is not active
                    foreach ($admin_files as $mail_files) {
                        foreach ($mail_files as $mail_file) {
                            $this->notifications_inactive_accounts[] = $mail_file['notification_id'];
                        }
                    }
                }
            }
        }
    }

    private function sendNotificationsToClients($notifications = [])
    {
        if (!empty($notifications)) {
            $processed_notifications = [];

            foreach ($notifications as $mail_username => $files) {
                $files_list_html = $this->makeFilesListHtml($files);

                // Add each notification to an array
                foreach ($files as $file) {
                    $processed_notifications[] = $file['notification_id'];
                }

                // Create the object and send the email
                $email = new \ProjectSend\Classes\Emails;
                if ($email->send([
                    'type' => 'new_files_by_user',
                    'address' => $this->mail_by_user[$mail_username],
                    'files_list' => $files_list_html,
                ])) {
                    $this->notifications_sent = array_merge($this->notifications_sent, $processed_notifications);
                }
                else {
                    $this->notifications_failed = array_merge($this->notifications_failed, $processed_notifications);
                }
            }
        }
    }

    /**
     * Make the list of files for the default ul container
     */
    private function makeFilesListHtml($files, $uploader_username = null)
    {
        $html = '';

        if (!empty($uploader_username)) {
            $html .= '<li style="font-size:15px; font-weight:bold; margin-bottom:5px;">'.$uploader_username.'</li>';
        }

        foreach ($files as $file) {
            $file_data = $this->files_data[$file['file_id']];
            $html .= '<li style="margin-bottom:11px;">';
            $html .= '<p style="font-weight:bold; margin:0 0 5px 0; font-size:14px;">'.$file_data['title'].'<br>('.$file_data['filename'].')</p>';
            if (!empty($file_data['description'])) {
                $html .= '<p>'.$file_data['description'].'</p>';
            }
            $html .= '</li>';
        }

        return $html;
    }

    /**
    * Mark the notifications as correctly sent.
    */
    private function updateDatabaseNotificationsSent($notifications = [])
    {
        if (!empty($notifications) && count($notifications) > 0) {
            $notifications = implode(',',array_unique($notifications));
            $statement = $this->dbh->prepare("UPDATE " . TABLE_NOTIFICATIONS . " SET sent_status = '1' WHERE FIND_IN_SET(id, :sent)");
            $statement->bindParam(':sent', $notifications);
            $statement->execute();
        }
    }

    /**
    * Mark the notifications as ERROR, and increment
    * the amount of times it failed by 1.
    */
    private function updateDatabaseNotificationsFailed($notifications = [])
    {
        if (!empty($notifications) && count($notifications) > 0) {
            $notifications = implode(',',array_unique($notifications));
            $statement = $this->dbh->prepare("UPDATE " . TABLE_NOTIFICATIONS . " SET sent_status = '0', times_failed = times_failed + 1 WHERE FIND_IN_SET(id, :failed)");
            $statement->bindParam(':failed', $notifications);
            $statement->execute();
        }
    }

    /**
    * There are notifications that will not be sent because
    * the user for which the file is, or the admin who created
    * the client that just uploaded a file is marked as INACTIVE
    */
    private function updateDatabaseNotificationsInactiveAccounts($notifications)
    {
        if (!empty($notifications) && count($notifications) > 0) {
            $notifications = implode(',',array_unique($notifications));
            $statement = $this->dbh->prepare("UPDATE " . TABLE_NOTIFICATIONS . " SET sent_status = '3' WHERE FIND_IN_SET(id, :inactive)");
            $statement->bindParam(':inactive', $notifications);
            $statement->execute();
        }
    }
}
