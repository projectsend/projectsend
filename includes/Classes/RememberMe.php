<?php
/**
 * Secure "Remember Me" token management class
 * Handles creation, validation, and cleanup of remember me tokens
 */
namespace ProjectSend\Classes;
use \PDO;

class RememberMe
{
    private $dbh;
    private $logger;
    private $token_length = 32; // 32 bytes = 256 bits
    private $cookie_name = 'ps_remember_token';
    
    public function __construct(?PDO $dbh = null)
    {
        if (empty($dbh)) {
            global $dbh;
        }
        
        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;
    }
    
    /**
     * Generate a cryptographically secure random token
     * @return string Base64 encoded token
     */
    public function generateToken()
    {
        return base64_encode(random_bytes($this->token_length));
    }
    
    /**
     * Store a remember me token for a user
     * @param int $user_id
     * @param string $token Plain text token
     * @param string $user_agent User agent for security
     * @return bool Success
     */
    public function storeToken($user_id, $token, $user_agent = null)
    {
        if (!get_option('remember_me_enabled', null, '1')) {
            return false;
        }
        
        // Check if user has too many tokens
        $max_tokens = (int)get_option('remember_me_max_tokens_per_user', null, '5');
        $this->cleanupUserTokens($user_id, $max_tokens - 1);
        
        // Hash the token before storing
        $token_hash = hash('sha256', $token);
        
        // Calculate expiration
        $duration_days = (int)get_option('remember_me_duration_days', null, '30');
        $expires_at = date('Y-m-d H:i:s', time() + ($duration_days * 24 * 60 * 60));
        
        $query = "INSERT INTO " . TABLE_REMEMBER_TOKENS . " 
                  (user_id, token_hash, expires_at, user_agent) 
                  VALUES (?, ?, ?, ?)";
        
        $statement = $this->dbh->prepare($query);
        $result = $statement->execute([
            $user_id,
            $token_hash,
            $expires_at,
            $user_agent ?: $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        if ($result) {
            // Log the token creation
            $this->logger->addEntry([
                'action' => 24, // Account logs in through cookies
                'owner_id' => $user_id,
                'details' => 'Remember me token created'
            ]);
        }
        
        return $result;
    }
    
    /**
     * Validate and rotate a remember me token
     * @param string $token Plain text token
     * @return array|false User data or false if invalid
     */
    public function validateToken($token)
    {
        if (!get_option('remember_me_enabled', null, '1')) {
            return false;
        }
        
        $token_hash = hash('sha256', $token);
        
        $query = "SELECT rt.id, rt.user_id, rt.expires_at, rt.user_agent, rt.last_used,
                         u.user, u.name, u.email, u.role_id, u.active
                  FROM " . TABLE_REMEMBER_TOKENS . " rt
                  JOIN " . TABLE_USERS . " u ON rt.user_id = u.id
                  WHERE rt.token_hash = ? AND rt.expires_at > NOW()";
        
        $statement = $this->dbh->prepare($query);
        $statement->execute([$token_hash]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return false;
        }
        
        // Check if user is still active
        if (!$result['active']) {
            $this->revokeToken($token);
            return false;
        }
        
        // Security check: compare user agent (basic protection against token theft)
        $stored_user_agent = $result['user_agent'];
        $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if ($stored_user_agent && $stored_user_agent !== $current_user_agent) {
            // Log suspicious activity
            $this->logger->addEntry([
                'action' => 25, // Suspicious login attempt
                'owner_id' => $result['user_id'],
                'details' => 'Remember me token used with different user agent'
            ]);
            
            // Optionally revoke token on user agent mismatch
            // $this->revokeToken($token);
            // return false;
        }
        
        // Update last used timestamp
        $this->updateTokenUsage($result['id']);
        
        // Generate new token for rotation
        $new_token = $this->generateToken();
        $this->rotateToken($result['id'], $new_token);
        
        // Set new cookie
        $this->setCookie($new_token);
        
        // Log successful authentication
        $this->logger->addEntry([
            'action' => 24, // Account logs in through cookies
            'owner_id' => $result['user_id'],
            'details' => 'Remember me token validated and rotated'
        ]);
        
        return [
            'user_id' => $result['user_id'],
            'username' => $result['user'],
            'name' => $result['name'],
            'email' => $result['email'],
            'role_id' => $result['role_id']
        ];
    }
    
    /**
     * Set the remember me cookie
     * @param string $token
     */
    public function setCookie($token)
    {
        $duration_days = (int)get_option('remember_me_duration_days', null, '30');
        $expires = time() + ($duration_days * 24 * 60 * 60);
        
        setcookie(
            $this->cookie_name,
            $token,
            [
                'expires' => $expires,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }
    
    /**
     * Clear the remember me cookie
     */
    public function clearCookie()
    {
        setcookie(
            $this->cookie_name,
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }
    
    /**
     * Get remember me token from cookie
     * @return string|null
     */
    public function getTokenFromCookie()
    {
        return $_COOKIE[$this->cookie_name] ?? null;
    }
    
    /**
     * Revoke a specific token
     * @param string $token Plain text token
     */
    public function revokeToken($token)
    {
        $token_hash = hash('sha256', $token);
        
        $query = "DELETE FROM " . TABLE_REMEMBER_TOKENS . " WHERE token_hash = ?";
        $statement = $this->dbh->prepare($query);
        $statement->execute([$token_hash]);
    }
    
    /**
     * Revoke all tokens for a user (logout from all devices)
     * @param int $user_id
     */
    public function revokeUserTokens($user_id)
    {
        $query = "DELETE FROM " . TABLE_REMEMBER_TOKENS . " WHERE user_id = ?";
        $statement = $this->dbh->prepare($query);
        $statement->execute([$user_id]);
        
        $this->logger->addEntry([
            'action' => 3, // Account logged out
            'owner_id' => $user_id,
            'details' => 'All remember me tokens revoked'
        ]);
    }
    
    /**
     * Clean expired tokens for all users
     */
    public function cleanExpiredTokens()
    {
        $query = "DELETE FROM " . TABLE_REMEMBER_TOKENS . " WHERE expires_at < NOW()";
        $statement = $this->dbh->prepare($query);
        $statement->execute();
        
        return $statement->rowCount();
    }
    
    /**
     * Clean old tokens for a specific user, keeping only the most recent ones
     * @param int $user_id
     * @param int $keep_count Number of tokens to keep
     */
    private function cleanupUserTokens($user_id, $keep_count = 4)
    {
        $query = "DELETE FROM " . TABLE_REMEMBER_TOKENS . " 
                  WHERE user_id = ? AND id NOT IN (
                      SELECT id FROM (
                          SELECT id FROM " . TABLE_REMEMBER_TOKENS . " 
                          WHERE user_id = ? 
                          ORDER BY created_at DESC 
                          LIMIT ?
                      ) as recent_tokens
                  )";
        
        $statement = $this->dbh->prepare($query);
        $statement->execute([$user_id, $user_id, $keep_count]);
    }
    
    /**
     * Update token usage timestamp
     * @param int $token_id
     */
    private function updateTokenUsage($token_id)
    {
        $query = "UPDATE " . TABLE_REMEMBER_TOKENS . " 
                  SET last_used = NOW() 
                  WHERE id = ?";
        
        $statement = $this->dbh->prepare($query);
        $statement->execute([$token_id]);
    }
    
    /**
     * Rotate token by generating new hash
     * @param int $token_id
     * @param string $new_token
     */
    private function rotateToken($token_id, $new_token)
    {
        $new_token_hash = hash('sha256', $new_token);
        
        $query = "UPDATE " . TABLE_REMEMBER_TOKENS . " 
                  SET token_hash = ? 
                  WHERE id = ?";
        
        $statement = $this->dbh->prepare($query);
        $statement->execute([$new_token_hash, $token_id]);
    }
    
    /**
     * Get token statistics for a user
     * @param int $user_id
     * @return array
     */
    public function getUserTokenStats($user_id)
    {
        $query = "SELECT COUNT(*) as total_tokens,
                         MIN(created_at) as oldest_token,
                         MAX(last_used) as last_activity
                  FROM " . TABLE_REMEMBER_TOKENS . " 
                  WHERE user_id = ? AND expires_at > NOW()";
        
        $statement = $this->dbh->prepare($query);
        $statement->execute([$user_id]);
        
        return $statement->fetch(PDO::FETCH_ASSOC);
    }
}