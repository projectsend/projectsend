<?php
/**
 * Handle Cron tasks
 */
namespace ProjectSend\Classes;

use \PDO;

class Cron extends Base
{
    private $results;
    private $results_formatted;

    public function __construct()
    {
        parent::__construct();

        $this->runPreChecks();

        $this->results = [];
        $this->results_formatted = '';
    }

    private function runPreChecks()
    {
        // Exit if execution is disabled via settings
        $this->exitIfCronIsDisabled();

        // Check if we are on command line and if not, check if execution is allowed 
        $this->exitIfCliOnly();

        // Check the security key
        $this->exitIfSecurityKeyFails();

        // Global, cron is authorized
        define('CRON_TASKS_AUTHORIZED', true);
    }

    private function exitIfCronIsDisabled()
    {
        if (get_option('cron_enable') != '1') {
            exit_with_error_code(403);
        }
    }

    private function exitIfCliOnly()
    {
        $cli_ony = get_option('cron_command_line_only');
        if (PHP_SAPI != 'cli' && $cli_ony == '1') {
            exit_with_error_code(403);
        }
    }

    private function exitIfSecurityKeyFails()
    {
        global $argc, $argv;

        if (PHP_SAPI == 'cli') {
            parse_str($argv[1], $params);
            $key = $params['key'];
        } else {
            $key = $_GET['key'];
        }

        if (empty($key)) {
            exit_with_error_code(403);
        }

        if (get_option('cron_key') != $key) {
            exit_with_error_code(403);
        }
    }

    public function runTasks()
    {
        $this->runTaskSendEmails();

        $this->runTaskDeleteExpiredFiles();

        $this->runTaskDeleteOrphanFiles();

        $this->emailResults();

        $this->saveResultsToDatabase();
    }

    public function outputResults()
    {
        if (PHP_SAPI != 'cli') {
            echo nl2br($this->results_formatted);
            return;
        }

        echo $this->results_formatted;
    }

    private function runTaskSendEmails()
    {
        $results = [
            'title' => __('Send email notifications', 'cftp_admin'),
            'enabled' => get_option('cron_send_emails'),
            'elements' => [],
        ];

        if (get_option('cron_send_emails') == '1') {
            $notifications = new \ProjectSend\Classes\EmailNotifications();
            $found = $notifications->getPendingNotificationsFromDatabase();
            $results['elements']['found'] = [
                'label' => __('Found', 'cftp_admin'),
                'items' => []
            ];

            foreach ($found['pending'] as $notification) {
                $results['elements']['found']['items'][] = $notification['id']; 
            }

            $notifications->sendNotifications();
            $results['elements']['sent'] = [
                'label' => __('Succesfully sent', 'cftp_admin'),
                'items' => $notifications->getNotificationsSent()
            ];
            $results['elements']['failed'] = [
                'label' => __('Failed', 'cftp_admin'),
                'items' => $notifications->getNotificationsFailed()
            ];
            $results['elements']['inactive_accounts'] = [
                'label' => __('Skipped (inactive accounts)', 'cftp_admin'),
                'items' => $notifications->getNotificationsInactiveAccounts()
            ];
        }

        $this->results['email_notifications'] = $results;
        $this->formatResultsForDisplay($results);
    }

    private function runTaskDeleteExpiredFiles()
    {
        $results = [
            'title' => __('Delete expired files', 'cftp_admin'),
            'enabled' => get_option('cron_delete_expired_files'),
            'elements' => [],
        ];

        $files = [];
        if (get_option('cron_delete_expired_files') == '1') {
            $statement = $this->dbh->prepare( "SELECT * FROM " . TABLE_FILES . " WHERE expires='1'" );
            $statement->execute();
            $results['elements']['found'] = [
                'label' => __('Found', 'cftp_admin'),
                'items' => []
            ];
            if ($statement->rowCount() > 0) {
                $statement->setFetchMode(PDO::FETCH_ASSOC);
                while( $row = $statement->fetch() ) {
                    $files[$row['id']] = $row['original_url'];
                }
            }

            $results['elements']['success'] = [
                'label' => __('Succesfully deleted', 'cftp_admin'),
                'items' => []
            ];
            $results['elements']['failed'] = [
                'label' => __('Failed', 'cftp_admin'),
                'items' => []
            ];

            foreach ($files as $file_id => $file_name) {
                $file = new \ProjectSend\Classes\Files($file_id);
                if ($file->isExpired()) {
                    $results['elements']['found']['items'][] = $file_name;
                    if ($file->deleteFiles()) {
                        $results['elements']['success']['items'][] = $file_name;
                    } else {
                        $results['elements']['failed']['items'][] = $file_name;
                    }
                }
            }
        }

        $this->results['delete_expired_files'] = $results;
        $this->formatResultsForDisplay($results);
    }

