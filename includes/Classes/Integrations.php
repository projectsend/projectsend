<?php
/**
 * Class that handles external storage integrations management.
 * Manages creation, updating, and deletion of external storage connections.
 */
namespace ProjectSend\Classes;

use \PDO;
use \ProjectSend\Classes\Validation;

class Integrations
{
    private $dbh;
    private $logger;

    // Integration types
    const TYPE_S3 = 's3';
    const TYPE_GCS = 'gcs';
    const TYPE_AZURE = 'azure';

    // Available integration types
    public static $available_types = [
        self::TYPE_S3 => [
            'name' => 'Amazon S3',
            'description' => 'Amazon Simple Storage Service and S3-compatible storage (MinIO, SeaweedFS, etc.)',
            'class' => 'S3Storage',
            'fields' => [
                'access_key' => ['type' => 'text', 'required' => true, 'label' => 'Access Key ID', 'help' => 'Your S3 access key or API key'],
                'secret_key' => ['type' => 'password', 'required' => true, 'label' => 'Secret Access Key', 'help' => 'Your S3 secret key'],
                'bucket_name' => ['type' => 'text', 'required' => true, 'label' => 'Bucket Name', 'help' => 'The name of the S3 bucket to use'],
                'region' => ['type' => 'select', 'required' => false, 'label' => 'Region', 'help' => 'AWS region (use "us-east-1" for most S3-compatible services)', 'options' => [
                    'us-east-1' => 'US East (N. Virginia)',
                    'us-east-2' => 'US East (Ohio)',
                    'us-west-1' => 'US West (N. California)',
                    'us-west-2' => 'US West (Oregon)',
                    'eu-west-1' => 'Europe (Ireland)',
                    'eu-west-2' => 'Europe (London)',
                    'eu-west-3' => 'Europe (Paris)',
                    'eu-central-1' => 'Europe (Frankfurt)',
                    'ap-southeast-1' => 'Asia Pacific (Singapore)',
                    'ap-southeast-2' => 'Asia Pacific (Sydney)',
                    'ap-northeast-1' => 'Asia Pacific (Tokyo)',
                    'custom' => 'Custom (specify endpoint below)',
                ]],
                'endpoint' => [
                    'type' => 'text',
                    'required' => false,
                    'label' => 'Custom Endpoint (Optional)',
                    'help' => 'For S3-compatible services like MinIO or SeaweedFS (e.g., https://minio.example.com or http://localhost:9000)',
                    'placeholder' => 'https://your-s3-server.com'
                ],
                'use_path_style' => ['type' => 'checkbox', 'required' => false, 'label' => 'Use Path-Style Addressing', 'help' => 'Enable for MinIO and some S3-compatible services. Uses bucket/key instead of bucket.endpoint format', 'default' => false]
            ]
        ],
        self::TYPE_GCS => [
            'name' => 'Google Cloud Storage',
            'description' => 'Google Cloud Storage (Coming Soon)',
            'class' => 'GCSStorage',
            'coming_soon' => true
        ],
        self::TYPE_AZURE => [
            'name' => 'Azure Blob Storage',
            'description' => 'Microsoft Azure Blob Storage (Coming Soon)',
            'class' => 'AzureStorage',
            'coming_soon' => true
        ]
    ];

