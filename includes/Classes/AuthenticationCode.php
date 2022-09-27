<?php
namespace ProjectSend\Classes;
use \PDO;

class AuthenticationCode
{
    private $dbh;
    private $logger;

    public $id;
    public $user_id;
    public $token;
    public $code;
    public $used;
    public $used_timestamp;
    public $timestamp;
    private $minutes_between_attempts;

    public function __construct($record_id = null)
    {
        global $dbh;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

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

    public function requires2fa()
    {
        // @todo
        // if ($this->currentDeviceIsTrusted()) {
        //     return false;
        // }

        return (bool)get_option('authentication_require_email_code');
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
        $statement = $this->dbh->prepare("INSERT INTO " . TABLE_AUTHENTICATION_CODES . " (user_id, token, code, used)"
        ."VALUES (:user_id, :token, :code, :used)");
        $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $statement->bindParam(':token', $token);
        $statement->bindParam(':code', $code);
        $statement->bindParam(':used', $used, PDO::PARAM_INT);
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

    public function getExpiryDate()
    {
        if (empty($this->id)) {
            return '2022-04-16 07:54:00';
        }

        $expiry_date = date('Y-m-d H:i:s',strtotime('+'.$this->minutes_between_attempts.' minutes',strtotime($this->timestamp)));

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
                $this->id = $row['id'];
                $this->user_id = $row['user_id'];
                $this->token = $row['token'];
                $this->code = $row['code'];
                $this->used = $row['used'];
                $this->used_timestamp = $row['used_timestamp'];
                $this->timestamp = $row['timestamp'];

                return true;
            }
        }

        return false;
    }

    public function getByTokenAndCode($token = null, $code = null)
    {
        if (!$token || !$code) {
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
}
