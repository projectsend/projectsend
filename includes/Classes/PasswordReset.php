<?php
namespace ProjectSend\Classes;
use \PDO;

class PasswordReset extends Base
{
    public function __construct($record_id = null)
    {
        parent::__construct();

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

    public function getById($id)
    {
        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_PASSWORD_RESET . " WHERE id=:id");
		$statement->execute([
            ':id' => (int)$id,
        ]);
		if ($statement->rowCount() > 0) {
			$statement->setFetchMode(PDO::FETCH_ASSOC);
            while ( $row = $statement->fetch() ) {
                return $this->getByTokenAndUserId($row['token'], $row['user_id']);
            }
        }

        return false;
    }

    public function getByTokenAndUserId($token = null, $user_id = null)
    {
        $this->id = null;
        $this->user_id = null;
        $this->token = null;
        $this->used = null;
        $this->timestamp = null;

        if (!$token || !$user_id) {
            return false;
        }

        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_PASSWORD_RESET . " WHERE token = :token AND user_id = :id");
        $statement->bindParam(':token', $token);
        $statement->bindParam(':id', $user_id, PDO::PARAM_INT);
        $statement->execute();

        if ($statement->rowCount() > 0) {
            $statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $row = $statement->fetch() ) {
                $this->id = $row['id'];
                $this->user_id = $row['user_id'];
                $this->token = $row['token'];
                $this->used = $row['used'];
                $this->timestamp = $row['timestamp'];

                return true;
            }
        }

        return false;
    }

    /**
     * Count how many request were made by this user today.
     * No more than 3 unused should exist at a time.
     */
    public function canRequestNew($user_id)
    {
        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_PASSWORD_RESET . " WHERE user_id = :id AND used = '0' AND timestamp > NOW() - INTERVAL 1 DAY");
        $statement->bindParam(':id', $user_id, PDO::PARAM_INT);
        $statement->execute();
        $count_requests = $statement->rowCount();
        if ($count_requests >= 3){
            return false;
        }

        return true;
    }

    public function validate()
    {
        if (empty($this->id) || empty($this->user_id)) {
            return [
                'status' => 'error',
                'message' => __('The request is not valid.')
            ];
        }

        if (!$this->getById($this->id)) {
            return [
                'status' => 'error',
                'message' => __('The request is not valid.')
            ];
        }

        if ($this->used != '0') {
            return [
                'status' => 'error',
                'message' => __("This request has already been completed. Please make a new one.",'cftp_admin'),
            ];
        }

        if (time() - strtotime($this->timestamp) > PASSWORD_RECOVERY_TOKEN_EXPIRATION_TIME) {
            return [
                'status' => 'error',
                'message' => __("This request has expired. Please make a new one.",'cftp_admin'),
            ];
        }

        return [
            'status' => 'success',
        ];
    }

    public function requestNew($user_id)
    {
        if (!$this->canRequestNew($user_id)) {
            return [
                'status' => 'error',
                'message' => __("There are 3 unused requests done in less than 24 hs. Please wait until one expires (1 day since made) to make a new one.",'cftp_admin'),
            ];
        }

        $token = generate_random_string(32);

        $statement = $this->dbh->prepare("INSERT INTO " . TABLE_PASSWORD_RESET . " (user_id, token) VALUES (:id, :token)");
        $statement->bindParam(':token', $token);
        $statement->bindParam(':id', $user_id, PDO::PARAM_INT);
        $statement->execute();

        $user = get_user_by_id($user_id);

        /** Send email */
        $notify_user = new \ProjectSend\Classes\Emails;
        $notify_user->send([
            'type' => 'password_reset',
            'address' => $user['email'],
            'username' => $user['username'],
            'token' => $token
        ]);

        return [
            'status' => 'success',
            'message' => $this->getNewRequestSuccessMessage(),
        ];
    }

    public function markAsUsed()
    {
        if (empty($this->id)) {
            return;
        }

        $query = $this->dbh->prepare("UPDATE " . TABLE_PASSWORD_RESET . " SET used = 1 WHERE id = :id");
        $query->bindParam(':id', $this->id, PDO::PARAM_INT);
        $query->execute();
    }

    public function processRequest($new_password = null)
    {
        $validate = $this->validate();
        if ($validate['status'] == 'error') {
            return $validate;
        }

        if (empty($new_password)) {
            return false;
        }

        $user = new \ProjectSend\Classes\Users($this->user_id);
        if (!$user->setNewPassword($new_password)) {
            return [
                'status' => 'error',
                'message' => __("Your new password couldn't be set.", 'cftp_admin'),
            ];
        }

        $this->markAsUsed();

        return [
            'status' => 'success',
            'message' => __('Your new password has been set. You can now log in using it.', 'cftp_admin'),
        ];
    }

    public function getNewRequestSuccessMessage()
    {
        return __('An e-mail with further instructions has been sent. Please check your inbox to proceed.','cftp_admin');
    }
}
