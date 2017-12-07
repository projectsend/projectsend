<?php

/**
 * This file implements a autoloader for SimpleSAMLphp modules.
 *
 * @author Boy Baukema, SURFnet
 * @package SimpleSAMLphp
 */

/**
 * Autoload function for SimpleSAMLphp modules.
 *
 * @param string $className Name of the class.
 */
function SimpleSAML_autoload($className)
{
    $modulePrefixLength = strlen('sspmod_');
    $classPrefix = substr($className, 0, $modulePrefixLength);
    if ($classPrefix !== 'sspmod_') {
        return;
    }

    $modNameEnd = strpos($className, '_', $modulePrefixLength);
    $module = substr($className, $modulePrefixLength, $modNameEnd - $modulePrefixLength);
    $moduleClass = substr($className, $modNameEnd + 1);

    if (!SimpleSAML_Module::isModuleEnabled($module)) {
        return;
    }

    $file = SimpleSAML_Module::getModuleDir($module) . '/lib/' . str_replace('_', '/', $moduleClass) . '.php';

    if (file_exists($file)) {
        require_once($file);
    }
}

spl_autoload_register('SimpleSAML_autoload');
