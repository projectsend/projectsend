<?php
namespace ProjectSend\Classes;

use \PDO;

class Account
{
    public $dbh;

    function __construct(PDO $pdo)
    {
        $this->dbh = $pdo;
    }

    /**
     * Check if a user id exists on the database.
     * Used on the Edit user page.
     *
     * @return bool
     */
    function user_exists_id($id)
    {
        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE id=:id");
        $statement->bindParam(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        if ( $statement->rowCount() > 0 ) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Get a user using any of the accepted field names
     * 
     * @uses get_user_by_id
     * @return array
     */
    function get_user_by($user_type, $field, $value)
    {
        $field = (string)$field;
        $field = trim( strip_Tags( htmlentities( strtolower( $field ) ) ) );
        $acceptable_fields = [
            'username',
            'name',
            'email',
        ];

        if ( in_array( $field, $acceptable_fields ) ) {
            $statement = $this->dbh->prepare("SELECT id FROM " . TABLE_USERS . " WHERE `$field`=:value");
            $statement->bindParam(':value', $value);
            $statement->execute();
            
            $result = $statement->fetchColumn();
            if ( $result ) {
                switch ( $user_type ) {
                    case 'user':
                        $user_data = get_user_by_id($result);
                        break;
                    case 'client':
                        $user_data = get_client_by_id($result);
                }

                return $user_data;
            }
            else {
                return false;
            }
        }
        else {
            return false;
        }
    }

    /**
     * Get all the user information knowing only the id
     *
     * @return array
     */
    function get_user_by_id($id)
    {
        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE id=:id");
        $statement->bindParam(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        while ( $row = $statement->fetch() ) {
            $information = array(
                                'id'			=> html_output($row['id']),
                                'username'		=> html_output($row['username']),
                                'name'			=> html_output($row['name']),
                                'email'			=> html_output($row['email']),
                                'level'			=> html_output($row['level']),
                                'active'		=> html_output($row['active']),
                                'max_file_size'	=> html_output($row['max_file_size']),
                                'created_date'	=> html_output($row['timestamp']),
                            );
            if ( !empty( $information ) ) {
                return $information;
            }
            else {
                return false;
            }
        }
    }

    /**
     * Get all the user information knowing only the log in username
     *
     * @return array
     * @uses get_user_by_id
     */
    function get_user_by_username($user)
    {
        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE username=:user");
        $statement->execute(
                            array(
                                ':user'	=> $user
                            )
                        );
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        if ( $statement->rowCount() > 0 ) {
            while ( $row = $statement->fetch() ) {
                $found_id = html_output($row['id']);
                if ( !empty( $found_id ) ) {
                    $information = get_user_by_id($found_id);
                    return $information;
                }
                else {
                    return false;
                }
            }
        }
        else {
            return false;
        }
    }

    // CLIENTS functions

    /**
     * Check if a client id exists on the database.
     * Used on the Edit client page.
     *
     * @return bool
     */
    function client_exists_id($id)
    {
        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE id=:id");
        $statement->bindParam(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        if ( $statement->rowCount() > 0 ) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Get all the client information knowing only the id
     * Used on the Manage files page.
     *
     * @return array
     */
    function get_client_by_id($client)
    {
        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE id=:id");
        $statement->bindParam(':id', $client, PDO::PARAM_INT);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        if ( $statement->rowCount() > 0 ) {
            while ( $row = $statement->fetch() ) {
                $information = array(
                                    'id'				=> html_output($row['id']),
                                    'username'			=> html_output($row['username']),
                                    'name'				=> html_output($row['name']),
                                    'address'			=> html_output($row['address']),
                                    'phone'				=> html_output($row['phone']),
                                    'email'				=> html_output($row['email']),
                                    'notify_upload'		=> html_output($row['notify']),
                                    'level'				=> html_output($row['level']),
                                    'active'			=> html_output($row['active']),
                                    'max_file_size' 	=> html_output($row['max_file_size']),
                                    'contact'			=> html_output($row['contact']),
                                    'created_date'		=> html_output($row['timestamp']),
                                    'created_by'		=> html_output($row['created_by'])
                                );
                if ( !empty( $information ) ) {
                    return $information;
                }
                else {
                    return false;
                }
            }
        }
        else {
            return false;
        }
    }


    /**
     * Get all the client information knowing only the log in username
     *
     * @return array
     */
    function get_client_by_username($client)
    {
        $statement = $this->dbh->prepare("SELECT id FROM " . TABLE_USERS . " WHERE username=:username");
        $statement->bindParam(':username', $client);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        while ( $row = $statement->fetch() ) {
            $found_id = html_output($row['id']);
            if ( !empty( $found_id ) ) {
                $information = get_client_by_id($found_id);
                return $information;
            }
            else {
                return false;
            }
        }
    }

    /**
     * Used on the file uploading process to determine if the client
     * needs to be notified by e-mail.
     */
    function check_if_notify_client($client)
    {
        $statement = $this->dbh->prepare("SELECT notify, email FROM " . TABLE_USERS . " WHERE username=:user");
        $statement->execute(
                            array(
                                ':user'	=> $client
                            )
                        );
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        while ( $row = $statement->fetch() ) {
            if ( $row['notify'] == '1' ) {
                return html_output($row['email']);
            }
            else {
                return false;
            }
        }
    }
}