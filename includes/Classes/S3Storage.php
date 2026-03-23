<?php
/**
 * AWS S3 Storage implementation.
 * Handles file operations with Amazon S3 storage.
 */
namespace ProjectSend\Classes;

use \ProjectSend\Classes\ExternalStorage;

class S3Storage extends ExternalStorage
{
    private $s3_client;
    private $bucket_name;
    private $region;
    private $access_key;
    private $secret_key;
    private ?string $endpoint;
    private bool $use_path_style;

    /**
     * Constructor
     *
     * @param int $integration_id The integration ID from tbl_integrations
     */
    public function __construct($integration_id = null)
    {
        parent::__construct($integration_id);

        if ($this->credentials) {
            $this->access_key = $this->credentials['access_key'] ?? '';
            $this->secret_key = $this->credentials['secret_key'] ?? '';
            $this->bucket_name = $this->credentials['bucket_name'] ?? '';
            $this->region = $this->credentials['region'] ?? 'us-east-1';
            $this->endpoint = $this->credentials['endpoint'] ?? '';
            $this->use_path_style = isset($this->credentials['use_path_style']) && $this->credentials['use_path_style'] === '1';

            $this->initializeS3Client();
        }
    }

    /**
     * Initialize S3 client
     */
    private function initializeS3Client()
    {
        try {
            // Check if AWS SDK is available
            if (!class_exists('\Aws\S3\S3Client')) {
                $this->logOperation('init', '', 'error', 'AWS SDK not installed');
                return;
            }

            $config = [
                'version' => 'latest',
                'region' => $this->region ?: 'us-east-1',
                'credentials' => [
                    'key' => $this->access_key,
                    'secret' => $this->secret_key,
                ],
            ];

            // Add custom endpoint for S3-compatible services (MinIO, SeaweedFS, etc.)
            if (!empty($this->endpoint)) {
                $config['endpoint'] = $this->endpoint;
                $this->logOperation('init', '', 'info', 'Using custom endpoint: ' . $this->endpoint);
            }

            // Enable path-style addressing for MinIO and other S3-compatible services
            if ($this->use_path_style) {
                $config['use_path_style_endpoint'] = true;
                $this->logOperation('init', '', 'info', 'Using path-style addressing');
            }

            $this->s3_client = new \Aws\S3\S3Client($config);

            $this->is_connected = true;
            $this->logOperation('init', '', 'success', 'S3 client initialized');
        } catch (\Exception $e) {
            $this->logOperation('init', '', 'error', $e->getMessage());
        }
    }

