<?php
/**
 * Requirements of basic system files.
 *
 * @package ProjectSend
 * @subpackage Core
 */

define('ROOT_DIR', dirname(__FILE__));

/** Basic system constants */
require_once ROOT_DIR . '/sys.vars.php';

/** Load the database class */
require_once CLASSES_DIR . '/database.php';

/**
 * Core function and classes files to include
 */
$includes = array(
	'security/xsrf.php', // Security
	'site.options.php', // Site options (conditional: !IS_MAKE_CONFIG)
	'language.php', // Load the language class and translation file
	'language-locales-names.php', // Load the language and locales names list
	'text-strings.php', // Text strings used on various files
	'functions.php', // Basic functions to be accessed from anywhere
	'custom.php', // Custom functions, file not included in PS
	'updates.functions.php', // Legacy updates functions
	'userlevel_check.php', // Contains the session and cookies validation functions
	'functions.templates.php', // Template list functions
	'active.session.php', // Contains the current session information (conditional: !IS_INSTALL)
	'timezone_identifiers_list.php', // Recreate the function if it doesn't exist. By Alan Reiblein
	'functions.categories.php', // Categories functions
	'functions.forms.php', // Search, filters and actions forms
);

foreach ( $includes as $filename ) {
	$location = INCLUDES_DIR . '/' . $filename;
	if ( file_exists( $location ) ) {
		require_once $location;
	}
}

/**
 * ProjectSend's own classes
 */
$classes_files = array(
						'projectsend.php',
						'actions-categories.php',
						'actions-clients.php',
						'actions-files.php',
						'actions-groups.php',
						'actions-log.php',
						'actions-members.php',
						'actions-users.php',
						'file-upload.php',
						'form-validation.php',
						'generate-form.php',
						'generate-table.php',
						'send-email.php',
						'update.php',
					);
foreach ( $classes_files as $filename ) {
	$location = CLASSES_DIR . '/' . $filename;
	if ( file_exists( $location ) ) {
		require_once $location;
	}
}
