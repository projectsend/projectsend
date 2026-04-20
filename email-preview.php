<?php
/**
 * Show a preview of the currently selected e-mail template
 */
require_once 'bootstrap.php';
check_access_enhanced(['edit_settings']);

$page_title = __('E-mail templates','cftp_admin') . ': ' . __('Preview','cftp_admin');

$active_nav = 'options';

// Get the preview type
$type = $_GET['t'];

// Generate the preview using the email sending class
$email = new \ProjectSend\Classes\Emails;
echo $email->send([
    'preview' => true,
    'type' => $type,
]);

ob_end_flush();
