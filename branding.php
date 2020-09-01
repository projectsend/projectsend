<?php
/**
 * Shows the current company logo and a form to upload
 * a new one.
 * This image is used on the files list templates later.
 *
 * @package ProjectSend
 * @subpackage Upload
 *
 * deprecated
 * Branding was moved to the general options page. A redirect takes place here.
 */
$allowed_levels = array(9);
require_once('sys.includes.php');

/** Redirect so the options are reflected immediatly */
while (ob_get_level()) ob_end_clean();
$location = BASE_URI . 'options.php?section=branding';
header("Location: $location");
die();