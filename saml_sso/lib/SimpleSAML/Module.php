<?php


/**
 * Helper class for accessing information about modules.
 *
 * @author Olav Morken, UNINETT AS.
 * @package SimpleSAMLphp
 */
class SimpleSAML_Module
{


    /**
     * Retrieve the base directory for a module.
     *
     * The returned path name will be an absolute path.
     *
     * @param string $module Name of the module
     *
     * @return string The base directory of a module.
     */
    public static function getModuleDir($module)
    {
        $baseDir = dirname(dirname(dirname(__FILE__))).'/modules';
        $moduleDir = $baseDir.'/'.$module;

        return $moduleDir;
    }


    /**
     * Determine whether a module is enabled.
     *
     * Will return false if the given module doesn't exists.
     *
     * @param string $module Name of the module
     *
     * @return bool True if the given module is enabled, false otherwise.
     *
     * @throws Exception If module.enable is set and is not boolean.
     */
    public static function isModuleEnabled($module)
    {

        $moduleDir = self::getModuleDir($module);

        if (!is_dir($moduleDir)) {
            return false;
        }

        $globalConfig = SimpleSAML_Configuration::getOptionalConfig();
        $moduleEnable = $globalConfig->getArray('module.enable', array());

        if (isset($moduleEnable[$module])) {
            if (is_bool($moduleEnable[$module]) === true) {
                return $moduleEnable[$module];
            }

            throw new Exception("Invalid module.enable value for for the module $module");
        }

        if (assert_options(ASSERT_ACTIVE) &&
            !file_exists($moduleDir.'/default-enable') &&
            !file_exists($moduleDir.'/default-disable')
        ) {
            SimpleSAML_Logger::error("Missing default-enable or default-disable file for the module $module");
        }

        if (file_exists($moduleDir.'/enable')) {
            return true;
        }

        if (!file_exists($moduleDir.'/disable') && file_exists($moduleDir.'/default-enable')) {
            return true;
        }

        return false;
    }


    /**
     * Get available modules.
     *
     * @return array One string for each module.
     *
     * @throws Exception If we cannot open the module's directory.
     */
    public static function getModules()
    {

        $path = self::getModuleDir('.');

        $dh = opendir($path);
        if ($dh === false) {
            throw new Exception('Unable to open module directory "'.$path.'".');
        }

        $modules = array();

        while (($f = readdir($dh)) !== false) {
            if ($f[0] === '.') {
                continue;
            }

            if (!is_dir($path.'/'.$f)) {
                continue;
            }

            $modules[] = $f;
        }

        closedir($dh);

        return $modules;
    }


    /**
     * Resolve module class.
     *
     * This function takes a string on the form "<module>:<class>" and converts it to a class
     * name. It can also check that the given class is a subclass of a specific class. The
     * resolved classname will be "sspmod_<module>_<$type>_<class>.
     *
     * It is also possible to specify a full classname instead of <module>:<class>.
     *
     * An exception will be thrown if the class can't be resolved.
     *
     * @param string      $id The string we should resolve.
     * @param string      $type The type of the class.
     * @param string|null $subclass The class should be a subclass of this class. Optional.
     *
     * @return string The classname.
     *
     * @throws Exception If the class cannot be resolved.
     */
    public static function resolveClass($id, $type, $subclass = null)
    {
        assert('is_string($id)');
        assert('is_string($type)');
        assert('is_string($subclass) || is_null($subclass)');

        $tmp = explode(':', $id, 2);
        if (count($tmp) === 1) {
            $className = $tmp[0];
        } else {
            $className = 'sspmod_'.$tmp[0].'_'.$type.'_'.$tmp[1];
        }

        if (!class_exists($className)) {
            throw new Exception(
                'Could not resolve \''.$id.'\': No class named \''.$className.'\'.'
            );
        } elseif ($subclass !== null && !is_subclass_of($className, $subclass)) {
            throw new Exception(
                'Could not resolve \''.$id.'\': The class \''.$className.'\' isn\'t a subclass of \''.$subclass.'\'.'
            );
        }

        return $className;
    }


    /**
     * Get absolute URL to a specified module resource.
     *
     * This function creates an absolute URL to a resource stored under ".../modules/<module>/www/".
     *
     * @param string $resource Resource path, on the form "<module name>/<resource>"
     * @param array  $parameters Extra parameters which should be added to the URL. Optional.
     *
     * @return string The absolute URL to the given resource.
     */
    public static function getModuleURL($resource, array $parameters = array())
    {
        assert('is_string($resource)');
        assert('$resource[0] !== "/"');

        $url = \SimpleSAML\Utils\HTTP::getBaseURL().'module.php/'.$resource;
        if (!empty($parameters)) {
            $url = \SimpleSAML\Utils\HTTP::addURLParameters($url, $parameters);
        }
        return $url;
    }


    /**
     * Call a hook in all enabled modules.
     *
     * This function iterates over all enabled modules and calls a hook in each module.
     *
     * @param string $hook The name of the hook.
     * @param mixed  &$data The data which should be passed to each hook. Will be passed as a reference.
     */
    public static function callHooks($hook, &$data = null)
    {
        assert('is_string($hook)');

        $modules = self::getModules();
        sort($modules);
        foreach ($modules as $module) {
            if (!self::isModuleEnabled($module)) {
                continue;
            }

            $hookfile = self::getModuleDir($module).'/hooks/hook_'.$hook.'.php';
            if (!file_exists($hookfile)) {
                continue;
            }

            require_once($hookfile);

            $hookfunc = $module.'_hook_'.$hook;
            assert('is_callable($hookfunc)');

            $hookfunc($data);
        }
    }
}
