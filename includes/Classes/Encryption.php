<?php
/**
 * Class that handles file encryption and decryption operations.
 *
 * Uses AES-256-GCM for authenticated encryption with per-file keys.
 * Each file gets a unique encryption key that is encrypted with a master key.
 * This allows for key rotation without re-encrypting all files.
 */
namespace ProjectSend\Classes;

class Encryption
{
    private string $master_key;
    private string $algorithm = 'aes-256-gcm';
    private int $chunk_size = 8192; // 8KB chunks for streaming

    public function __construct()
    {
        $this->master_key = $this->getMasterKey();
    }

    /**
     * Get or generate the master encryption key
     *
     * @return string Binary master key
     */
    private function getMasterKey()
    {
        // Check if master key exists in config
        if (defined('ENCRYPTION_MASTER_KEY') && ENCRYPTION_MASTER_KEY !== '') {
            return base64_decode(ENCRYPTION_MASTER_KEY);
        }

        // For backward compatibility, generate from existing secret if available
        if (defined('HASH_SALT') && HASH_SALT !== '') {
            // Derive a 256-bit key from the existing hash salt
            return hash_pbkdf2('sha256', HASH_SALT, 'projectsend-encryption', 10000, 32, true);
        }

        // Auto-generate and persist the key for installations upgraded from older versions
        $generated_key = $this->generateAndPersistEncryptionKey();
        if ($generated_key !== null) {
            return $generated_key;
        }

        // Last resort fallback - key won't persist across requests
        error_log('WARNING: No encryption master key configured and could not write to config. Using temporary key.');
        return random_bytes(32);
    }

    /**
     * Generate a new ENCRYPTION_MASTER_KEY and append it to sys.config.php
     */
    private function generateAndPersistEncryptionKey()
    {
        $config_file = CONFIG_FILE;
        if (!file_exists($config_file) || !is_writable($config_file)) {
            return null;
        }

        $key_bytes = random_bytes(32);
        $key_base64 = base64_encode($key_bytes);

        $config_addition = "\n/** Auto-generated encryption key */\ndefine('ENCRYPTION_MASTER_KEY', '" . $key_base64 . "');\n";

        if (file_put_contents($config_file, $config_addition, FILE_APPEND | LOCK_EX) === false) {
            return null;
        }

        if (!defined('ENCRYPTION_MASTER_KEY')) {
            define('ENCRYPTION_MASTER_KEY', $key_base64);
        }

        return $key_bytes;
    }

    /**
     * Generate a random file-specific encryption key
     *
     * @return string Binary encryption key (32 bytes)
     */
    public function generateFileKey()
    {
        return random_bytes(32);
    }

