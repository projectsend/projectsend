<?php
namespace ProjectSend\Classes;
use \PDO;

class AuthenticationCode {
    private $dbh;
    private $logger;

    public $id;
    public $user_id;
    public $token;
    public $code;
    public $used;
    public $used_timestamp;

    public function __construct($record_id = null)
    {
        global $dbh;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

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

    public function createNew($user_id = null)
    {
        if (empty($user_id)) {
            return false;
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

        return [
            'token' => $token,
            'code' => $code,
        ];
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
            ':code' => $code,
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

                return true;
            }
        }

        return false;
    }

    public function getById($id)
    {
        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_AUTHENTICATION_CODES . " WHERE id=:id");
		$statement->execute([
            ':id' => $id,
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
        ];

        return $return;
    }

    public function validateRequest($token, $code)
    {
        if (!$this->getByTokenAndCode($token, $code)) {
            return false;
        }

        if ($this->used != '0') {
            return false;
        }

        $this->markAsUsed();

        return true;
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
}
