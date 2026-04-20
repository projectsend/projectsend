<?php
namespace ProjectSend\Classes;

use \PDO;
use OTPHP\TOTP as OtphpTotp;

class Totp
{
    private $dbh;
    private $algorithm = 'aes-256-gcm';

    public function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;
    }

    /**
     * Get the encryption key for TOTP secrets
     * Uses the same derivation as the Encryption class
     */
    private function getEncryptionKey()
    {
        if (defined('ENCRYPTION_MASTER_KEY') && ENCRYPTION_MASTER_KEY !== '') {
            return base64_decode(ENCRYPTION_MASTER_KEY);
        }

        if (defined('HASH_SALT') && HASH_SALT !== '') {
            return hash_pbkdf2('sha256', HASH_SALT, 'projectsend-totp', 10000, 32, true);
        }

        // Auto-generate and persist the key for installations that were upgraded
        // from versions that didn't include ENCRYPTION_MASTER_KEY in the config
        $generated_key = $this->generateAndPersistEncryptionKey();
        if ($generated_key !== null) {
            return $generated_key;
        }

        throw new \Exception('No encryption key available for TOTP secret storage. Please add ENCRYPTION_MASTER_KEY to your sys.config.php file.');
    }

    /**
     * Generate a new ENCRYPTION_MASTER_KEY and append it to sys.config.php
     * This handles upgrades from older versions that didn't have this constant.
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

        // Define the constant for the current request
        define('ENCRYPTION_MASTER_KEY', $key_base64);

        return $key_bytes;
    }

    /**
     * Generate a new TOTP secret
     */
    public function generateSecret()
    {
        $totp = OtphpTotp::generate(secretSize: 20);
        return $totp->getSecret();
    }

    /**
     * Get the provisioning URI for QR code generation
     */
    public function getProvisioningUri($secret, $userEmail)
    {
        $totp = OtphpTotp::createFromSecret($secret);
        $totp->setLabel($userEmail);
        $totp->setIssuer(get_option('this_install_title', null, 'ProjectSend'));

        return $totp->getProvisioningUri();
    }

    /**
     * Generate a QR code data URI from a provisioning URI
     * Returns a base64-encoded SVG data URI suitable for use in <img src="">
     */
    public function generateQrCodeDataUri($provisioningUri)
    {
        $options = new \chillerlan\QRCode\QROptions;
        $options->outputInterface = \chillerlan\QRCode\Output\QRMarkupSVG::class;
        $options->svgUseCssProperties = false;
        $options->drawLightModules = true;
        $options->addQuietzone = true;

        $qrcode = new \chillerlan\QRCode\QRCode($options);
        return $qrcode->render($provisioningUri);
    }

    /**
     * Verify a TOTP code against a secret
     * Allows 1 window of tolerance for clock drift (±30 seconds)
     */
    public function verifyCode($secret, $code)
    {
        $totp = OtphpTotp::createFromSecret($secret);
        return $totp->verify($code, null, 1);
    }

    /**
     * Encrypt a TOTP secret for storage
     */
    public function encryptSecret($secret)
    {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(openssl_cipher_iv_length($this->algorithm));
        $tag = '';

        $encrypted = openssl_encrypt(
            $secret,
            $this->algorithm,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            throw new \Exception('Failed to encrypt TOTP secret');
        }

        // Format: base64(iv + encrypted + tag)
        return base64_encode($iv . $encrypted . $tag);
    }

    /**
     * Decrypt a stored TOTP secret
     */
    public function decryptSecret($encryptedData)
    {
        $key = $this->getEncryptionKey();
        $data = base64_decode($encryptedData);

        $iv_length = openssl_cipher_iv_length($this->algorithm);
        $tag_length = 16; // GCM tag

        $iv = substr($data, 0, $iv_length);
        $tag = substr($data, -$tag_length);
        $encrypted = substr($data, $iv_length, -$tag_length);

        $decrypted = openssl_decrypt(
            $encrypted,
            $this->algorithm,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new \Exception('Failed to decrypt TOTP secret');
        }

        return $decrypted;
    }

    /**
     * Generate backup codes
     */
    public function generateBackupCodes($count = 8)
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $part1 = strtoupper(bin2hex(random_bytes(3))); // 6 hex chars
            $part2 = strtoupper(bin2hex(random_bytes(3)));
            $codes[] = $part1 . '-' . $part2;
        }
        return $codes;
    }

    /**
     * Store backup codes (hashed) for a user, replacing any previous set
     */
    public function storeBackupCodes($userId, $codes)
    {
        // Delete existing codes
        $stmt = $this->dbh->prepare("DELETE FROM " . TABLE_TOTP_BACKUP_CODES . " WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Insert new codes
        $now = date('Y-m-d H:i:s');
        $stmt = $this->dbh->prepare("INSERT INTO " . TABLE_TOTP_BACKUP_CODES . " (user_id, code_hash, used, created_at) VALUES (:user_id, :code_hash, 0, :created_at)");

        foreach ($codes as $code) {
            $hash = password_hash($code, PASSWORD_BCRYPT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':code_hash', $hash);
            $stmt->bindParam(':created_at', $now);
            $stmt->execute();
        }
    }

    /**
     * Validate a backup code. If valid, mark it as used.
     */
    public function validateBackupCode($userId, $code)
    {
        // Normalize: remove spaces and dashes, uppercase
        $code = strtoupper(str_replace([' ', '-'], ['', '-'], trim($code)));

        $stmt = $this->dbh->prepare("SELECT id, code_hash FROM " . TABLE_TOTP_BACKUP_CODES . " WHERE user_id = :user_id AND used = 0");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($code, $row['code_hash'])) {
                // Mark as used
                $update = $this->dbh->prepare("UPDATE " . TABLE_TOTP_BACKUP_CODES . " SET used = 1, used_timestamp = NOW() WHERE id = :id");
                $update->bindParam(':id', $row['id'], PDO::PARAM_INT);
                $update->execute();
                return true;
            }
        }

        return false;
    }

    /**
     * Get the count of remaining unused backup codes
     */
    public function getRemainingBackupCodesCount($userId)
    {
        $stmt = $this->dbh->prepare("SELECT COUNT(*) FROM " . TABLE_TOTP_BACKUP_CODES . " WHERE user_id = :user_id AND used = 0");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Enable TOTP for a user: store encrypted secret and update flags
     */
    public function enableForUser($userId, $secret)
    {
        $encryptedSecret = $this->encryptSecret($secret);

        $stmt = $this->dbh->prepare("UPDATE " . TABLE_USERS . " SET totp_secret = :secret, totp_enabled = 1, two_factor_method = 'totp' WHERE id = :id");
        $stmt->bindParam(':secret', $encryptedSecret);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Disable TOTP for a user: clear secret and backup codes
     */
    public function disableForUser($userId)
    {
        $stmt = $this->dbh->prepare("UPDATE " . TABLE_USERS . " SET totp_secret = NULL, totp_enabled = 0, two_factor_method = NULL WHERE id = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete backup codes
        $stmt = $this->dbh->prepare("DELETE FROM " . TABLE_TOTP_BACKUP_CODES . " WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Get and decrypt the TOTP secret for a user
     */
    public function getUserSecret($userId)
    {
        $stmt = $this->dbh->prepare("SELECT totp_secret FROM " . TABLE_USERS . " WHERE id = :id AND totp_enabled = 1");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['totp_secret'])) {
            return null;
        }

        return $this->decryptSecret($row['totp_secret']);
    }

    /**
     * Check if a user has TOTP enabled
     */
    public function isEnabledForUser($userId)
    {
        $stmt = $this->dbh->prepare("SELECT totp_enabled FROM " . TABLE_USERS . " WHERE id = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row && $row['totp_enabled'] == 1;
    }

    /**
     * Get the user's configured 2FA method
     */
    public function getUserMethod($userId)
    {
        $stmt = $this->dbh->prepare("SELECT two_factor_method FROM " . TABLE_USERS . " WHERE id = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['two_factor_method'] : null;
    }

    /**
     * Set 2FA method preference for a user (without changing TOTP secret)
     */
    public function setUserMethod($userId, $method)
    {
        $valid_methods = ['email', 'totp', null];
        if (!in_array($method, $valid_methods, true)) {
            return false;
        }

        $stmt = $this->dbh->prepare("UPDATE " . TABLE_USERS . " SET two_factor_method = :method WHERE id = :id");
        $stmt->bindParam(':method', $method);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
