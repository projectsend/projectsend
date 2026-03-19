<?php
/**
 * Auto Update Class
 * Handles system updates for ProjectSend
 */

namespace ProjectSend\Classes;

class AutoUpdate
{
    private $temp_dir;
    private $backup_dir;
    private $update_file;
    private $errors = [];
    private $requires_php;

    public function __construct()
    {
        $this->temp_dir = ROOT_DIR . DS . 'upload' . DS . 'temp';
        $this->backup_dir = $this->temp_dir . DS . 'backup_' . time();
        $this->update_file = $this->temp_dir . DS . 'projectsend_update.zip';
    }

    /**
     * Set the minimum PHP version required by the target release
     * @param string $version PHP version string (e.g. "8.2")
     */
    public function setRequiredPhpVersion($version)
    {
        $this->requires_php = $version;
    }

    /**
     * Check system requirements for update
     * @return array Status and results
     */
    public function checkSystemRequirements()
    {
        $requirements = [];
        $all_pass = true;

        // Check PHP version meets the requirement for the new version
        $required_php = REQUIRED_VERSION_PHP;

        // If the update feed provides a requires_php field, use that instead
        // This allows the server to specify the minimum PHP version for the target release
        if (!empty($this->requires_php)) {
            $required_php = $this->requires_php;
        }

        $php_ok = version_compare(phpversion(), $required_php, '>=');
        $requirements[] = [
            'name' => __('PHP version', 'cftp_admin'),
            'status' => $php_ok,
            'message' => $php_ok
                ? sprintf(__('Current PHP version (%s) meets the minimum requirement (%s)', 'cftp_admin'), phpversion(), $required_php)
                : sprintf(__('PHP %s or higher is required. Your server is running PHP %s. Please upgrade PHP before updating.', 'cftp_admin'), $required_php, phpversion())
        ];
        if (!$php_ok) $all_pass = false;

        // Check if root directory is writable
        $root_writable = is_writable(ROOT_DIR);
        $requirements[] = [
            'name' => __('Root directory writable', 'cftp_admin'),
            'status' => $root_writable,
            'message' => $root_writable
                ? sprintf(__('System can write to root directory (%s)', 'cftp_admin'), ROOT_DIR)
                : sprintf(__('Root directory is not writable (%s). Try: sudo chown -R www-data:www-data %s', 'cftp_admin'), ROOT_DIR, ROOT_DIR)
        ];
        if (!$root_writable) $all_pass = false;

        // Check if temp directory is writable
        $requirements[] = [
            'name' => __('Temp directory writable', 'cftp_admin'),
            'status' => is_writable($this->temp_dir),
            'message' => is_writable($this->temp_dir)
                ? sprintf(__('System can write to temp directory (%s)', 'cftp_admin'), $this->temp_dir)
                : sprintf(__('Temp directory is not writable (%s)', 'cftp_admin'), $this->temp_dir)
        ];
        if (!is_writable($this->temp_dir)) $all_pass = false;

        // Check available disk space (need at least 100MB)
        $free_space = disk_free_space(ROOT_DIR);
        $required_space = 100 * 1024 * 1024; // 100MB in bytes
        $has_space = $free_space > $required_space;

        $requirements[] = [
            'name' => __('Disk space available', 'cftp_admin'),
            'status' => $has_space,
            'message' => $has_space
                ? sprintf(__('Available: %s MB', 'cftp_admin'), round($free_space / 1024 / 1024))
                : sprintf(__('Insufficient disk space. Need at least %s MB', 'cftp_admin'), round($required_space / 1024 / 1024))
        ];
        if (!$has_space) $all_pass = false;

        // Check PHP functions
        $required_functions = ['file_get_contents', 'file_put_contents', 'mkdir', 'rmdir', 'unlink', 'copy'];
        $disabled_functions = [];
        foreach ($required_functions as $func) {
            if (!function_exists($func)) {
                $disabled_functions[] = $func;
            }
        }

        $requirements[] = [
            'name' => __('Required PHP functions', 'cftp_admin'),
            'status' => empty($disabled_functions),
            'message' => empty($disabled_functions)
                ? sprintf(__('All required functions available: %s', 'cftp_admin'), implode(', ', $required_functions))
                : sprintf(__('Missing functions: %s (Required: %s)', 'cftp_admin'), implode(', ', $disabled_functions), implode(', ', $required_functions))
        ];
        if (!empty($disabled_functions)) $all_pass = false;

        // Check ZIP extension
        $has_zip = class_exists('ZipArchive');
        $requirements[] = [
            'name' => __('ZIP extension', 'cftp_admin'),
            'status' => $has_zip,
            'message' => $has_zip
                ? __('ZIP extension is installed', 'cftp_admin')
                : __('ZIP extension is required but not installed', 'cftp_admin')
        ];
        if (!$has_zip) $all_pass = false;

        // Check database connection
        global $dbh;
        $db_connected = false;
        try {
            $dbh->query("SELECT 1");
            $db_connected = true;
        } catch (\Exception $e) {
            $db_connected = false;
        }

        $requirements[] = [
            'name' => __('Database connection', 'cftp_admin'),
            'status' => $db_connected,
            'message' => $db_connected
                ? __('Database connection is active', 'cftp_admin')
                : __('Cannot connect to database', 'cftp_admin')
        ];
        if (!$db_connected) $all_pass = false;

        // Check network connectivity
        $can_connect = false;
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'HEAD'
            ]
        ]);
        $headers = @get_headers(UPDATES_FEED_URI, false, $context);
        if ($headers && strpos($headers[0], '200')) {
            $can_connect = true;
        }

        $requirements[] = [
            'name' => __('Network connectivity', 'cftp_admin'),
            'status' => $can_connect,
            'message' => $can_connect
                ? __('Can reach update server', 'cftp_admin')
                : __('Cannot connect to update server', 'cftp_admin')
        ];
        if (!$can_connect) $all_pass = false;

        // Add additional warnings if root directory is not writable
        $warnings = [];
        if (!$root_writable) {
            $warnings[] = __('Some files may fail to update due to permission restrictions. The update will proceed but may require manual file permission fixes.', 'cftp_admin');
        }

        return [
            'status' => $all_pass ? 'success' : 'error',
            'requirements' => $requirements,
            'warnings' => $warnings,
            'can_update' => $all_pass
        ];
    }

    /**
     * Download update package
     * @param string $url Download URL
     * @param string $expected_hash Optional SHA256 hash to verify
     * @return array Status and message
     */
    public function downloadUpdate($url, $expected_hash = null)
    {
        try {
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \Exception(__('Invalid download URL', 'cftp_admin'));
            }

            // Create temp directory if it doesn't exist
            if (!is_dir($this->temp_dir)) {
                if (!mkdir($this->temp_dir, 0755, true)) {
                    throw new \Exception(__('Cannot create temp directory', 'cftp_admin'));
                }
            }

            // Clean up any existing update file
            if (file_exists($this->update_file)) {
                @unlink($this->update_file);
            }

            // Download file with context for timeout and user agent
            $context = stream_context_create([
                'http' => [
                    'timeout' => 300, // 5 minutes timeout
                    'user_agent' => 'ProjectSend/' . CURRENT_VERSION
                ]
            ]);

            $content = @file_get_contents($url, false, $context);
            if ($content === false) {
                throw new \Exception(__('Failed to download update file', 'cftp_admin'));
            }

            // Save to temp file
            if (!file_put_contents($this->update_file, $content)) {
                throw new \Exception(__('Failed to save update file', 'cftp_admin'));
            }

            // Verify SHA256 hash if provided
            if (!empty($expected_hash)) {
                $actual_hash = hash_file('sha256', $this->update_file);
                if (strtolower($actual_hash) !== strtolower($expected_hash)) {
                    @unlink($this->update_file);
                    throw new \Exception(sprintf(
                        __('Hash verification failed. Expected: %s, Got: %s', 'cftp_admin'),
                        $expected_hash,
                        $actual_hash
                    ));
                }
            }

            // Verify it's a valid ZIP file
            $zip = new \ZipArchive();
            if ($zip->open($this->update_file) !== true) {
                @unlink($this->update_file);
                throw new \Exception(__('Downloaded file is not a valid ZIP archive', 'cftp_admin'));
            }
            $zip->close();

            return [
                'status' => 'success',
                'message' => __('Update downloaded successfully', 'cftp_admin'),
                'file' => $this->update_file,
                'size' => filesize($this->update_file)
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Create backup of current installation
     * @return array Status and message
     */
    public function createBackup()
    {
        try {
            // Create backup directory
            if (!mkdir($this->backup_dir, 0755, true)) {
                throw new \Exception(__('Cannot create backup directory', 'cftp_admin'));
            }

            // List of directories and files to backup (exclude upload folder and temp files)
            $exclude_dirs = ['upload', 'vendor', '.git', 'node_modules'];
            $exclude_files = ['.DS_Store', 'Thumbs.db', '.gitignore'];

            // Get all files and directories
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(ROOT_DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            $backed_up = 0;
            foreach ($iterator as $item) {
                $path = $item->getPathname();
                $relative_path = str_replace(ROOT_DIR . DS, '', $path);

                // Check if should be excluded
                $should_exclude = false;

                // Check excluded directories
                foreach ($exclude_dirs as $exclude) {
                    if (strpos($relative_path, $exclude . DS) === 0 || $relative_path === $exclude) {
                        $should_exclude = true;
                        break;
                    }
                }

                // Check excluded files
                if (!$should_exclude && $item->isFile()) {
                    $filename = $item->getFilename();
                    if (in_array($filename, $exclude_files)) {
                        $should_exclude = true;
                    }
                }

                if ($should_exclude) {
                    continue;
                }

                // Create backup path
                $backup_path = $this->backup_dir . DS . $relative_path;

                if ($item->isDir()) {
                    // Create directory in backup
                    if (!is_dir($backup_path)) {
                        mkdir($backup_path, 0755, true);
                    }
                } else {
                    // Copy file to backup
                    $backup_dirname = dirname($backup_path);
                    if (!is_dir($backup_dirname)) {
                        mkdir($backup_dirname, 0755, true);
                    }

                    if (copy($path, $backup_path)) {
                        $backed_up++;
                    }
                }
            }

            return [
                'status' => 'success',
                'message' => sprintf(__('Backup created successfully (%d files)', 'cftp_admin'), $backed_up),
                'backup_dir' => $this->backup_dir,
                'files_backed_up' => $backed_up
            ];

        } catch (\Exception $e) {
            // Clean up partial backup
            if (is_dir($this->backup_dir)) {
                $this->deleteDirectory($this->backup_dir);
            }

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract and install update
     * @return array Status and message
     */
    public function extractUpdate()
    {
        try {
            // Check if update file exists, if not try to find any update file
            if (!file_exists($this->update_file)) {
                $update_file = $this->findLatestUpdateFile();
                if (!$update_file) {
                    throw new \Exception(__('Update file not found', 'cftp_admin'));
                }
                $this->update_file = $update_file;
            }

            // Open ZIP archive
            $zip = new \ZipArchive();
            if ($zip->open($this->update_file) !== true) {
                throw new \Exception(__('Cannot open update archive', 'cftp_admin'));
            }

            // Extract files individually to handle permission issues
            $extracted = 0;
            $failed = 0;
            $failed_files = [];
            $num_files = $zip->numFiles;

            for ($i = 0; $i < $num_files; $i++) {
                $filename = $zip->getNameIndex($i);
                $fileinfo = $zip->statIndex($i);

                // Skip directories
                if (substr($filename, -1) === '/') {
                    continue;
                }

                // Get file content
                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    $failed++;
                    $failed_files[] = $filename . ' (could not read from archive)';
                    continue;
                }

                // Determine target path
                $target_path = ROOT_DIR . DS . $filename;
                $target_dir = dirname($target_path);

                // Create directory if it doesn't exist
                if (!is_dir($target_dir)) {
                    if (!@mkdir($target_dir, 0755, true)) {
                        $failed++;
                        $failed_files[] = $filename . ' (could not create directory)';
                        continue;
                    }
                }

                // Try to write the file
                if (@file_put_contents($target_path, $content) !== false) {
                    $extracted++;
                    // Try to set file permissions to match existing files
                    @chmod($target_path, 0644);
                } else {
                    $failed++;
                    $failed_files[] = $filename . ' (permission denied)';
                }
            }

            $zip->close();

            // Determine if extraction was successful enough
            $success_rate = $extracted / max(1, $extracted + $failed);

            if ($extracted === 0) {
                throw new \Exception(
                    sprintf(__('No files could be extracted. Failed files: %s', 'cftp_admin'),
                    implode(', ', array_slice($failed_files, 0, 5)) . ($failed > 5 ? '...' : ''))
                );
            } elseif ($success_rate < 0.8) {
                // Less than 80% success rate is considered a failure
                throw new \Exception(
                    sprintf(__('Too many files failed to extract (%d extracted, %d failed). Some failed files: %s', 'cftp_admin'),
                    $extracted, $failed, implode(', ', array_slice($failed_files, 0, 3)))
                );
            }

            // Success or partial success
            $message = sprintf(__('Update extracted successfully (%d files)', 'cftp_admin'), $extracted);
            if ($failed > 0) {
                $message .= sprintf(__(' - %d files skipped due to permissions', 'cftp_admin'), $failed);
            }

            return [
                'status' => 'success',
                'message' => $message,
                'files_extracted' => $extracted,
                'files_failed' => $failed,
                'failed_files' => $failed_files
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Finalize update - run database upgrades and cleanup
     * @return array Status and message
     */
    public function finalize()
    {
        try {
            // Run database upgrades
            $db_upgrade = new \ProjectSend\Classes\DatabaseUpgrade();
            $db_upgrade->upgradeDatabase(false);

            // Clean up temporary files
            if (file_exists($this->update_file)) {
                @unlink($this->update_file);
            }

            // Clean up any old update files
            $old_update_files = glob($this->temp_dir . DS . 'update_*.zip');
            foreach ($old_update_files as $file) {
                @unlink($file);
            }

            // Remove backup directory (successful update)
            if (is_dir($this->backup_dir)) {
                $this->deleteDirectory($this->backup_dir);
            }

            // Clear any caches
            $cache_dir = ROOT_DIR . DS . 'cache';
            if (is_dir($cache_dir)) {
                $this->clearDirectory($cache_dir);
            }

            return [
                'status' => 'success',
                'message' => __('Update completed successfully', 'cftp_admin')
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Rollback update from backup
     * @return array Status and message
     */
    public function rollback()
    {
        try {
            // Check if backup exists
            if (!is_dir($this->backup_dir)) {
                throw new \Exception(__('Backup directory not found', 'cftp_admin'));
            }

            // Restore files from backup
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->backup_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            $restored = 0;
            foreach ($iterator as $item) {
                $backup_path = $item->getPathname();
                $relative_path = str_replace($this->backup_dir . DS, '', $backup_path);
                $restore_path = ROOT_DIR . DS . $relative_path;

                if ($item->isDir()) {
                    if (!is_dir($restore_path)) {
                        mkdir($restore_path, 0755, true);
                    }
                } else {
                    $restore_dirname = dirname($restore_path);
                    if (!is_dir($restore_dirname)) {
                        mkdir($restore_dirname, 0755, true);
                    }

                    if (copy($backup_path, $restore_path)) {
                        $restored++;
                    }
                }
            }

            // Clean up
            $this->deleteDirectory($this->backup_dir);
            if (file_exists($this->update_file)) {
                @unlink($this->update_file);
            }

            // Clean up any old update files
            $old_update_files = glob($this->temp_dir . DS . 'update_*.zip');
            foreach ($old_update_files as $file) {
                @unlink($file);
            }

            return [
                'status' => 'success',
                'message' => sprintf(__('Rollback completed (%d files restored)', 'cftp_admin'), $restored),
                'files_restored' => $restored
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Find the latest update file in temp directory
     * @return string|false Path to latest update file or false if none found
     */
    private function findLatestUpdateFile()
    {
        $pattern = $this->temp_dir . DS . 'update_*.zip';
        $files = glob($pattern);

        if (empty($files)) {
            return false;
        }

        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $files[0];
    }

    /**
     * Delete directory recursively
     * @param string $dir Directory path
     */
    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DS . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Clear directory contents (keep directory)
     * @param string $dir Directory path
     */
    private function clearDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DS . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
    }
}