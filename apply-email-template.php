<?php
/**
 * Apply Email Template
 * Loads a template's header and footer into the system options
 */
require_once 'bootstrap.php';
check_access_enhanced(['edit_settings']);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Check CSRF token
if (!validateCsrfToken()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$template_id = isset($_POST['template_id']) ? $_POST['template_id'] : null;

if (!$template_id) {
    echo json_encode(['success' => false, 'message' => 'Template ID required']);
    exit;
}

$emails = new \ProjectSend\Classes\Emails;
$template_data = $emails->getTemplateData($template_id);

if (!$template_data) {
    echo json_encode(['success' => false, 'message' => 'Template not found']);
    exit;
}

// Get template content
$header_content = $emails->getTemplateContent($template_id, 'header');
$footer_content = $emails->getTemplateContent($template_id, 'footer');

if (!$header_content || !$footer_content) {
    echo json_encode(['success' => false, 'message' => 'Could not load template content']);
    exit;
}

// Save the template content to options
$header_saved = save_option('email_header_text', $header_content);
$footer_saved = save_option('email_footer_text', $footer_content);
$customize_enabled = save_option('email_header_footer_customize', 1);

if ($header_saved && $footer_saved && $customize_enabled) {
    /** Record the action log */
    $logger = new \ProjectSend\Classes\ActionsLog;
    $new_record_action = $logger->addEntry([
        'action' => 48,
        'owner_id' => CURRENT_USER_ID,
        'owner_user' => CURRENT_USER_USERNAME,
        'details' => [
            'template_applied' => $template_id,
            'template_name' => $template_data['name'],
        ],
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Template applied successfully',
        'template_name' => $template_data['name']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save template settings']);
}
?>