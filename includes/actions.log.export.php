<?php
/**
 * Export actions log as CSV
 */
require_once '../bootstrap.php';
redirect_if_not_logged_in();

if (!current_user_can('view_actions_log')) {
    exit_with_error_code(403);
}

// Get format parameter
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

if ($format !== 'csv') {
    exit_with_error_code(400);
}

// Get all log entries (no filtering for iframe requests as we can't access form data)
$sql = "SELECT * FROM " . TABLE_LOG . " ORDER BY timestamp DESC";
$statement = $dbh->prepare($sql);
$statement->execute();
$log_entries = $statement->fetchAll(PDO::FETCH_ASSOC);

// Set cookie to indicate download has started (used by JavaScript)
setcookie('log_download_started', '1', time() + 300, '/');

// Clean any output that might have been sent
if (ob_get_length()) {
    ob_clean();
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="actions-log-' . date('Y-m-d-H-i-s') . '.csv"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Create file pointer connected to output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    __('Date/Time', 'cftp_admin'),
    __('Action', 'cftp_admin'),
    __('Owner', 'cftp_admin'),
    __('Affected File', 'cftp_admin'),
    __('Affected Account', 'cftp_admin'),
    __('Details', 'cftp_admin')
]);

// Add log data
foreach ($log_entries as $log) {
    // Format the action using the same function as the main actions log page
    $formatted_action = format_action_log_record($log);

    fputcsv($output, [
        $formatted_action['timestamp'] ?? $log['timestamp'] ?? '',
        $formatted_action['formatted'] ?? ($formatted_action['action'] ?? $log['action'] ?? ''),
        $log['owner_user'] ?? '',
        $log['affected_file_name'] ?? '',
        $log['affected_account_name'] ?? '',
        $log['details'] ?? ''
    ]);
}

fclose($output);
exit;