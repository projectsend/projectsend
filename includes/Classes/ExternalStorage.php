<?php
/**
 * Abstract class for external storage integrations.
 * Provides the base structure for implementing different external storage providers
 * like AWS S3, Google Cloud Storage, Azure Blob Storage, etc.
 */
namespace ProjectSend\Classes;

abstract class ExternalStorage
{
    protected $integration_id;
    protected $integration_name;
    protected $credentials;
    protected $config;
    protected $is_connected = false;

    /**
     * Constructor
     *
     * @param int $integration_id The integration ID from tbl_integrations
     */
    public function __construct($integration_id = null)
    {
        $this->integration_id = $integration_id;
        if ($integration_id) {
            $this->loadIntegration();
        }
    }

    /**
     * Load integration configuration from database
     *
     * @return bool
     */
    protected function loadIntegration()
    {
        global $dbh;

        try {
            $query = "SELECT * FROM " . TABLE_INTEGRATIONS . " WHERE id = :id AND active = 1";
            $statement = $dbh->prepare($query);
            $statement->bindParam(':id', $this->integration_id, \PDO::PARAM_INT);
            $statement->execute();
            $integration = $statement->fetch(\PDO::FETCH_ASSOC);

            if ($integration) {
                $this->integration_name = $integration['name'];
                $this->credentials = json_decode($this->decryptCredentials($integration['credentials_encrypted']), true);
                return true;
            }
        } catch (\PDOException $e) {
            error_log('External Storage: Failed to load integration: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Test the connection to the external storage provider
     *
     * @return array Status response with success/error
     */
    abstract public function testConnection();

    /**
     * Upload a file to external storage
     *
     * @param string $local_file_path Local file path
     * @param string $remote_file_path Remote file path/key
     * @param array $metadata Additional metadata
     * @return array Status response with success/error and file information
     */
    abstract public function uploadFile($local_file_path, $remote_file_path, $metadata = []);

    /**
     * Download a file from external storage
     *
     * @param string $remote_file_path Remote file path/key
     * @param string $local_file_path Local destination path
     * @return array Status response with success/error
     */
    abstract public function downloadFile($remote_file_path, $local_file_path);

    /**
     * Delete a file from external storage
     *
     * @param string $remote_file_path Remote file path/key
     * @return array Status response with success/error
     */
    abstract public function deleteFile($remote_file_path);

    /**
     * List files in external storage
     *
     * @param string $prefix Optional prefix to filter files
     * @param int $max_keys Maximum number of files to return
     * @return array List of files with metadata
     */
    abstract public function listFiles($prefix = '', $max_keys = 1000);

    /**
     * Get a pre-signed URL for direct file access
     *
     * @param string $remote_file_path Remote file path/key
     * @param int $expires_in Expiration time in seconds
     * @return string|false Pre-signed URL or false on error
     */
    abstract public function getPresignedUrl($remote_file_path, $expires_in = 3600);

    /**
     * Get file metadata from external storage
     *
     * @param string $remote_file_path Remote file path/key
     * @return array|false File metadata or false if not found
     */
    abstract public function getFileMetadata($remote_file_path);

    /**
     * Check if a file exists in external storage
     *
     * @param string $remote_file_path Remote file path/key
     * @return bool
     */
    abstract public function fileExists($remote_file_path);

    /**
     * Get the storage type identifier
     *
     * @return string Storage type (s3, gcs, azure, etc.)
     */
    abstract public function getStorageType();

    /**
     * Encrypt credentials for database storage
     *
     * @param string $credentials JSON string of credentials
     * @return string Encrypted credentials
     */
    protected function encryptCredentials($credentials)
    {
        // Use a simple encryption for now - in production, use proper encryption
        $key = hash('sha256', 'projectsend-external-storage-key-' . ROOT_DIR);
        return base64_encode(openssl_encrypt($credentials, 'AES-256-CBC', $key, 0, substr($key, 0, 16)));
    }

    /**
     * Decrypt credentials from database storage
     *
     * @param string $encrypted_credentials Encrypted credentials string
     * @return string Decrypted JSON credentials
     */
    protected function decryptCredentials($encrypted_credentials)
    {
        $key = hash('sha256', 'projectsend-external-storage-key-' . ROOT_DIR);
        return openssl_decrypt(base64_decode($encrypted_credentials), 'AES-256-CBC', $key, 0, substr($key, 0, 16));
    }

    /**
     * Get human-readable error message
     *
     * @param string $error_code Error code
     * @return string Human-readable error message
     */
    protected function getErrorMessage($error_code)
    {
        $messages = [
            'connection_failed' => __('Failed to connect to external storage', 'cftp_admin'),
            'upload_failed' => __('Failed to upload file to external storage', 'cftp_admin'),
            'download_failed' => __('Failed to download file from external storage', 'cftp_admin'),
            'delete_failed' => __('Failed to delete file from external storage', 'cftp_admin'),
            'file_not_found' => __('File not found in external storage', 'cftp_admin'),
            'invalid_credentials' => __('Invalid storage credentials', 'cftp_admin'),
            'permission_denied' => __('Permission denied for storage operation', 'cftp_admin'),
        ];

        return isset($messages[$error_code]) ? $messages[$error_code] : __('Unknown external storage error', 'cftp_admin');
    }

    /**
     * Log storage operations
     *
     * @param string $operation Operation type (upload, download, delete, etc.)
     * @param string $file_path File path
     * @param string $status Status (success, error)
     * @param string $details Additional details
     */
    protected function logOperation($operation, $file_path, $status, $details = '')
    {
        error_log(sprintf(
            'External Storage [%s]: %s - %s (%s) - %s',
            $this->getStorageType(),
            $operation,
            $file_path,
            $status,
            $details
        ));
    }

    /**
     * Validate file path/key for external storage
     *
     * @param string $file_path File path to validate
     * @return bool
     */
    protected function isValidFilePath($file_path)
    {
        // Basic validation - no empty paths, no directory traversal
        return !empty($file_path) && strpos($file_path, '..') === false && strpos($file_path, '//') === false;
    }

    /**
     * Generate a safe file key for external storage
     *
     * @param string $filename Original filename
     * @param string $prefix Optional prefix
     * @return string Safe file key
     */
    protected function generateFileKey($filename, $prefix = '')
    {
        $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $timestamp = time();
        $random = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);

        if ($prefix) {
            return $prefix . '/' . $timestamp . '_' . $random . '_' . $safe_filename;
        }

        return $timestamp . '_' . $random . '_' . $safe_filename;
    }

    /**
     * Get the connection status
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->is_connected;
    }

    /**
     * Get the integration ID
     *
     * @return int|null
     */
    public function getIntegrationId()
    {
        return $this->integration_id;
    }

    /**
     * Get the integration name
     *
     * @return string|null
     */
    public function getIntegrationName()
    {
        return $this->integration_name;
    }
}