    public function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;
    }

    /**
     * Get all integrations
     *
     * @param bool $active_only Whether to return only active integrations
     * @return array
     */
    public function getAll($active_only = false)
    {
        $query = "SELECT i.*, u.name as created_by_name, u.user as created_by_username
                  FROM " . TABLE_INTEGRATIONS . " i
                  LEFT JOIN " . TABLE_USERS . " u ON i.user_id = u.id";
        if ($active_only) {
            $query .= " WHERE i.active = 1";
        }
        $query .= " ORDER BY i.name ASC";

        $statement = $this->dbh->prepare($query);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get integration by ID
     *
     * @param int $id Integration ID
     * @return array|false Integration data or false if not found
     */
    public function getById($id)
    {
        $query = "SELECT * FROM " . TABLE_INTEGRATIONS . " WHERE id = :id";
        $statement = $this->dbh->prepare($query);
        $statement->bindParam(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get integration by name
     *
     * @param string $name Integration name
     * @return array|false Integration data or false if not found
     */
    public function getByName($name)
    {
        $query = "SELECT * FROM " . TABLE_INTEGRATIONS . " WHERE name = :name";
        $statement = $this->dbh->prepare($query);
        $statement->bindParam(':name', $name, PDO::PARAM_STR);
        $statement->execute();
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create new integration
     *
     * @param array $data Integration data
     * @return array Response with success/error status
     */
    public function create($data)
    {
        // Validate required fields
        $validation = new Validation;
        $validation->validate_items([
            $data['type'] => [
                'required' => ['error' => __('Integration type is required.', 'cftp_admin')],
            ],
            $data['name'] => [
                'required' => ['error' => __('Integration name is required.', 'cftp_admin')],
            ],
        ]);

        if (!$validation->passed()) {
            return [
                'status' => 'error',
                'message' => __('Please complete all required fields.', 'cftp_admin'),
                'errors' => $validation->list_errors(false)
            ];
        }

        // Check if type is supported
        if (!isset(self::$available_types[$data['type']])) {
            return [
                'status' => 'error',
                'message' => __('Invalid integration type.', 'cftp_admin')
            ];
        }

        // Check if name already exists
        if ($this->getByName($data['name'])) {
            return [
                'status' => 'error',
                'message' => __('Integration name already exists.', 'cftp_admin')
            ];
        }

        // Validate credentials based on type
        $credentials_validation = $this->validateCredentials($data['type'], $data['credentials'] ?? []);
        if (!$credentials_validation['valid']) {
            return [
                'status' => 'error',
                'message' => $credentials_validation['message'],
                'errors' => $credentials_validation['errors'] ?? []
            ];
        }

        try {
            // Encrypt credentials
            $encrypted_credentials = $this->encryptCredentials(json_encode($data['credentials']));

            $query = "INSERT INTO " . TABLE_INTEGRATIONS . "
                      (type, name, credentials_encrypted, active, user_id, created_date)
                      VALUES (:type, :name, :credentials, :active, :user_id, NOW())";

            $statement = $this->dbh->prepare($query);
            $statement->bindParam(':type', $data['type'], PDO::PARAM_STR);
            $statement->bindParam(':name', $data['name'], PDO::PARAM_STR);
            $statement->bindParam(':credentials', $encrypted_credentials, PDO::PARAM_STR);
            $statement->bindParam(':active', $data['active'], PDO::PARAM_INT);
            $user_id = CURRENT_USER_ID;
            $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);

            $statement->execute();
            $integration_id = $this->dbh->lastInsertId();

            // Test connection if requested
            $connection_test = null;
            if (isset($data['test_connection']) && $data['test_connection']) {
                $connection_test = $this->testIntegration($integration_id);
            }

            return [
                'status' => 'success',
                'message' => __('Integration created successfully.', 'cftp_admin'),
                'integration_id' => $integration_id,
                'connection_test' => $connection_test
            ];

        } catch (\PDOException $e) {
            error_log('Integration creation failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => __('Failed to create integration.', 'cftp_admin'),
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Update existing integration
     *
     * @param int $id Integration ID
     * @param array $data Updated data
     * @return array Response with success/error status
     */
    public function update($id, $data)
    {
        // Check if integration exists
        $existing = $this->getById($id);
        if (!$existing) {
            return [
                'status' => 'error',
                'message' => __('Integration not found.', 'cftp_admin')
            ];
        }

        // Validate name only if it's being updated
        if (isset($data['name'])) {
            $validation = new Validation;
            $validation->validate_items([
                $data['name'] => [
                    'required' => ['error' => __('Integration name is required.', 'cftp_admin')],
                ],
            ]);

            if (!$validation->passed()) {
                return [
                    'status' => 'error',
                    'message' => __('Please complete all required fields.', 'cftp_admin'),
                    'errors' => $validation->list_errors(false)
                ];
            }

            // Check if name already exists (excluding current integration)
            $name_check = $this->getByName($data['name']);
            if ($name_check && $name_check['id'] != $id) {
                return [
                    'status' => 'error',
                    'message' => __('Integration name already exists.', 'cftp_admin')
                ];
            }
        }

        try {
            $query_parts = [];
            $params = [':id' => $id];

            // Update name if provided
            if (isset($data['name'])) {
                $query_parts[] = "name = :name";
                $params[':name'] = $data['name'];
            }

            // Update credentials if provided
            if (isset($data['credentials'])) {
                $credentials_validation = $this->validateCredentials($existing['type'], $data['credentials']);
                if (!$credentials_validation['valid']) {
                    return [
                        'status' => 'error',
                        'message' => $credentials_validation['message'],
                        'errors' => $credentials_validation['errors'] ?? []
                    ];
                }

                $encrypted_credentials = $this->encryptCredentials(json_encode($data['credentials']));
                $query_parts[] = "credentials_encrypted = :credentials";
                $params[':credentials'] = $encrypted_credentials;
            }

            // Update active status if provided
            if (isset($data['active'])) {
                $query_parts[] = "active = :active";
                $params[':active'] = $data['active'];
            }

            if (empty($query_parts)) {
                return [
                    'status' => 'error',
                    'message' => __('No data to update.', 'cftp_admin')
                ];
            }

            $query_parts[] = "updated_date = NOW()";
            $query = "UPDATE " . TABLE_INTEGRATIONS . " SET " . implode(', ', $query_parts) . " WHERE id = :id";

            $statement = $this->dbh->prepare($query);
            $statement->execute($params);

            // Test connection if credentials were updated and test is requested
            $connection_test = null;
            if (isset($data['credentials']) && isset($data['test_connection']) && $data['test_connection']) {
                $connection_test = $this->testIntegration($id);
            }

            return [
                'status' => 'success',
                'message' => __('Integration updated successfully.', 'cftp_admin'),
                'connection_test' => $connection_test
            ];

        } catch (\PDOException $e) {
            error_log('Integration update failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => __('Failed to update integration.', 'cftp_admin'),
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete integration
     *
     * @param int $id Integration ID
     * @return array Response with success/error status
     */
    public function delete($id)
    {
        // Check if integration exists
        $integration = $this->getById($id);
        if (!$integration) {
            return [
                'status' => 'error',
                'message' => __('Integration not found.', 'cftp_admin')
            ];
        }

        // Check if integration is used by any files
        $query = "SELECT COUNT(*) FROM " . TABLE_FILES . " WHERE integration_id = :id";
        $statement = $this->dbh->prepare($query);
        $statement->bindParam(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $file_count = $statement->fetchColumn();

        if ($file_count > 0) {
            return [
                'status' => 'error',
                'message' => sprintf(__('Cannot delete integration. %d files are using this integration.', 'cftp_admin'), $file_count)
            ];
        }

        try {
            $query = "DELETE FROM " . TABLE_INTEGRATIONS . " WHERE id = :id";
            $statement = $this->dbh->prepare($query);
            $statement->bindParam(':id', $id, PDO::PARAM_INT);
            $statement->execute();

            return [
                'status' => 'success',
                'message' => __('Integration deleted successfully.', 'cftp_admin')
            ];

        } catch (\PDOException $e) {
            error_log('Integration deletion failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => __('Failed to delete integration.', 'cftp_admin'),
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Test integration connection
     *
     * @param int $id Integration ID
     * @return array Connection test result
     */
    public function testIntegration($id)
    {
        $integration = $this->getById($id);
        if (!$integration) {
            return [
                'success' => false,
                'message' => __('Integration not found.', 'cftp_admin')
            ];
        }

        $storage = $this->createStorageInstance($integration);
        if (!$storage) {
            return [
                'success' => false,
                'message' => __('Failed to create storage instance.', 'cftp_admin')
            ];
        }

        return $storage->testConnection();
    }

    /**
     * Create storage instance from integration data
     *
     * @param array $integration Integration data
     * @return ExternalStorage|null Storage instance or null on error
     */
    public function createStorageInstance($integration)
    {
        if (!isset(self::$available_types[$integration['type']])) {
            return null;
        }

        $type_config = self::$available_types[$integration['type']];
        $class_name = '\ProjectSend\Classes\\' . $type_config['class'];

        if (!class_exists($class_name)) {
            return null;
        }

        try {
            return new $class_name($integration['id']);
        } catch (\Exception $e) {
            error_log('Failed to create storage instance: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate credentials based on integration type
     *
     * @param string $type Integration type
     * @param array $credentials Credentials data
     * @return array Validation result
     */
    private function validateCredentials($type, $credentials)
    {
        if (!isset(self::$available_types[$type])) {
            return ['valid' => false, 'message' => __('Invalid integration type.', 'cftp_admin')];
        }

        $type_config = self::$available_types[$type];
        $errors = [];

        if (isset($type_config['fields'])) {
            foreach ($type_config['fields'] as $field => $config) {
                if ($config['required'] && empty($credentials[$field])) {
                    $errors[$field] = sprintf(__('%s is required.', 'cftp_admin'), $config['label']);
                }
            }
        }

        if (!empty($errors)) {
            return [
                'valid' => false,
                'message' => __('Please complete all required credential fields.', 'cftp_admin'),
                'errors' => $errors
            ];
        }

        return ['valid' => true];
    }

    /**
     * Encrypt credentials for database storage
     *
     * @param string $credentials JSON string of credentials
     * @return string Encrypted credentials
     */
    private function encryptCredentials($credentials)
    {
        $key = hash('sha256', 'projectsend-external-storage-key-' . ROOT_DIR);
        return base64_encode(openssl_encrypt($credentials, 'AES-256-CBC', $key, 0, substr($key, 0, 16)));
    }

    /**
     * Get available integration types
     *
     * @param bool $include_coming_soon Whether to include coming soon types
     * @return array
     */
    public static function getAvailableTypes($include_coming_soon = false)
    {
        if ($include_coming_soon) {
            return self::$available_types;
        }

        return array_filter(self::$available_types, function($type) {
            return !isset($type['coming_soon']) || !$type['coming_soon'];
        });
    }

    /**
     * Get integration type configuration
     *
     * @param string $type Integration type
     * @return array|null Type configuration or null if not found
     */
    public static function getTypeConfig($type)
    {
        return self::$available_types[$type] ?? null;
    }

    /**
     * Get integrations count by type
     *
     * @return array Count by type
     */
    public function getCountByType()
    {
        $query = "SELECT type, COUNT(*) as count FROM " . TABLE_INTEGRATIONS . " GROUP BY type";
        $statement = $this->dbh->prepare($query);
        $statement->execute();

        $counts = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $counts[$row['type']] = $row['count'];
        }

        return $counts;
    }

    /**
     * Get active integrations by type
     *
     * @param string $type Integration type
     * @return array
     */
    public function getByType($type)
    {
        $query = "SELECT * FROM " . TABLE_INTEGRATIONS . " WHERE type = :type AND active = 1 ORDER BY name ASC";
        $statement = $this->dbh->prepare($query);
        $statement->bindParam(':type', $type, PDO::PARAM_STR);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}