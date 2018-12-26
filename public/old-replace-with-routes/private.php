<?php
/**
 * Landing page for clients. Loads the selected template and displays
 * the files list.
 *
 * @package		ProjectSend
 * @subpackage	Files
 *
 */
require_once('./bootstrap.php');
define('IS_TEMPLATE_VIEW', true);

$this_user = CURRENT_USER_USERNAME;

if (!empty($_GET['client']) && CURRENT_USER_LEVEL != '0') {
    $this_user = $_GET['client'];
}

include_once SELECTED_TEMPLATE_MAIN_FILE;