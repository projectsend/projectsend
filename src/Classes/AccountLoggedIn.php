<?php

namespace ProjectSend\Classes;

use ProjectSend\Classes\Account;
use ProjectSend\Controller\InstallationController;
use \PDO;

class AccountLoggedIn
{
    protected $account;
    public $dbh;

    function __construct(PDO $pdo)
    {
        $this->dbh = $pdo;
    }

    /**
     * Used on header.php to check if there is an active session or valid
     * cookie before generating the content.
     * If none is found, redirect to the log in form.
     */
    public function check( $redirect = true )
    {
        $is_logged_now = false;
        if (isset($_SESSION['loggedin'])) {
            $is_logged_now = true;
        }
        elseif (isset($_SESSION['access']) && $_SESSION['access'] == 'admin') {
            $is_logged_now = true;
        }
        if ( !$is_logged_now && $redirect == true ) {
            header("location:" . BASE_URI . "index.php");
        }
        return $is_logged_now;
    }

    /**
     * Used on header.php to check if the current logged in account is either
     * a system user or a client.
     *
     * Clients are then redirected to the index page, where another check is
     * performed and then a second redirection takes the client to the
     * correspondent file list.
     *
     * @see check_for_client
     */
    public function check_for_admin() {
        $is_logged_admin = false;
        if (isset($_SESSION['access']) && $_SESSION['access'] == 'admin') {
            $is_logged_admin = true;
        }
        if (!$is_logged_admin) {
            ob_clean();
            header("location:" . BASE_URI . "index.php");
        }
        return $is_logged_admin;
    }

    /**
     * Used on the log in form page (index.php) to take the clients directly to their
     * files list.
     * Also used on the self-registration form (register.php).
     */
    public function check_for_client() {
        if (isset($_SESSION['userlevel']) && $_SESSION['userlevel'] == '0') {
            header("location:" . CLIENT_VIEW_FILE_LIST_URI);
            exit;
        }
        if (isset($_COOKIE['userlevel']) && $_COOKIE['userlevel'] == '0') {
            header("location:" . CLIENT_VIEW_FILE_LIST_URI);
            exit;
        }
    }

    /**
     * Used on header.php to check if the current logged in system user has the
     * permission to view this page.
     */
    public function can_see_content($allowed_levels) {
        $permission = false;
        if(isset($allowed_levels)) {
            if (isset($_SESSION['userlevel']) && in_array($_SESSION['userlevel'],$allowed_levels)) {
                $permission = true;
            }
        }

        return $permission;
    }


    /**
     * Function used accross the system to determine if the current logged in
     * account has permission to do something.
     *
     */
    public function in_session_or_cookies($levels)
    {
        if (isset($_SESSION['userlevel']) && (in_array($_SESSION['userlevel'],$levels))) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Returns the current logged in account level either from the active
     * session.
     *
     * @todo Validate the returned value against the one stored on the database
     */
    public function get_current_user_level()
    {
        $level = 0;
        if (isset($_SESSION['userlevel'])) {
            $level = $_SESSION['userlevel'];
        }
        return $level;
    }

    /**
     * Returns the current logged in account username either from the active
     * session.
     *
     * @todo Validate the returned value against the one stored on the database
     */
    public function get_current_user_username()
    {
        $user = '';

        if (isset($_SESSION['loggedin'])) {
            $user = $_SESSION['loggedin'];
        }
        return $user;
    }

    /**
     * Get all the client information knowing only the log in username
     *
     * @return array
     */
    public function get_logged_account_id($username)
    {
        $statement = $this->dbh->prepare("SELECT id FROM " . TABLE_USERS . " WHERE username=:user");
        $statement->execute(
                            array(
                                ':user'	=> $username
                            )
                        );
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        while ( $row = $statement->fetch() ) {
            $return_id = html_output($row['id']);
            if ( !empty( $return_id ) ) {
                return $return_id;
            }
            else {
                return false;
            }
        }
    }
}