    private function runTaskDeleteOrphanFiles()
    {
        $results = [
            'title' => __('Delete orphan files', 'cftp_admin'),
            'enabled' => get_option('cron_delete_orphan_files'),
            'elements' => [],
        ];

        if (get_option('cron_delete_expired_files') == '1') {
            $files = [];
            $object = new \ProjectSend\Classes\OrphanFiles;
            $orphan_files = $object->getFiles();

            $results['elements'] = [
                'found' => [
                    'label' => __('Found', 'cftp_admin'),
                    'items' => []
                ],
                'success' => [
                    'label' => __('Succesfully deleted', 'cftp_admin'),
                    'items' => []
                ],
                'failed' => [
                    'label' => __('Failed', 'cftp_admin'),
                    'items' => []
                ],
            ];

            // Add allowed files to the found files array
            if (get_option('cron_delete_orphan_files_types') == 'all') {
                if (!empty($orphan_files['allowed'])) {
                    foreach ($orphan_files['allowed'] as $file) {
                        $results['elements']['found']['items'][] = $file['name'];
                    }
                }
            }

            // Add not allowed files to the found files array
            if (!empty($orphan_files['not_allowed'])) {
                foreach ($orphan_files['not_allowed'] as $file) {
                    $results['elements']['found']['items'][] = $file['name'];
                }
            }

            foreach ($results['elements']['found']['items'] as $file) {
                if (delete_file_from_disk(UPLOADED_FILES_DIR.DS.$file)) {
                    $results['elements']['success']['items'][] = $file;
                } else {
                    $results['elements']['failed']['items'][] = $file;
                }
            }
        }

        $this->results['delete_expired_files'] = $results;
        $this->formatResultsForDisplay($results);
    }

    private function formatResultsForDisplay($results = [])
    {
        if (empty($results)) {
            return;
        }

        $this->results_formatted .= $results['title'].PHP_EOL.PHP_EOL;

        if ($results['enabled'] == 0) {
            $this->results_formatted .= __('Task disabled. Skipping.', 'cftp_admin').PHP_EOL.PHP_EOL;
            return;
        }

        if (!empty($results['elements'])) {
            foreach ($results['elements'] as $type => $data) {
                $items = implode(',', $data['items']);
                $this->results_formatted .= $data['label'].PHP_EOL;
                $this->results_formatted .= __('Total:', 'cftp_admin').' '.count($data['items']).PHP_EOL;
                if (!empty($data['items'])) {
                    $this->results_formatted .= 'Items'.PHP_EOL.$items.PHP_EOL;
                }
                $this->results_formatted .= PHP_EOL;
            }
    
            $this->results_formatted .= PHP_EOL.PHP_EOL;
        }
    }

    private function emailResults()
    {
        if (get_option('cron_email_summary_send') == '1') {
            $option_to = get_option('cron_email_summary_address_to');
            $to = (!empty($option_to)) ? $option_to : get_option('admin_email_address');
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            $message = nl2br($this->results_formatted);

            $email = new \ProjectSend\Classes\Emails;
            $email->send([
                'type' => 'generic',
                'to' => $to,
                'subject' => __('Cron results', 'cftp_admin'),
                'message' => $message,
            ]);
        }
    }

    private function saveResultsToDatabase()
    {
        if (get_option('cron_save_log_database') == '1') {
            try {
                $sapi = PHP_SAPI;
                $results = json_encode($this->formatResultsForDatabase($this->results));
                $statement = $this->dbh->prepare("INSERT INTO " . TABLE_CRON_LOG . " (sapi, results)"
                        ."VALUES (:sapi, :results)");
                $statement->bindParam(':sapi', $sapi);
                $statement->bindParam(':results', $results);
                $statement->execute();
            } catch(\PDOException $e) {
                return $e;
            }
    
            return;
        }
    }

    private function formatResultsForDatabase($results)
    {
        $return = [];
        foreach ($results as $task => $data) {
            $return[$task] = [];
            if (!empty($data['elements'])) {
                foreach ($data['elements'] as $key => $items) {
                    $return[$task][$key] = $items['items'];
                }
            }
        }

        return $return;
    }
}