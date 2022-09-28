<?php
/**
 * Class that handles all the actions and functions regarding groups memberships.
 * @todo: this needs to be redone. It's a mix of loose functions that do not belong to an object ATM
 */

namespace ProjectSend\Classes;
use \PDO;

class GroupsMemberships
{
    private $dbh;
    private $logger;

    public function __construct()
    {
        global $dbh;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;
    }

    function groupAddMembers($arguments)
    {
        $client_ids	= is_array( $arguments['client_id'] ) ? $arguments['client_id'] : array( $arguments['client_id'] );
        $group_id = $arguments['group_id'];
        $added_by = $arguments['added_by'];

        $results = array(
            'added' => 0,
            'queue' => count( $client_ids ),
            'errors' => array(),
        );

        foreach ( $client_ids as $client_id ) {
            $statement = $this->dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id) VALUES (:admin, :id, :group)");
            $statement->bindParam(':admin', $added_by);
            $statement->bindParam(':id', $client_id, PDO::PARAM_INT);
            $statement->bindParam(':group', $group_id, PDO::PARAM_INT);
            $status = $statement->execute();
            
            if ( $status ) {
                $results['added']++;
            }
            else {
                $results['errors'][] = [
                    'client' => $client_id
                ];
            }
        }
        
        return $results;
    }

    function groupRemoveMembers($arguments)
    {
        $client_ids	= is_array( $arguments['client_id'] ) ? $arguments['client_id'] : array( $arguments['client_id'] );
        $group_id = $arguments['group_id'];

        $results = array(
            'removed' => 0,
            'queue' => count( $client_ids ),
            'errors' => array(),
        );

        foreach ( $client_ids as $client_id ) {
            $statement = $this->dbh->prepare("DELETE FROM " . TABLE_MEMBERS . " WHERE client_id = :client AND group_id = :group");
            $statement->bindParam(':client', $client_id, PDO::PARAM_INT);
            $statement->bindParam(':group_id', $group_id, PDO::PARAM_INT);
            $status = $statement->execute();
            
            if ( $status ) {
                $results['removed']++;
            }
            else {
                $results['errors'][] = [
                    'client' => $client_id
                ];
            }
        }
        
        return $results;
    }

    function getGroupsByClient($arguments)
    {
        $client_id = $arguments['client_id'];
        $return_type = !empty( $arguments['return'] ) ? $arguments['return'] : 'array';

        $found_groups = [];
        $statement = $this->dbh->prepare("SELECT DISTINCT group_id FROM " . TABLE_MEMBERS . " WHERE client_id=:id");
        $statement->bindParam(':id', $client_id, PDO::PARAM_INT);
        $statement->execute();
        $count_groups = $statement->rowCount();
    
        if ($count_groups > 0) {
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            while ( $row_groups = $statement->fetch() ) {
                $found_groups[] = $row_groups["group_id"];
            }
        }
        
        switch ( $return_type ) {
            case 'array':
                    $results = $found_groups;
                break;
            case 'list':
                    $results = implode(',', $found_groups);
                break;
        }
        
        return $results;
    }

    function clientAddToGroups($arguments)
    {
        $client_id = $arguments['client_id'];
        $group_ids = is_array( $arguments['group_ids'] ) ? $arguments['group_ids'] : array( $arguments['group_ids'] );
        $added_by = $arguments['added_by'];
        
        if ( defined('REGISTERING') or (defined('CURRENT_USER_LEVEL') && in_array( CURRENT_USER_LEVEL, array(9,8) )) ) {
            $results = [
                'added' => 0,
                'queue' => count( $group_ids ),
                'errors' => array(),
            ];
    
            foreach ( $group_ids as $group_id ) {
                $statement = $this->dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id) VALUES (:admin, :id, :group)");
                $statement->bindParam(':admin', $added_by);
                $statement->bindParam(':id', $client_id, PDO::PARAM_INT);
                $statement->bindParam(':group', $group_id, PDO::PARAM_INT);
                $status = $statement->execute();
                
                if ( $status ) {
                    $results['added']++;
                }
                else {
                    $results['errors'][] = [
                        'group'	=> $group_id
                    ];
                }
            }
            
            return $results;
        }
    }

    function clientEditGroups($arguments)
    {
        $client_id = $arguments['client_id'];
        $group_ids = is_array( $arguments['group_ids'] ) ? $arguments['group_ids'] : array( $arguments['group_ids'] );
        $added_by = $arguments['added_by'];

        if ( in_array( CURRENT_USER_LEVEL, array(9,8) ) ) {
            $results = [
                'added' => 0,
                'queue' => count( $group_ids ),
                'errors' => array(),
            ];

            $found_groups = [];
            $sql_groups = $this->dbh->prepare("SELECT DISTINCT group_id FROM " . TABLE_MEMBERS . " WHERE client_id=:id");
            $sql_groups->bindParam(':id', $client_id, PDO::PARAM_INT);
            $sql_groups->execute();
            $count_groups = $sql_groups->rowCount();
        
            if ($count_groups > 0) {
                $sql_groups->setFetchMode(PDO::FETCH_ASSOC);
                while ( $row_groups = $sql_groups->fetch() ) {
                    $found_groups[] = $row_groups["group_id"];
                }
            }
            
            /**
             * 1- Make an array of groups where the client is actually a member,
             * but they are not on the array of selected groups.
             */
            $remove_groups = array_diff($found_groups, $group_ids);

            if ( !empty( $remove_groups) ) {
                $delete_ids = implode( ',', $remove_groups );
                $statement = $this->dbh->prepare("DELETE FROM " . TABLE_MEMBERS . " WHERE client_id=:client_id AND FIND_IN_SET(group_id, :delete)");
                $statement->bindParam(':client_id', $client_id, PDO::PARAM_INT);
                $statement->bindParam(':delete', $delete_ids);
                $statement->execute();
            }

            /**
             * 2- Make an array of groups in which the client is not a current member.
             */
            $new_groups = array_diff($group_ids, $found_groups);
            if ( !empty( $new_groups) ) {
                $new_groups_add	= new \ProjectSend\Classes\GroupsMemberships;
                $results['new']	= $new_groups_add->clientAddToGroups([
                    'client_id' => $client_id,
                    'group_ids' => $new_groups,
                    'added_by' => CURRENT_USER_USERNAME,
                ]);
            }
    
            return $results;
        }
    }

    function getMembershipRequests($arguments = '')
    {
        $client_id = !empty( $arguments['client_id'] ) ? $arguments['client_id'] : '';
        $denied = !empty( $arguments['denied'] ) ? $arguments['denied'] : 0;
        $requests_query = "SELECT * FROM " . TABLE_MEMBERS_REQUESTS . " WHERE denied=:denied";
        if ( !empty( $client_id ) ) {
            $requests_query	.= " AND client_id=:client_id";
        }
        $requests = $this->dbh->prepare( $requests_query );
        $requests->bindParam(':denied', $denied, PDO::PARAM_INT);
        if ( !empty( $client_id ) ) {
            $requests->bindParam(':client_id', $client_id, PDO::PARAM_INT);
        }
        $requests->execute();
        $requests_count = $requests->rowCount();
        $results = [
            'requests' => array(),
        ];
        
        if ( $requests_count > 0 ) {
            $get_groups = get_groups([]);

            while ( $row = $requests->fetch() ) {
                $results[$row['client_id']]['requests'][] = array(
                    'id' => $row['group_id'],
                    'name' => $get_groups[$row['group_id']]['name'],
                );
                $results[$row['client_id']]['group_ids'][] = $row['group_id'];
            }
            
            if ( !empty( $client_id ) ) {
                $results['client_id'] = $client_id;
            }
            return $results;
        }
        else {
            return false;
        }
    }
    
    function groupRequestMembership($arguments)
    {
        if ( (defined('CURRENT_USER_LEVEL') && in_array( CURRENT_USER_LEVEL, array(9,8) )) || ( defined('REGISTERING') ) || ( defined('EDITING_SELF_ACCOUNT') ) ) {
            if (get_option('clients_can_select_group') == 'public' || get_option('clients_can_select_group') == 'all') {
                $client_id = $arguments['client_id'];
                $group_ids = is_array( $arguments['group_ids'] ) ? $arguments['group_ids'] : array( $arguments['group_ids'] );
                $request_by = $arguments['request_by'];

                /** Make a list of current groups to ignore new requests to them */
                $current_groups = $this->getGroupsByClient(
                    array(
                        'client_id' => $client_id
                    )
                );

                if ( !empty( $current_groups ) ) {
                    foreach ( $group_ids as $array_key => $group_id ) {
                        if ( in_array( $group_id, $current_groups ) ) {
                            unset($group_ids[$array_key]);
                        }
                    }
                }

                /** Make a list of current requests to avoid duplicates */
                $current_requests = $this->getMembershipRequests([
                    'client_id' => $client_id
                ]);

                if ( !empty( $current_requests ) ) {
                    foreach ( $group_ids as $array_key => $group_id ) {
                        if ( in_array( $group_id, $current_requests[$client_id]['group_ids'] ) ) {
                            unset($group_ids[$array_key]);
                        }
                    }
                }
    
                if ( get_option('clients_can_select_group') == 'public' ) {
                    /**
                     * Make a list of public groups in case clients can only request
                     * membership to those
                     */
                    $public_groups = get_groups([
                        'public' => true
                    ]);
                }
                
                $results = [
                    'added' => 0,
                    'queue' => count( $group_ids ),
                    'errors' => array(),
                ];
        
                if ( !empty( $group_ids ) ) {
                    $requests = [];
                    foreach ( $group_ids as $group_id ) {
                        if ( defined('REGISTERING') ) {
                            if ( get_option('clients_can_select_group') == 'public' ) {
                                $permitted = [];
                                foreach ( $public_groups as $public_group ) {
                                    $permitted[] = $public_group['id'];
                                }
                                
                                if ( !in_array( $group_id, $permitted ) ) {
                                    continue;
                                }
                            }
                        }
    
                        $statement = $this->dbh->prepare("INSERT INTO " . TABLE_MEMBERS_REQUESTS . " (requested_by,client_id,group_id)"
                                                            ." VALUES (:username, :id, :group)");
                        $statement->bindParam(':username', $request_by);
                        $statement->bindParam(':id', $client_id, PDO::PARAM_INT);
                        $statement->bindParam(':group', $group_id, PDO::PARAM_INT);
                        $status = $statement->execute();
                        
                        if ( $status ) {
                            $results['added']++;
                            $requests[] = $group_id;
                        }
                        else {
                            $results['errors'][] = [
                                'client' => $group_id,
                            ];
                        }
    
                        $results['requests'] = $requests;
                    }
                }
                else {
                    return false;
                }
            }
            else {
                return false;
            }
        }
        return $results;
    }

    /**
     * Approve and deny group memberships requests
     */
    function clientProcessMemberships($arguments, $email = false)
    {
        $client_id = $arguments['client_id'];
        $approve = !empty( $arguments['approve'] ) ? $arguments['approve'] : null;
        $deny_all = !empty( $arguments['deny_all'] ) ? $arguments['deny_all'] : null;
        
        $get_requests = $this->getMembershipRequests([
            'client_id'	=> $client_id,
        ]);

        if (empty($get_requests)) {
            return false;
        }

        $got_requests = $get_requests[$client_id]['group_ids'];
        $return_info = array(
            'memberships' => array(
                'approved' => [],
                'denied' => [],
            ),
        );
        
        /** Deny all */
        if ( !empty( $deny_all ) && $deny_all == true ) {
            $sql = $this->dbh->prepare('UPDATE ' . TABLE_MEMBERS_REQUESTS . ' SET denied=:denied WHERE client_id=:client_id');
            $sql->bindValue(':denied', 1, PDO::PARAM_INT);
            $sql->bindValue(':client_id', $client_id, PDO::PARAM_INT);
            $status = $sql->execute();
        }

        /** Process individual requests */
        foreach ( $got_requests as $request ) {
            /**
             * Process request
             */
            $requests_to_remove = [];
            if ( in_array( $request, $approve ) ) {
                /** Insert into memberships */
                $statement = $this->dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id)"
                                                    ." VALUES (:added_by, :client_id, :group_id)");
                $statement->bindValue(':client_id', $client_id, PDO::PARAM_INT);
                $statement->bindValue(':group_id', $request, PDO::PARAM_INT);
                $statement->execute();
                /** Add to delete from requests array */
                $requests_to_remove[] = $request;
                $return_info['memberships']['approved'][] = $request;
            }
            else {
                /** Mark as denied */
                $sql = $this->dbh->prepare('UPDATE ' . TABLE_MEMBERS_REQUESTS . ' SET denied=:denied WHERE client_id=:client_id AND group_id=:group_id');
                $sql->bindValue(':denied', 1, PDO::PARAM_INT);
                $sql->bindValue(':client_id', $client_id, PDO::PARAM_INT);
                $sql->bindValue(':group_id', $request, PDO::PARAM_INT);
                $status = $sql->execute();
                $return_info['memberships']['denied'][] = $request;
            }
        }
        
        if ( !empty( $requests_to_remove ) ) {
            $delete_ids = implode( ',', $requests_to_remove );
            $statement = $this->dbh->prepare("DELETE FROM " . TABLE_MEMBERS_REQUESTS . " WHERE client_id=:client_id AND FIND_IN_SET(group_id, :delete)");
            $statement->bindParam(':client_id', $client_id, PDO::PARAM_INT);
            $statement->bindParam(':delete', $delete_ids);
            $statement->execute();
        }
        
        // Add to the log
        $client = get_client_by_id($client_id);
        $this->logger->addEntry([
            'action' => 39,
            'owner_id' => CURRENT_USER_ID,
            'affected_account_name' => $client['name']
        ]);

        /** Send email */
        if ($email) {
            $notify_client = new \ProjectSend\Classes\Emails;
            $send = $notify_client->send([
                'type' => 'client_memberships_process',
                'username' => $client['username'],
                'name' => $client['name'],
                'address' => $client['email'],
                'memberships' => $return_info['memberships'],
            ]);
        }
        
        return $return_info;
    }


    /**
     * Delete memberships requests
     */
    function clientDeleteRequests($arguments)
    {
        $client_id = $arguments['client_id'];
        $type = ( !empty( $arguments['type'] ) && $arguments['type'] == 'denied' ) ? 1 : 0;

        if ( !empty( $client_id ) ) {
            $statement = $this->dbh->prepare("DELETE FROM " . TABLE_MEMBERS_REQUESTS . " WHERE client_id=:client_id AND denied=:denied");
            $statement->bindParam(':client_id', $client_id, PDO::PARAM_INT);
            $statement->bindParam(':denied', $type, PDO::PARAM_INT);
            $statement->execute();

            // Add to the log
            $client = get_client_by_id($client_id);
            $this->logger->addEntry([
                'action' => 39,
                'owner_id' => CURRENT_USER_ID,
                'affected_account_name' => $client['name']
            ]);
        }
    }

    /**
     * Takes a submitted memberships array. Adds new ones and removes
     * those that are in the database but not in the new request.
     */
    function updateMembershipRequests($arguments)
    {
        $client_id = $arguments['client_id'];
        $group_ids = is_array( $arguments['group_ids'] ) ? $arguments['group_ids'] : array( $arguments['group_ids'] );
        $request_by = $arguments['request_by'];

        if ( !empty( $client_id ) ) {
            $get_requests = $this->getMembershipRequests([
                'client_id' => $client_id,
            ]);
            $on_database = $get_requests[$client_id]['group_ids'];

            /**
             * On database but not on array:
             * delete it from requests table
             */
            $remove = [];
            if ( !empty( $on_database ) ) {
                foreach ( $on_database as $key => $group_id ) {
                    if ( !in_array( $group_id, $group_ids ) ) {
                        $remove[] = $group_id;
                    }
                }
                if ( !empty( $remove ) ) {
                    $delete_ids = implode( ',', $remove );
                    $statement = $this->dbh->prepare("DELETE FROM " . TABLE_MEMBERS_REQUESTS . " WHERE client_id=:client_id AND FIND_IN_SET(group_id, :remove)");
                    $statement->bindParam(':client_id', $client_id, PDO::PARAM_INT);
                    $statement->bindParam(':remove', $delete_ids);
                    $statement->execute();
                }
            }

            /**
             * On array but not on database:
             * add the request
             */
            $add = [];
            if ( !empty( $group_ids ) ) {
                foreach ( $group_ids as $key => $group_id ) {
                    if ( !in_array( $group_id, $on_database ) ) {
                        $add[] = $group_id;
                    }
                }
                if ( !empty( $add ) ) {
                    $process_add = $this->groupRequestMembership([
                        'client_id' => $client_id,
                        'group_ids' => $add,
                        'request_by' => $request_by,
                    ]);
                }
            }

            /**
             * Prepare and send an email to administrator(s) if there is at least one request
             */
            if ( !empty( $group_ids ) ) {
                $client_info = get_client_by_id($client_id);
                $notify_admin = new \ProjectSend\Classes\Emails;

                $send = $notify_admin->send([
                    'type' => 'client_edited',
                    'address' => get_option('admin_email_address'),
                    'username' => $client_info['username'],
                    'name' => $client_info['name'],
                    'memberships' => $group_ids
                ]);
            }
        }
    }
}
