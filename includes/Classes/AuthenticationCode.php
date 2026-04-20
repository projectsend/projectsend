<?php
namespace ProjectSend\Classes;
use \PDO;

class AuthenticationCode
{
    private $dbh;

    public $id;
    public $user_id;
    public $token;
    public $code;
    public $used;
    public $used_timestamp;
    public $timestamp;
    public $expiry_date;
    private $minutes_between_attempts;

    public function __construct($record_id = null)
    {
        global $dbh;

        $this->dbh = $dbh;

        $this->minutes_between_attempts = 5;

        if (!empty($record_id)) {
            $this->getById($record_id);
        }
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        if (!empty($this->id)) {
            return $this->id;
        }

        return false;
    }

    public function requires2fa($user_id = null)
    {
        // @todo
        // if ($this->currentDeviceIsTrusted()) {
        //     return false;
        // }

        // Check if user has individually configured a 2FA method
        if ($user_id) {
            $totp = new \ProjectSend\Classes\Totp();
            $user_method = $totp->getUserMethod($user_id);
            if ($user_method) {
                return true;
            }
        }

        // Check new global setting
        if ((bool)get_option('two_factor_required', null, '0')) {
            return true;
        }

        // Legacy compatibility - only applies before upgrade_2026032701 has run,
        // because that upgrade copies this value to two_factor_required. Once the
        // new option exists in the DB, ignore the old one (it may be stale '1').
        if (!option_exists('two_factor_required')) {
            return (bool)get_option('authentication_require_email_code');
        }

        return false;
    }

    /**
     * Determine which 2FA method to use for a given user
     * Returns 'totp', 'email', or null
     */
    public function get2faMethod($user_id)
    {
        if (!$this->requires2fa($user_id)) {
            return null;
        }

        $totp = new \ProjectSend\Classes\Totp();
        $user_method = $totp->getUserMethod($user_id);

        // If user has TOTP configured and it's allowed, use it
        if ($user_method === 'totp' && $totp->isEnabledForUser($user_id)) {
            if ((bool)get_option('two_factor_allow_totp', null, '1')) {
                return 'totp';
            }
        }

        // If user explicitly chose email, or as fallback
        if ((bool)get_option('two_factor_allow_email', null, '1')) {
            return 'email';
        }

        // If only TOTP is allowed but user hasn't set it up
        if ((bool)get_option('two_factor_allow_totp', null, '1')) {
            return 'totp_setup_required';
        }

        return 'email';
    }

    public function requestNewCode($user_id = null)
    {
        if (empty($user_id)) {
            return json_encode([
                'status' => 'error',
                'message' => __('User ID must not be empty.','cftp_admin'),
            ]);
        }

        if (!$this->canRequestNewCode($user_id)) {
            global $json_strings;
            return json_encode([
                'status' => 'error',
                'message' => sprintf($json_strings['login']['errors']['2fa']['throttle'], $this->whenCanRequestNewCode($user_id)),
            ]);
        }

        $token = generate_random_string(32);
        $code = mt_rand(100000,999999);
        $used = 0;
        $statement = $this->dbh->prepare("INSERT INTO " . TABLE_AUTHENTICATION_CODES . " (user_id, token, code, used, timestamp)"
        ."VALUES (:user_id, :token, :code, :used, :timestamp)");
        $now = date('Y-m-d H:i:s');
        $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $statement->bindParam(':token', $token);
        $statement->bindParam(':code', $code);
        $statement->bindParam(':used', $used, PDO::PARAM_INT);
        $statement->bindParam(':timestamp', $now);
        $statement->execute();

        $this->getByTokenAndCode($token, $code);

        $user = get_user_by_id($user_id);

        $email = new \ProjectSend\Classes\Emails;
        $email->send([
            'type' => '2fa_code',
            'address' => $user['email'],
            'code' => $code,
            'expiry_date' => $this->getExpiryDate(),
        ]);

        return json_encode([
            'status' => 'success',
            'token' => $token,
            'code' => $code,
        ]);
    }

    /**
     * Create a token record for TOTP verification (no email sent)
     * The token links the pre-auth state to the user
     */
    public function createTotpToken($user_id)
    {
        if (empty($user_id)) {
            return json_encode([
                'status' => 'error',
                'message' => __('User ID must not be empty.', 'cftp_admin'),
            ]);
        }

        $token = generate_random_string(32);
        $code = 0; // Marker for TOTP tokens
        $used = 0;
        $statement = $this->dbh->prepare("INSERT INTO " . TABLE_AUTHENTICATION_CODES . " (user_id, token, code, used, timestamp)"
        . "VALUES (:user_id, :token, :code, :used, :timestamp)");
        $now = date('Y-m-d H:i:s');
        $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $statement->bindParam(':token', $token);
        $statement->bindParam(':code', $code, PDO::PARAM_INT);
        $statement->bindParam(':used', $used, PDO::PARAM_INT);
        $statement->bindParam(':timestamp', $now);
        $statement->execute();

        return json_encode([
            'status' => 'success',
            'token' => $token,
        ]);
    }

    public function getExpiryDate()
    {
        if (empty($this->id)) {
            return '2022-04-16 07:54:00'; // A mi hija María del Sol. Te amo.
        }

        // TOTP tokens (code=0) get a longer window since the code is app-generated
        $minutes = ($this->code == 0) ? 10 : $this->minutes_between_attempts;
        $expiry_date = date('Y-m-d H:i:s',strtotime('+'.$minutes.' minutes',strtotime($this->timestamp)));

        return $expiry_date;
    }

