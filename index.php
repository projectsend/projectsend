<?php
/**
 * ------------ ERROR ------------
 *
 * If you are seeing this message then your webserver is not running PHP.
 * Please contact your administrator or service provider to solve this issue.
 * ProjectSend cannot be executed without php and a database engine like MySQL
 *
 * ------ END ERROR MESSAGE ------
 *
 * ProjectSend is an open source, clients-oriented, private file sharing web
 * application.
 * Clients are created and assigned a username and a password. Then you can
 * upload as much files as you want under each account, and optionally add
 * a name and description to them.
 *
 * ProjectSend is hosted on github.
 * Feel free to participate!
 *
 * @package		ProjectSend
 * @link			https://github.com/ignacionelson/ProjectSend/
 * @license		http://www.gnu.org/licenses/gpl-2.0.html GNU GPL version 2
 * @author		Ignacio Nelson <contact@projectsend.org>
 *
 */

/** Packages loaded from Composer */
require_once dirname(__FILE__) . '/lib/vendor/autoload.php';

/** ProjectSend's classes and functions files */
require_once dirname(__FILE__) . '/sys.includes.php';

/** Initiate */
$app = new \ProjectSend\ProjectSend();
$auth = new \ProjectSend\Auth();