    /**
     * Test the connection to S3
     *
     * @return array Status response with success/error
     */
    public function testConnection()
    {
        if (!$this->s3_client) {
            return [
                'success' => false,
                'message' => $this->getErrorMessage('connection_failed'),
                'details' => 'AWS SDK not available or client not initialized'
            ];
        }

        try {
            // Test connection by checking if the configured bucket exists and is accessible
            // This is better than ListBuckets as it only requires bucket-level permissions
            $result = $this->s3_client->headBucket(['Bucket' => $this->bucket_name]);

            $this->logOperation('test', '', 'success', 'Connection test passed - bucket accessible');
            return [
                'success' => true,
                'message' => sprintf(__('Connection to S3 successful. Bucket "%s" is accessible.', 'cftp_admin'), $this->bucket_name)
            ];
        } catch (\Aws\S3\Exception\S3Exception $e) {
            $error_code = $e->getAwsErrorCode();
            $error_message = $e->getMessage();

            // Provide more specific error messages based on error code
            $user_message = '';
            switch ($error_code) {
                case 'NoSuchBucket':
                    $user_message = sprintf(__('Bucket "%s" does not exist.', 'cftp_admin'), $this->bucket_name);
                    break;
                case 'AccessDenied':
                    $user_message = __('Access denied. Check your AWS credentials and bucket permissions.', 'cftp_admin');
                    break;
                case 'InvalidAccessKeyId':
                    $user_message = __('Invalid AWS Access Key ID.', 'cftp_admin');
                    break;
                case 'SignatureDoesNotMatch':
                    $user_message = __('Invalid AWS Secret Access Key.', 'cftp_admin');
                    break;
                case 'TokenRefreshRequired':
                    $user_message = __('AWS credentials need to be refreshed.', 'cftp_admin');
                    break;
                default:
                    $user_message = $this->getErrorMessage('connection_failed');
                    break;
            }

            $this->logOperation('test', '', 'error', $error_message);
            return [
                'success' => false,
                'message' => $user_message,
                'details' => $error_code . ': ' . $error_message
            ];
        } catch (\Exception $e) {
            $this->logOperation('test', '', 'error', $e->getMessage());
            return [
                'success' => false,
                'message' => $this->getErrorMessage('connection_failed'),
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload a file to S3
     *
     * @param string $local_file_path Local file path
     * @param string $remote_file_path Remote file path/key
     * @param array $metadata Additional metadata
     * @return array Status response with success/error and file information
     */
    public function uploadFile($local_file_path, $remote_file_path, $metadata = [])
    {
        if (!$this->s3_client) {
            return [
                'success' => false,
                'message' => $this->getErrorMessage('connection_failed')
            ];
        }

        if (!file_exists($local_file_path)) {
            return [
                'success' => false,
                'message' => __('Local file not found', 'cftp_admin')
            ];
        }

        if (!$this->isValidFilePath($remote_file_path)) {
            return [
                'success' => false,
                'message' => __('Invalid remote file path', 'cftp_admin')
            ];
        }

        try {
            $upload_params = [
                'Bucket' => $this->bucket_name,
                'Key' => $remote_file_path,
                'SourceFile' => $local_file_path,
                'ContentType' => mime_content_type($local_file_path) ?: 'application/octet-stream',
            ];

            // Add metadata if provided
            if (!empty($metadata)) {
                $upload_params['Metadata'] = $metadata;
            }

            $result = $this->s3_client->putObject($upload_params);

            $this->logOperation('upload', $remote_file_path, 'success', 'File uploaded to S3');

            return [
                'success' => true,
                'message' => __('File uploaded successfully', 'cftp_admin'),
                'file_url' => $result['ObjectURL'] ?? '',
                'etag' => $result['ETag'] ?? '',
                'version_id' => $result['VersionId'] ?? null
            ];
        } catch (\Exception $e) {
            $this->logOperation('upload', $remote_file_path, 'error', $e->getMessage());
            return [
                'success' => false,
                'message' => $this->getErrorMessage('upload_failed'),
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Download a file from S3
     *
     * @param string $remote_file_path Remote file path/key
     * @param string $local_file_path Local destination path
     * @return array Status response with success/error
     */
    public function downloadFile($remote_file_path, $local_file_path)
    {
        if (!$this->s3_client) {
            return [
                'success' => false,
                'message' => $this->getErrorMessage('connection_failed')
            ];
        }

        try {
            $result = $this->s3_client->getObject([
                'Bucket' => $this->bucket_name,
                'Key' => $remote_file_path,
                'SaveAs' => $local_file_path
            ]);

            $this->logOperation('download', $remote_file_path, 'success', 'File downloaded from S3');

            return [
                'success' => true,
                'message' => __('File downloaded successfully', 'cftp_admin'),
                'local_path' => $local_file_path,
                'size' => $result['ContentLength'] ?? 0
            ];
        } catch (\Exception $e) {
            $this->logOperation('download', $remote_file_path, 'error', $e->getMessage());
            return [
                'success' => false,
                'message' => $this->getErrorMessage('download_failed'),
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete a file from S3
     *
     * @param string $remote_file_path Remote file path/key
     * @return array Status response with success/error
     */
    public function deleteFile($remote_file_path)
    {
        if (!$this->s3_client) {
            return [
                'success' => false,
                'message' => $this->getErrorMessage('connection_failed')
            ];
        }

        try {
            $this->s3_client->deleteObject([
                'Bucket' => $this->bucket_name,
                'Key' => $remote_file_path
            ]);

            $this->logOperation('delete', $remote_file_path, 'success', 'File deleted from S3');

            return [
                'success' => true,
                'message' => __('File deleted successfully', 'cftp_admin')
            ];
        } catch (\Exception $e) {
            $this->logOperation('delete', $remote_file_path, 'error', $e->getMessage());
            return [
                'success' => false,
                'message' => $this->getErrorMessage('delete_failed'),
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * List files in S3 bucket
     *
     * @param string $prefix Optional prefix to filter files
     * @param int $max_keys Maximum number of files to return
     * @return array List of files with metadata
     */
    public function listFiles($prefix = '', $max_keys = 1000)
    {
        if (!$this->s3_client) {
            return [
                'success' => false,
                'message' => $this->getErrorMessage('connection_failed'),
                'files' => []
            ];
        }

        try {
            $params = [
                'Bucket' => $this->bucket_name,
                'MaxKeys' => $max_keys
            ];

            if (!empty($prefix)) {
                $params['Prefix'] = $prefix;
            }

            $result = $this->s3_client->listObjectsV2($params);
            $files = [];

            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $files[] = [
                        'key' => $object['Key'],
                        'size' => $object['Size'],
                        'last_modified' => $object['LastModified']->format('Y-m-d H:i:s'),
                        'etag' => trim($object['ETag'], '"'),
                        'storage_class' => $object['StorageClass'] ?? 'STANDARD'
                    ];
                }
            }

            $this->logOperation('list', $prefix, 'success', count($files) . ' files found');

            return [
                'success' => true,
                'files' => $files,
                'truncated' => $result['IsTruncated'] ?? false,
                'next_token' => $result['NextContinuationToken'] ?? null
            ];
        } catch (\Exception $e) {
            $this->logOperation('list', $prefix, 'error', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'files' => []
            ];
        }
    }

    /**
     * Get a pre-signed URL for direct file access
     *
     * @param string $remote_file_path Remote file path/key
     * @param int $expires_in Expiration time in seconds
     * @return string|false Pre-signed URL or false on error
     */
    public function getPresignedUrl($remote_file_path, $expires_in = 3600)
    {
        if (!$this->s3_client) {
            return false;
        }

        try {
            $command = $this->s3_client->getCommand('GetObject', [
                'Bucket' => $this->bucket_name,
                'Key' => $remote_file_path
            ]);

            $request = $this->s3_client->createPresignedRequest($command, "+{$expires_in} seconds");
            $url = (string) $request->getUri();

            $this->logOperation('presign', $remote_file_path, 'success', 'Presigned URL generated');

            return $url;
        } catch (\Exception $e) {
            $this->logOperation('presign', $remote_file_path, 'error', $e->getMessage());
            return false;
        }
    }

    /**
     * Get file metadata from S3
     *
     * @param string $remote_file_path Remote file path/key
     * @return array|false File metadata or false if not found
     */
    public function getFileMetadata($remote_file_path)
    {
        if (!$this->s3_client) {
            return false;
        }

        try {
            $result = $this->s3_client->headObject([
                'Bucket' => $this->bucket_name,
                'Key' => $remote_file_path
            ]);

            return [
                'size' => $result['ContentLength'],
                'last_modified' => $result['LastModified']->format('Y-m-d H:i:s'),
                'content_type' => $result['ContentType'],
                'etag' => trim($result['ETag'], '"'),
                'metadata' => $result['Metadata'] ?? []
            ];
        } catch (\Exception $e) {
            $this->logOperation('metadata', $remote_file_path, 'error', $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a file exists in S3
     *
     * @param string $remote_file_path Remote file path/key
     * @return bool
     */
    public function fileExists($remote_file_path)
    {
        if (!$this->s3_client) {
            return false;
        }

        try {
            $this->s3_client->headObject([
                'Bucket' => $this->bucket_name,
                'Key' => $remote_file_path
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the storage type identifier
     *
     * @return string Storage type
     */
    public function getStorageType()
    {
        return 's3';
    }

    /**
     * Get bucket name
     *
     * @return string
     */
    public function getBucketName()
    {
        return $this->bucket_name;
    }

    /**
     * Get AWS region
     *
     * @return string
     */
    public function getRegion()
    {
        return $this->region;
    }

    /**
     * Set bucket for operations
     *
     * @param string $bucket_name
     */
    public function setBucketName($bucket_name)
    {
        $this->bucket_name = $bucket_name;
    }

    /**
     * Create S3 storage instance from credentials
     *
     * @param array $credentials S3 credentials
     * @return S3Storage
     */
    public static function fromCredentials($credentials)
    {
        $instance = new self();
        $instance->credentials = $credentials;
        $instance->access_key = $credentials['access_key'] ?? '';
        $instance->secret_key = $credentials['secret_key'] ?? '';
        $instance->bucket_name = $credentials['bucket_name'] ?? '';
        $instance->region = $credentials['region'] ?? 'us-east-1';
        $instance->endpoint = $credentials['endpoint'] ?? '';
        $instance->use_path_style = isset($credentials['use_path_style']) && $credentials['use_path_style'] == '1';
        $instance->initializeS3Client();
        return $instance;
    }
}