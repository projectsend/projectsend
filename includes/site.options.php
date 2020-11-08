<?php
/**
 * Gets all the options from the database and define each as a constant.
 *
 * @package		ProjectSend
 * @subpackage	Core
 *
 */

/**
 * If options exists, call the method to set the constants.
 */
global $dbh;
global $options;

try {
	$options = $dbh->query("SELECT * FROM " . TABLE_OPTIONS);
    $options->setFetchMode(PDO::FETCH_ASSOC);

	if ( $options->rowCount() > 0) {
        $options = new ProjectSend\Classes\Options;
        $options->getAll();
    }
}
catch ( Exception $e ) {
	return false;
}