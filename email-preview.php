<?php
/**
 * Show a preview of the currently selected e-mail template
 *
 * @package ProjectSend
 * @subpackage Options
 */
$allowed_levels = array(9);
require_once('sys.includes.php');

$page_title = __('E-mail templates','cftp_admin') . ': ' . __('Preview','cftp_admin');

$active_nav = 'options';

/** Do a couple of functions that are in header.php */
/** Check for an active session or cookie */
check_for_session();

can_see_content($allowed_levels);

/** Get the preview type */
$type = $_GET['t'];

/** Generate the preview using the email sending class */
$preview = new PSend_Email();
$preview_arguments = array(
								'preview'	=> true,
								'type'		=> $type,
							);
$preview_results = $preview->psend_send_email($preview_arguments);
echo $preview_results;

ob_end_flush();