    /**
     * Encrypt a file key with the master key
     *
     * @param string $file_key Binary file key to encrypt
     * @return array<string, mixed> ['encrypted_key' => string, 'iv' => string] Base64 encoded
     */
    public function encryptFileKey($file_key)
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->algorithm));
        $tag = '';

        $encrypted = openssl_encrypt(
            $file_key,
            $this->algorithm,
            $this->master_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            throw new \Exception('Failed to encrypt file key');
        }

        // Combine encrypted key and authentication tag
        $encrypted_with_tag = $encrypted . $tag;

        return [
            'encrypted_key' => base64_encode($encrypted_with_tag),
            'iv' => base64_encode($iv)
        ];
    }

    /**
     * Decrypt a file key with the master key
     *
     * @param string $encrypted_key Base64 encoded encrypted key with tag
     * @param string $iv Base64 encoded IV
     * @return string Binary file key
     */
    public function decryptFileKey($encrypted_key, $iv)
    {
        $encrypted_with_tag = base64_decode($encrypted_key);
        $iv = base64_decode($iv);

        // Extract tag (last 16 bytes for GCM)
        $tag_length = 16;
        $encrypted = substr($encrypted_with_tag, 0, -$tag_length);
        $tag = substr($encrypted_with_tag, -$tag_length);

        $decrypted = openssl_decrypt(
            $encrypted,
            $this->algorithm,
            $this->master_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new \Exception('Failed to decrypt file key - authentication failed');
        }

        return $decrypted;
    }

    /**
     * Encrypt a file using streaming to handle large files
     *
     * @param string $input_path Path to plaintext file
     * @param string $output_path Path to write encrypted file
     * @param string $file_key Binary encryption key
     * @return array<string, mixed> ['success' => bool, 'iv' => string, 'tag' => string, 'error' => string]
     */
    public function encryptFile($input_path, $output_path, $file_key)
    {
        if (!file_exists($input_path)) {
            return [
                'success' => false,
                'error' => 'Input file does not exist'
            ];
        }

        $input = fopen($input_path, 'rb');
        if (!$input) {
            return [
                'success' => false,
                'error' => 'Failed to open input file'
            ];
        }

        $output = fopen($output_path, 'wb');
        if (!$output) {
            fclose($input);
            return [
                'success' => false,
                'error' => 'Failed to open output file'
            ];
        }

        $iv = random_bytes(openssl_cipher_iv_length($this->algorithm));
        $tag = '';

        try {
            // Write IV at the beginning of the file
            fwrite($output, $iv);

            // Read and encrypt file in chunks
            $ciphertext = '';
            while (!feof($input)) {
                $chunk = fread($input, $this->chunk_size);
                if ($chunk === false) {
                    throw new \Exception('Failed to read input file');
                }
                $ciphertext .= $chunk;
            }

            // Encrypt the entire file content
            $encrypted = openssl_encrypt(
                $ciphertext,
                $this->algorithm,
                $file_key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($encrypted === false) {
                throw new \Exception('Encryption failed');
            }

            // Write encrypted content and tag
            fwrite($output, $encrypted);
            fwrite($output, $tag);

            fclose($input);
            fclose($output);

            return [
                'success' => true,
                'iv' => base64_encode($iv),
                'tag' => base64_encode($tag)
            ];

        } catch (\Exception $e) {
            fclose($input);
            fclose($output);

            // Clean up partial output file
            if (file_exists($output_path)) {
                unlink($output_path);
            }

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Decrypt a file and stream it to output
     * Used for serving files to users
     *
     * @param string $encrypted_path Path to encrypted file
     * @param string $file_key Binary encryption key
     * @return bool Success
     */
    public function decryptFileStream($encrypted_path, $file_key)
    {
        if (!file_exists($encrypted_path)) {
            return false;
        }

        $input = fopen($encrypted_path, 'rb');
        if (!$input) {
            return false;
        }

        try {
            // Read IV from the beginning of the file
            $iv_length = openssl_cipher_iv_length($this->algorithm);
            $iv = fread($input, $iv_length);
            if ($iv === false || strlen($iv) !== $iv_length) {
                throw new \Exception('Failed to read IV');
            }

            // Read the rest of the file (encrypted content + tag)
            $file_size = filesize($encrypted_path);
            $encrypted_size = $file_size - $iv_length;

            // Read encrypted content and tag
            $tag_length = 16; // GCM tag is 16 bytes
            $ciphertext_with_tag = fread($input, $encrypted_size);
            fclose($input);

            if ($ciphertext_with_tag === false) {
                throw new \Exception('Failed to read encrypted content');
            }

            // Split ciphertext and tag
            $ciphertext = substr($ciphertext_with_tag, 0, -$tag_length);
            $tag = substr($ciphertext_with_tag, -$tag_length);

            // Decrypt
            $plaintext = openssl_decrypt(
                $ciphertext,
                $this->algorithm,
                $file_key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($plaintext === false) {
                throw new \Exception('Decryption failed - authentication failed');
            }

            // Stream decrypted content to output in chunks
            $offset = 0;
            $length = strlen($plaintext);

            while ($offset < $length) {
                $chunk = substr($plaintext, $offset, $this->chunk_size);
                echo $chunk;
                ob_flush();
                flush();

                // Check if connection is still alive
                if (connection_status() != 0) {
                    return false;
                }

                $offset += $this->chunk_size;
            }

            return true;

        } catch (\Exception $e) {
            error_log('Decryption error: ' . $e->getMessage());
            if (is_resource($input)) {
                fclose($input);
            }
            return false;
        }
    }

    /**
     * Decrypt a file to a temporary location
     * Used for operations that need the full decrypted file
     *
     * @param string $encrypted_path Path to encrypted file
     * @param string $output_path Path to write decrypted file
     * @param string $file_key Binary encryption key
     * @return array<string, mixed> ['success' => bool, 'error' => string]
     */
    public function decryptFileToPath($encrypted_path, $output_path, $file_key)
    {
        if (!file_exists($encrypted_path)) {
            return [
                'success' => false,
                'error' => 'Encrypted file does not exist'
            ];
        }

        $input = fopen($encrypted_path, 'rb');
        if (!$input) {
            return [
                'success' => false,
                'error' => 'Failed to open encrypted file'
            ];
        }

        $output = fopen($output_path, 'wb');
        if (!$output) {
            fclose($input);
            return [
                'success' => false,
                'error' => 'Failed to open output file'
            ];
        }

        try {
            // Read IV
            $iv_length = openssl_cipher_iv_length($this->algorithm);
            $iv = fread($input, $iv_length);
            if ($iv === false || strlen($iv) !== $iv_length) {
                throw new \Exception('Failed to read IV');
            }

            // Read encrypted content and tag
            $file_size = filesize($encrypted_path);
            $encrypted_size = $file_size - $iv_length;
            $tag_length = 16;

            $ciphertext_with_tag = fread($input, $encrypted_size);
            fclose($input);

            if ($ciphertext_with_tag === false) {
                throw new \Exception('Failed to read encrypted content');
            }

            // Split ciphertext and tag
            $ciphertext = substr($ciphertext_with_tag, 0, -$tag_length);
            $tag = substr($ciphertext_with_tag, -$tag_length);

            // Decrypt
            $plaintext = openssl_decrypt(
                $ciphertext,
                $this->algorithm,
                $file_key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($plaintext === false) {
                throw new \Exception('Decryption failed - authentication failed');
            }

            // Write decrypted content
            fwrite($output, $plaintext);
            fclose($output);

            return [
                'success' => true
            ];

        } catch (\Exception $e) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }

            // Clean up partial output file
            if (file_exists($output_path)) {
                unlink($output_path);
            }

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if encryption is enabled globally
     *
     * @return bool
     */
    public static function isEnabled()
    {
        return get_option('files_encryption_enabled', null, '0') === '1';
    }

    /**
     * Check if encryption is required globally
     * Note: Encryption can only be required if it's enabled
     *
     * @return bool
     */
    public static function isRequired()
    {
        // Encryption can't be required if it's not enabled
        if (!self::isEnabled()) {
            return false;
        }

        return get_option('files_encryption_required', null, '0') === '1';
    }

    /**
     * Get the encryption algorithm being used
     *
     * @return string
     */
    public function getAlgorithm()
    {
        return $this->algorithm;
    }
}
