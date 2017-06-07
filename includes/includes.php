<?php
/**
 * Requirements and inclussions of basic system files.
 *
 * Left here for backwards compatibility, since this file is directly
 * loaded by the index.php file generated on the clients folders.
 *
 * @package ProjectSend
 * @deprecated Since r120
 *
 */

/** Basic system constants */
require_once('sys.vars.php');

/** Text strings used on various files */
require_once('includes/vars.php');

/** Basic functions to be accessed from anywhere */
require_once('includes/functions.php');

/** Contains the session and cookies validation functions */
require_once('includes/userlevel_check.php');

/** Template list generator */
require_once('includes/templates.php');

/**
 * Always include this classes to avoid repetition of code
 * on other files.
 *
 */
require_once('includes/classes/actions-clients.php');
require_once('includes/classes/actions-files.php');
require_once('includes/classes/actions-groups.php');
require_once('includes/classes/actions-log.php');
require_once('includes/classes/actions-users.php');
require_once('includes/classes/file-upload.php');
require_once('includes/classes/form-validation.php');
require_once('includes/classes/send-email.php');
?>