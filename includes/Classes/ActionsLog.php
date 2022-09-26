<?php
/**
 * Class that handles all the actions that are logged on the database.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 *
 */
namespace ProjectSend\Classes;
use \PDO;

class ActionsLog
{

    private $action;
    private $owner_id;
    private $owner_user;
    private $affected_file;
    private $affected_account;
    private $affected_file_name;
    private $affected_account_name;

    private $dbh;

    public function __construct()
    {
        global $dbh;

        $this->dbh = $dbh;
    }

    public function getActivitiesReferences()
    {
        $this->activities_references = array(
            1	=> __('Account logs in through the form','cftp_admin'),
            //24	=> __('Account logs in through cookies','cftp_admin'),
            31	=> __('Account (user or client) logs out','cftp_admin'),
            2	=> __('A user creates a new user account','cftp_admin'),
            3	=> __('A user creates a new client account','cftp_admin'),
            4	=> __('A client registers an account for himself','cftp_admin'),
            42	=> __("A client registers using a social profile",'cftp_admin'),
            43	=> __("Account logged in using a social profile",'cftp_admin'),
            5	=> __('A file is uploaded by an user','cftp_admin'),
            6	=> __('A file is uploaded by a client','cftp_admin'),
            7	=> __('A file is downloaded by a user','cftp_admin'),
            8	=> __('A file is downloaded by a client','cftp_admin'),
            37	=> __('An anonymous user downloaded a public file','cftp_admin'),
            9	=> __('A zip file was generated','cftp_admin'),
            10	=> __('A file has been unassigned from a client','cftp_admin'),
            11	=> __('A file has been unassigned from a group','cftp_admin'),
            12	=> __('A file has been deleted','cftp_admin'),
            13	=> __('A user was edited','cftp_admin'),
            14	=> __('A client was edited','cftp_admin'),
            15	=> __('A group was edited','cftp_admin'),
            16	=> __('A user was deleted','cftp_admin'),
            17	=> __('A client was deleted','cftp_admin'),
            18	=> __('A group was deleted','cftp_admin'),
            27	=> __('A user account was marked as active','cftp_admin'),
            28	=> __('A user account was marked as inactive','cftp_admin'),
            19	=> __('A client account was marked as active','cftp_admin'),
            20	=> __('A client account was marked as inactive','cftp_admin'),
            21	=> __('A file was marked as hidden','cftp_admin'),
            22	=> __('A file was marked as visible','cftp_admin'),
            40	=> __('A file was marked as hidden for everyone','cftp_admin'),
            46	=> __('A file was marked as hidden for everyone','cftp_admin'),
            23	=> __('A user creates a new group','cftp_admin'),
            25	=> __('A file is assigned to a client','cftp_admin'),
            26	=> __('A file is assigned to a group','cftp_admin'),
            29	=> __('The logo on "Branding" was changed','cftp_admin'),
            32	=> __('A system user edited a file','cftp_admin'),
            33	=> __('A client edited a file','cftp_admin'),
            34	=> __('A user created a category','cftp_admin'),
            35	=> __('A user edited a category','cftp_admin'),
            36	=> __('A user deleted a category','cftp_admin'),
            38	=> __('A client account request was processed','cftp_admin'),
            39	=> __("A client's groups membership requests were processed",'cftp_admin'),
            41	=> __("A file preview request was made",'cftp_admin'),
            0	=> __('ProjectSend has been installed','cftp_admin'),
            30	=> __('ProjectSend was updated','cftp_admin'),
            49	=> __('The database was updated','cftp_admin'),
            44	=> __('A client account request was approved','cftp_admin'),
            45	=> __('A client account request was denied','cftp_admin'),
            47	=> __('System options were updated','cftp_admin'),
            48	=> __('An email template was updated','cftp_admin'),
        );

        return $this->activities_references;
    }

	/**
	 * Create entry in database
	 */
	function addEntry($arguments)
	{
		global $dbh;

        /** Define the account information */
        $default_user = (defined('CURRENT_USER_USERNAME')) ? CURRENT_USER_USERNAME : null;
		$this->action = $arguments['action'];
		$this->owner_id = $arguments['owner_id'];
		$this->owner_user = (!empty($arguments['owner_user'])) ? $arguments['owner_user'] : $default_user;
		$this->affected_file = (!empty($arguments['affected_file'])) ? $arguments['affected_file'] : null;
		$this->affected_account = (!empty($arguments['affected_account'])) ? $arguments['affected_account'] : null;
		$this->affected_file_name = (!empty($arguments['affected_file_name'])) ? $arguments['affected_file_name'] : null;
		$this->affected_account_name = (!empty($arguments['affected_account_name'])) ? $arguments['affected_account_name'] : null;
		$this->details = (!empty($arguments['details'])) ? $arguments['details'] : null;
		
		/** Get the real name of the client or user */
		if (!empty($arguments['username_column'])) {
            $user = get_user_by_username($this->affected_account_name);
            $this->affected_account_name = $user['name'];
		}

		/** Get the title of the file on downloads */
		if (!empty($arguments['file_title_column'])) {
            $file = get_file_by_filename($this->affected_file_name);
    		$this->affected_file_name = $file['title'];
        }
        
        if (is_array($this->details)) {
            $this->details = json_encode($this->details);
        }

		/** Insert the client information into the database */
		$lq = "INSERT INTO " . TABLE_LOG . " (action,owner_id,owner_user,details";
		
			if (!empty($this->affected_file)) { $lq .= ",affected_file"; }
			if (!empty($this->affected_account)) { $lq .= ",affected_account"; }
			if (!empty($this->affected_file_name)) { $lq .= ",affected_file_name"; }
			if (!empty($this->affected_account_name)) { $lq .= ",affected_account_name"; }
		
		$lq .= ") VALUES (:action, :owner_id, :owner_user, :details";

			$params = array(
							':action'		=> $this->action,
							':owner_id'		=> $this->owner_id,
							':owner_user'	=> $this->owner_user,
							':details'		=> $this->details,
						);
		
			if (!empty($this->affected_file)) {			$lq .= ", :file";		$params['file'] = $this->affected_file; }
			if (!empty($this->affected_account)) {		$lq .= ", :account";	$params['account'] = $this->affected_account; }
			if (!empty($this->affected_file_name)) {	$lq .= ", :title";		$params['title'] = $this->affected_file_name; }
			if (!empty($this->affected_account_name)) {	$lq .= ", :name";		$params['name'] = $this->affected_account_name; }

		$lq .= ")";

		$this->sql_query = $dbh->prepare( $lq );
		$this->sql_query->execute( $params );
	}
}