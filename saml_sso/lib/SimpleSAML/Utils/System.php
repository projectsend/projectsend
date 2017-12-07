<?php
namespace SimpleSAML\Utils;

/**
 * System-related utility methods.
 *
 * @package SimpleSAMLphp
 */
class System
{

    const WINDOWS = 1;
    const LINUX = 2;
    const OSX = 3;
    const HPUX = 4;
    const UNIX = 5;
    const BSD = 6;
    const IRIX = 7;
    const SUNOS = 8;


    /**
     * This function returns the Operating System we are running on.
     *
     * @return mixed A predefined constant identifying the OS we are running on. False if we are unable to determine it.
     *
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function getOS()
    {
        if (stristr(PHP_OS, 'LINUX')) {
            return self::LINUX;
        }
        if (stristr(PHP_OS, 'DARWIN')) {
            return self::OSX;
        }
        if (stristr(PHP_OS, 'WIN')) {
            return self::WINDOWS;
        }
        if (stristr(PHP_OS, 'BSD')) {
            return self::BSD;
        }
        if (stristr(PHP_OS, 'UNIX')) {
            return self::UNIX;
        }
        if (stristr(PHP_OS, 'HP-UX')) {
            return self::HPUX;
        }
        if (stristr(PHP_OS, 'IRIX')) {
            return self::IRIX;
        }
        if (stristr(PHP_OS, 'SUNOS')) {
            return self::SUNOS;
        }
        return false;
    }


    /**
     * This function retrieves the path to a directory where temporary files can be saved.
     *
     * @return string Path to a temporary directory, without a trailing directory separator.
     * @throws \SimpleSAML_Error_Exception If the temporary directory cannot be created or it exists and does not belong
     * to the current user.
     *
     * @author Andreas Solberg, UNINETT AS <andreas.solberg@uninett.no>
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function getTempDir()
    {
        $globalConfig = \SimpleSAML_Configuration::getInstance();

        $tempDir = rtrim($globalConfig->getString('tempdir', sys_get_temp_dir().DIRECTORY_SEPARATOR.'simplesaml'),
            DIRECTORY_SEPARATOR);

        if (!is_dir($tempDir)) {
            if (!mkdir($tempDir, 0700, true)) {
                $error = error_get_last();
                throw new \SimpleSAML_Error_Exception('Error creating temporary directory "'.$tempDir.
                    '": '.$error['message']);
            }
        } elseif (function_exists('posix_getuid')) {
            // check that the owner of the temp directory is the current user
            $stat = lstat($tempDir);
            if ($stat['uid'] !== posix_getuid()) {
                throw new \SimpleSAML_Error_Exception('Temporary directory "'.$tempDir.
                    '" does not belong to the current user.');
            }
        }

        return $tempDir;
    }


    /**
     * Resolve a (possibly) relative path from the given base path.
     *
     * A path which starts with a '/' is assumed to be absolute, all others are assumed to be
     * relative. The default base path is the root of the SimpleSAMLphp installation.
     *
     * @param string      $path The path we should resolve.
     * @param string|null $base The base path, where we should search for $path from. Default value is the root of the
     *     SimpleSAMLphp installation.
     *
     * @return string An absolute path referring to $path.
     *
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     */
    public static function resolvePath($path, $base = null)
    {
        if ($base === null) {
            $config = \SimpleSAML_Configuration::getInstance();
            $base = $config->getBaseDir();
        }

        // remove trailing slashes
        $base = rtrim($base, '/');

        // check for absolute path
        if (substr($path, 0, 1) === '/') {
            // absolute path. */
            $ret = '/';
        } else {
            // path relative to base
            $ret = $base;
        }

        $path = explode('/', $path);
        foreach ($path as $d) {
            if ($d === '.') {
                continue;
            } elseif ($d === '..') {
                $ret = dirname($ret);
            } else {
                if (substr($ret, -1) !== '/') {
                    $ret .= '/';
                }
                $ret .= $d;
            }
        }

        return $ret;
    }


    /**
     * Atomically write a file.
     *
     * This is a helper function for writing data atomically to a file. It does this by writing the file data to a
     * temporary file, then renaming it to the required file name.
     *
     * @param string $filename The path to the file we want to write to.
     * @param string $data The data we should write to the file.
     * @param int    $mode The permissions to apply to the file. Defaults to 0600.
     *
     * @throws \InvalidArgumentException If any of the input parameters doesn't have the proper types.
     * @throws \SimpleSAML_Error_Exception If the file cannot be saved, permissions cannot be changed or it is not
     *     possible to write to the target file.
     *
     * @author Andreas Solberg, UNINETT AS <andreas.solberg@uninett.no>
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     * @author Andjelko Horvat
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function writeFile($filename, $data, $mode = 0600)
    {
        if (!is_string($filename) || !is_string($data) || !is_numeric($mode)) {
            throw new \InvalidArgumentException('Invalid input parameters');
        }

        $tmpFile = self::getTempDir().DIRECTORY_SEPARATOR.rand();

        $res = @file_put_contents($tmpFile, $data);
        if ($res === false) {
            $error = error_get_last();
            throw new \SimpleSAML_Error_Exception('Error saving file "'.$tmpFile.
                '": '.$error['message']);
        }

        if (self::getOS() !== self::WINDOWS) {
            if (!chmod($tmpFile, $mode)) {
                unlink($tmpFile);
                $error = error_get_last();
                throw new \SimpleSAML_Error_Exception('Error changing file mode of "'.$tmpFile.
                    '": '.$error['message']);
            }
        }

        if (!rename($tmpFile, $filename)) {
            unlink($tmpFile);
            $error = error_get_last();
            throw new \SimpleSAML_Error_Exception('Error moving "'.$tmpFile.'" to "'.
                $filename.'": '.$error['message']);
        }
    }
}
