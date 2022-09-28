<?php
/**
 * Gets all the options from the database and define each as a constant.
 */

// If options exists, call the method to set the constants.
global $dbh;

try {
    $options = new ProjectSend\Classes\Options;
    $options->setSystemConstants();
} catch (Exception $e) {
    return false;
}
