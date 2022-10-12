<?php
define('VIEW_TYPE', 'template');

require_once '../bootstrap.php';

if (!defined('CURRENT_USER_USERNAME')) {
    ps_redirect('../index.php');
}

$view_files_as = (!empty($_GET['client']) && CURRENT_USER_LEVEL != '0') ? $_GET['client'] : CURRENT_USER_USERNAME;

require get_template_file_location('template.php');
