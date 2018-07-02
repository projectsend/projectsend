<?php
/**
 * After establishing a database connection, the next thing is to get the site options
 *
 * @package		ProjectSend
 * @subpackage	autostart
 */

/** Get system options */
global $options;
$options = new \ProjectSend\Options();
$options->retrieve();

/**
* Versions prior to 1.0 used the number of the current commit, with a preceding "r"
* as Google Code used to do.
* If the currently installed version is indeed named like this, then the extracted
* number from the version is converted to 0.{version}, which will force the
* updating process.
*/
function convert_old_version_number($v) {
    $v = ( str_pad($v, 4, "0", STR_PAD_LEFT) ) / 10000;
    return $v;
}