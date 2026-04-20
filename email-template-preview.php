<?php
/**
 * Email Template Preview
 * Shows a preview of a selected email template
 */
require_once 'bootstrap.php';
check_access_enhanced(['edit_email_templates']); // Check for email template editing permission

$template_id = isset($_GET['template']) ? $_GET['template'] : null;

if (!$template_id) {
    exit('Template ID required');
}

$emails = new \ProjectSend\Classes\Emails;
$template_data = $emails->getTemplateData($template_id);

if (!$template_data) {
    exit('Template not found');
}

$preview_content = $emails->previewTemplate($template_id);

if (!$preview_content) {
    exit('Could not generate template preview');
}

// Set content type
header('Content-Type: text/html; charset=utf-8');

// Output the preview
echo $preview_content;
?>