    public function codeExpired()
    {
        $expiry = $this->getExpiryDate();
        $now = date('Y-m-d H:i:s');

        if ($expiry > $now) {
            return false;
        }

        return true;
    }

    public function getByToken($token = null)
    {
        if (!$token) {
            return false;
        }

        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_AUTHENTICATION_CODES . " WHERE token=:token");
		$statement->execute([
            ':token' => $token,
        ]);
		if ($statement->rowCount() > 0) {
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $row = $statement->fetch() ) {
                return $this->getByTokenAndCode($row['token'], $row['code']);
            }
        }

        return false;
    }

    public function getByTokenAndCode($token = null, $code = null)
    {
        if (!$token || ($code === null || $code === '')) {
            return false;
        }

        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_AUTHENTICATION_CODES . " WHERE token=:token AND code=:code");
		$statement->execute([
            ':token' => $token,
            ':code' => (int)$code,
        ]);
		if ($statement->rowCount() > 0) {
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $row = $statement->fetch() ) {
                $this->id = $row['id'];
                $this->user_id = $row['user_id'];
                $this->token = $row['token'];
                $this->code = $row['code'];
                $this->used = $row['used'];
                $this->used_timestamp = $row['used_timestamp'];
                $this->timestamp = $row['timestamp'];
                $this->expiry_date = $this->getExpiryDate();

                return true;
            }
        }

        return false;
    }

    public function getById($id)
    {
        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_AUTHENTICATION_CODES . " WHERE id=:id");
		$statement->execute([
            ':id' => (int)$id,
        ]);
		if ($statement->rowCount() > 0) {
			$statement->setFetchMode(PDO::FETCH_ASSOC);
            while ( $row = $statement->fetch() ) {
                $this->getByTokenAndCode($row['token'], $row['code']);
            }
        }
    }

    /**
     * Return the current properties
     */
    public function getProperties()
    {
        $return = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'token' => $this->token,
            'code' => $this->code,
            'used' => $this->used,
            'used_timestamp' => $this->used_timestamp,
            'timestamp' => $this->timestamp,
            'expiry_date' => $this->expiry_date,
        ];

        return $return;
    }

    public function validateRequest($token, $code)
    {
        global $json_strings;
        if (!$this->getByTokenAndCode($token, $code)) {
            return json_encode([
                'status' => 'error',
                'message' => $json_strings['login']['errors']['2fa']['invalid'],
            ]);
        }

        if ($this->used != '0') {
            return json_encode([
                'status' => 'error',
                'message' => $json_strings['login']['errors']['2fa']['used'],
            ]);
        }

        if ($this->codeExpired()) {
            return json_encode([
                'status' => 'error',
                'message' => $json_strings['login']['errors']['2fa']['expired'],
            ]);
        }

        $this->markAsUsed();

        return json_encode([
            'status' => 'success',
        ]);
    }

    public function markAsUsed()
    {
        if (empty($this->id)) {
            return;
        }

        $query = $this->dbh->prepare("UPDATE " . TABLE_AUTHENTICATION_CODES . " SET used = 1, used_timestamp=NOW() WHERE id = :id");
        $query->bindParam(':id', $this->id, PDO::PARAM_INT);
        $query->execute();
    }

    public function canRequestNewCode($user_id)
    {
        $query = "SELECT * FROM " . TABLE_AUTHENTICATION_CODES . " WHERE user_id=:user_id AND used=:used AND timestamp > DATE_SUB(NOW(), INTERVAL ".$this->minutes_between_attempts." MINUTE)";
        $statement = $this->dbh->prepare($query);
		$statement->execute([
            ':used' => 0,
            ':user_id' => $user_id,
        ]);
		if ($statement->rowCount() > 0) {
            $statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $statement->fetch() ) {
                return false;
            }
        }

        return true;
    }

    public function whenCanRequestNewCode($user_id)
    {
        $query = "SELECT * FROM " . TABLE_AUTHENTICATION_CODES . " WHERE user_id=:user_id AND timestamp > DATE_SUB(NOW(), INTERVAL ".$this->minutes_between_attempts." MINUTE)";
        $statement = $this->dbh->prepare($query);
		$statement->execute([
            ':user_id' => $user_id,
        ]);
		if ($statement->rowCount() > 0) {
            $statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $row = $statement->fetch() ) {
                $expiry_date = date('Y-m-d H:i:s',strtotime('+'.$this->minutes_between_attempts.' minutes',strtotime($row['timestamp'])));
                return $expiry_date;
            }
        }

        return date('Y-m-d H:i:s');
    }

    /**
     * Get the token of the latest pending (unused, not expired) code for a user
     */
    public function getLatestPendingToken($user_id)
    {
        $query = "SELECT token FROM " . TABLE_AUTHENTICATION_CODES . " WHERE user_id=:user_id AND used=0 AND timestamp > DATE_SUB(NOW(), INTERVAL " . $this->minutes_between_attempts . " MINUTE) ORDER BY id DESC LIMIT 1";
        $statement = $this->dbh->prepare($query);
        $statement->execute([
            ':user_id' => $user_id,
        ]);
        if ($statement->rowCount() > 0) {
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            return $row['token'];
        }

        return null;
    }
}
