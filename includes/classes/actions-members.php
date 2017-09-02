<?php
/**
 * Class that handles all the actions and functions regarding groups memberships.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

class MembersActions
{

	var $client	= '';
	var $groups	= '';

	function __construct() {
		global $dbh;
		$this->dbh = $dbh;
	}
	
	function group_add_members($arguments)
	{
		$this->client_ids	= is_array( $arguments['client_id'] ) ? $arguments['client_id'] : array( $arguments['client_id'] );
		$this->group_id		= $arguments['group_id'];
		$this->added_by		= $arguments['added_by'];

		$this->results 		= array(
									'added'		=> 0,
									'queue'		=> count( $this->client_ids ),
									'errors'	=> array(),
								);

		foreach ( $this->client_ids as $this->client_id ) {
			$statemente = $this->dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id)"
												." VALUES (:admin, :id, :group)");
			$statemente->bindParam(':admin', $this->added_by);
			$statemente->bindParam(':id', $this->client_id, PDO::PARAM_INT);
			$statemente->bindParam(':group', $this->group_id, PDO::PARAM_INT);
			$this->status = $statemente->execute();
			
			if ( $this->status ) {
				$this->results['added']++;
			}
			else {
				$this->results['errors'][] = array(
													'client'	=> $this->client_id,
												);
			}
		}
		
		return $this->results;
	}

	function group_remove_members($arguments)
	{
		$this->client_ids	= is_array( $arguments['client_id'] ) ? $arguments['client_id'] : array( $arguments['client_id'] );
		$this->group_id		= $arguments['group_id'];

		$this->results 		= array(
									'removed'	=> 0,
									'queue'		=> count( $this->client_ids ),
									'errors'	=> array(),
								);

		foreach ( $this->client_ids as $this->client_id ) {
			$statemente = $this->dbh->prepare("DELETE FROM " . TABLE_MEMBERS . " WHERE client_id = :client AND group_id = :group");
			$statemente->bindParam(':client', $this->client_id, PDO::PARAM_INT);
			$statemente->bindParam(':group_id', $this->group_id, PDO::PARAM_INT);
			$this->status = $statemente->execute();
			
			if ( $this->status ) {
				$this->results['removed']++;
			}
			else {
				$this->results['errors'][] = array(
													'client'	=> $this->client_id,
												);
			}
		}
		
		return $this->results;
	}

	function client_get_groups($arguments)
	{
		$this->client_id	= $arguments['client_id'];
		$this->return_type	= !empty( $arguments['return'] ) ? $arguments['return'] : 'array';

		$this->found_groups = array();
		$this->sql_groups = $this->dbh->prepare("SELECT DISTINCT group_id FROM " . TABLE_MEMBERS . " WHERE client_id=:id");
		$this->sql_groups->bindParam(':id', $this->client_id, PDO::PARAM_INT);
		$this->sql_groups->execute();
		$this->count_groups = $this->sql_groups->rowCount();
	
		if ($this->count_groups > 0) {
			$this->sql_groups->setFetchMode(PDO::FETCH_ASSOC);
			while ( $this->row_groups = $this->sql_groups->fetch() ) {
				$this->found_groups[] = $this->row_groups["group_id"];
			}
		}
		
		switch ( $this->return_type ) {
			case 'array':
					$this->results = $this->found_groups;
				break;
			case 'list':
					$this->results = implode(',', $this->found_groups);
				break;
		}
		
		return $this->results;
	}

	function client_add_to_groups($arguments)
	{
		$this->client_id	= $arguments['client_id'];
		$this->group_ids	= is_array( $arguments['group_ids'] ) ? $arguments['group_ids'] : array( $arguments['group_ids'] );
		$this->added_by		= $arguments['added_by'];
		
		if ( in_array( CURRENT_USER_LEVEL, array(9,8) ) || ( defined('AUTOGROUP') ) ) {
			$this->results 		= array(
										'added'		=> 0,
										'queue'		=> count( $this->group_ids ),
										'errors'	=> array(),
									);
	
			foreach ( $this->group_ids as $this->group_id ) {
				$statemente = $this->dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id)"
													." VALUES (:admin, :id, :group)");
				$statemente->bindParam(':admin', $this->added_by);
				$statemente->bindParam(':id', $this->client_id, PDO::PARAM_INT);
				$statemente->bindParam(':group', $this->group_id, PDO::PARAM_INT);
				$this->status = $statemente->execute();
				
				if ( $this->status ) {
					$this->results['added']++;
				}
				else {
					$this->results['errors'][] = array(
														'group'	=> $this->group_id,
													);
				}
			}
			
			return $this->results;
		}
	}

	function client_edit_groups($arguments)
	{
		$this->client_id	= $arguments['client_id'];
		$this->group_ids	= is_array( $arguments['group_ids'] ) ? $arguments['group_ids'] : array( $arguments['group_ids'] );
		$this->added_by		= $arguments['added_by'];

		if ( in_array( CURRENT_USER_LEVEL, array(9,8) ) ) {
			$this->results 		= array(
										'added'		=> 0,
										'queue'		=> count( $this->group_ids ),
										'errors'	=> array(),
									);

			$this->found_groups = array();
			$this->sql_groups = $this->dbh->prepare("SELECT DISTINCT group_id FROM " . TABLE_MEMBERS . " WHERE client_id=:id");
			$this->sql_groups->bindParam(':id', $this->client_id, PDO::PARAM_INT);
			$this->sql_groups->execute();
			$this->count_groups = $this->sql_groups->rowCount();
		
			if ($this->count_groups > 0) {
				$this->sql_groups->setFetchMode(PDO::FETCH_ASSOC);
				while ( $this->row_groups = $this->sql_groups->fetch() ) {
					$this->found_groups[] = $this->row_groups["group_id"];
				}
			}
			
			/**
			 * 1- Make an array of groups where the client is actually a member,
			 * but they are not on the array of selected groups.
			 */
			$this->remove_groups = array_diff($this->found_groups, $this->group_ids);

			if ( !empty( $this->remove_groups) ) {
				$this->delete_ids = implode( ',', $this->remove_groups );
				$this->statement = $this->dbh->prepare("DELETE FROM " . TABLE_MEMBERS . " WHERE client_id=:client_id AND FIND_IN_SET(group_id, :delete)");
				$this->statement->bindParam(':client_id', $this->client_id, PDO::PARAM_INT);
				$this->statement->bindParam(':delete', $this->delete_ids);
				$this->statement->execute();
			}
			 
			/**
			 * 2- Make an array of groups in which the client is not a current member.
			 */
			$this->new_groups = array_diff($this->group_ids, $this->found_groups);
			if ( !empty( $this->new_groups) ) {
				$this->new_groups_add	= new MembersActions;
				$this->add_arguments	= array(
												'client_id'	=> $this->client_id,
												'group_ids'	=> $this->new_groups,
												'added_by'	=> CURRENT_USER_USERNAME,
											);
				$this->results['new']	= $this->new_groups_add->client_add_to_groups($this->add_arguments);
			}
	
			return $this->results;
		}
	}

	function get_membership_requests($arguments = '')
	{
		$this->client_id		= !empty( $arguments['client_id'] ) ? $arguments['client_id'] : '';
		$this->denied			= !empty( $arguments['denied'] ) ? $arguments['denied'] : 0;
		$this->requests_query	= "SELECT * FROM " . TABLE_MEMBERS_REQUESTS . " WHERE denied=:denied";
		if ( !empty( $this->client_id ) ) {
			$this->requests_query	.= " AND client_id=:client_id";
		}
		$this->requests			= $this->dbh->prepare( $this->requests_query );
		$this->requests->bindParam(':denied', $this->denied, PDO::PARAM_INT);
		if ( !empty( $this->client_id ) ) {
			$this->requests->bindParam(':client_id', $this->client_id, PDO::PARAM_INT);
		}
		$this->requests->execute();
		$this->requests_count = $this->requests->rowCount();
		$this->results = array(
								'requests'	=> array(),
							);
		
		if ( $this->requests_count > 0 ) {
			$this->groups		= new GroupActions;
			$this->arguments	= array();

			$this->arguments = array();
			$this->get_groups = $this->groups->get_groups($this->arguments);

			while ( $this->row = $this->requests->fetch() ) {
				$this->results[$this->row['client_id']]['requests'][] = array(
																			'id'	=> $this->row['group_id'],
																			'name'	=> $this->get_groups[$this->row['group_id']]['name'],
																		);
				$this->results[$this->row['client_id']]['group_ids'][] = $this->row['group_id'];
			}
			
			if ( !empty( $this->client_id ) ) {
				$this->results['client_id'] = $this->client_id;
			}
			return $this->results;
		}
		else {
			return false;
		}
	}
	
	function group_request_membership($arguments)
	{
		if ( in_array( CURRENT_USER_LEVEL, array(9,8) ) || ( defined('REGISTERING') ) || ( defined('EDITING_SELF_ACCOUNT') ) ) {
			if ( CLIENTS_CAN_SELECT_GROUP == 'public' || CLIENTS_CAN_SELECT_GROUP == 'all' ) {
				$this->client_id	= $arguments['client_id'];
				$this->group_ids	= is_array( $arguments['group_ids'] ) ? $arguments['group_ids'] : array( $arguments['group_ids'] );
				$this->request_by	= $arguments['request_by'];

				/** Make a list of current groups to ignore new requests to them */
				$this->current_groups = self::client_get_groups(
															array(
																'client_id' => $this->client_id
															)
														);

				if ( !empty( $this->current_groups ) ) {
					foreach ( $this->group_ids as $this->array_key => $this->group_id ) {
						if ( in_array( $this->group_id, $this->current_groups ) ) {
							unset($this->group_ids[$this->array_key]);
						}
					}
				}

				/** Make a list of current requests to avoid duplicates */
				$this->current_requests = self::get_membership_requests(
															array(
																'client_id' => $this->client_id
															)
														);

				if ( !empty( $this->current_requests ) ) {
					foreach ( $this->group_ids as $this->array_key => $this->group_id ) {
						if ( in_array( $this->group_id, $this->current_requests[$this->client_id]['group_ids'] ) ) {
							unset($this->group_ids[$this->array_key]);
						}
					}
				}
	
				if ( CLIENTS_CAN_SELECT_GROUP == 'public' ) {
					/**
					 * Make a list of public groups in case clients can only request
					 * membership to those
					 */
					$this->memberships	= new GroupActions;
					$this->arguments = array(
											'public'	=> true,
										);
					$this->public_groups = $this->memberships->get_groups($this->arguments);
				}
				
				$this->results 		= array(
											'added'		=> 0,
											'queue'		=> count( $this->group_ids ),
											'errors'	=> array(),
										);
		
				if ( !empty( $this->group_ids ) ) {
					$this->requests = array();
					foreach ( $this->group_ids as $this->group_id ) {
						if ( defined('REGISTERING') ) {
							if ( CLIENTS_CAN_SELECT_GROUP == 'public' ) {
								$this->permitted = array();
								foreach ( $this->public_groups as $this->public_group ) {
									$this->permitted[] = $this->public_group['id'];
								}
								
								if ( !in_array( $this->group_id, $this->permitted ) ) {
									continue;
								}
							}
						}
	
						$statemente = $this->dbh->prepare("INSERT INTO " . TABLE_MEMBERS_REQUESTS . " (requested_by,client_id,group_id)"
															." VALUES (:username, :id, :group)");
						$statemente->bindParam(':username', $this->request_by);
						$statemente->bindParam(':id', $this->client_id, PDO::PARAM_INT);
						$statemente->bindParam(':group', $this->group_id, PDO::PARAM_INT);
						$this->status = $statemente->execute();
						
						if ( $this->status ) {
							$this->results['added']++;
							$this->requests[] = $this->group_id;
						}
						else {
							$this->results['errors'][] = array(
																'client'	=> $this->group_id,
															);
						}
	
						$this->results['requests'] = $this->requests;
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
		return $this->results;
	}

	/**
	 * Approve and deny group memberships requests
	 */
	function group_process_memberships($arguments)
	{
		$this->client_id	= $arguments['client_id'];
		$this->approve		= !empty( $arguments['approve'] ) ? $arguments['approve'] : '';
		$this->deny_all		= !empty( $arguments['deny_all'] ) ? $arguments['deny_all'] : '';
		
		$this->get_requests_arguments = array(
												'client_id'	=> $this->client_id,
											);
		$this->get_requests	= $this->get_membership_requests( $this->get_requests_arguments );
		$this->got_requests = $this->get_requests[$this->client_id]['group_ids'];

		$this->return_info	= array(
									'memberships'	=> array(
															'approved'	=> array(),
															'denied'	=> array(),
														),
								);
		
		/** Deny all */
		if ( !empty( $this->deny_all ) && $this->deny_all == true ) {
			$this->sql = $this->dbh->prepare('UPDATE ' . TABLE_MEMBERS_REQUESTS . ' SET denied=:denied WHERE client_id=:client_id');
			$this->sql->bindValue(':denied', 1, PDO::PARAM_INT);
			$this->sql->bindValue(':client_id', $this->client_id, PDO::PARAM_INT);
			$this->status = $this->sql->execute();
		}

		/** Process individual requests */
		if ( !empty( $this->approve ) ) {
			foreach ( $this->got_requests as $this->request ) {
				/**
				 * Process request
				 */
				$this->requests_to_remove = array();
				if ( in_array( $this->request, $this->approve ) ) {
					/** Insert into memberships */
					$statemente = $this->dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id)"
														." VALUES (:added_by, :client_id, :group_id)");
					$statemente->bindValue(':added_by', 'SELFREGISTERED');
					$statemente->bindValue(':client_id', $this->client_id, PDO::PARAM_INT);
					$statemente->bindValue(':group_id', $this->request, PDO::PARAM_INT);
					$statemente->execute();
					/** Add to delete from requests array */
					$this->requests_to_remove[] = $this->request;
					$this->return_info['memberships']['approved'][] = $this->request;
				}
				else {
					/** Mark as denied */
					$this->sql = $this->dbh->prepare('UPDATE ' . TABLE_MEMBERS_REQUESTS . ' SET denied=:denied WHERE client_id=:client_id AND group_id=:group_id');
					$this->sql->bindValue(':denied', 1, PDO::PARAM_INT);
					$this->sql->bindValue(':client_id', $this->client_id, PDO::PARAM_INT);
					$this->sql->bindValue(':group_id', $this->request, PDO::PARAM_INT);
					$this->status = $this->sql->execute();
					$this->return_info['memberships']['denied'][] = $this->request;
				}
			}
			
			if ( !empty( $this->requests_to_remove ) ) {
				$this->delete_ids = implode( ',', $this->requests_to_remove );
				$this->statement = $this->dbh->prepare("DELETE FROM " . TABLE_MEMBERS_REQUESTS . " WHERE client_id=:client_id AND FIND_IN_SET(group_id, :delete)");
				$this->statement->bindParam(':client_id', $this->client_id, PDO::PARAM_INT);
				$this->statement->bindParam(':delete', $this->delete_ids);
				$this->statement->execute();
			}
		}
		
		return $this->return_info;
	}


	/**
	 * Delete memberships requests
	 */
	function group_delete_requests($arguments)
	{
		$this->client_id	= $arguments['client_id'];
		$this->type			= ( !empty( $arguments['type'] ) && $arguments['type'] == 'denied' ) ? 1 : 0;

		if ( !empty( $this->client_id ) ) {
			$this->statement = $this->dbh->prepare("DELETE FROM " . TABLE_MEMBERS_REQUESTS . " WHERE client_id=:client_id AND denied=:denied");
			$this->statement->bindParam(':client_id', $this->client_id, PDO::PARAM_INT);
			$this->statement->bindParam(':denied', $this->type, PDO::PARAM_INT);
			$this->statement->execute();
		}
	}


	/**
	 * Takes a submitted memberships array. Adds new ones and removes
	 * those that are in the database but not in the new request.
	 */
	function update_membership_requests($arguments)
	{
		$this->client_id	= $arguments['client_id'];
		$this->group_ids	= is_array( $arguments['group_ids'] ) ? $arguments['group_ids'] : array( $arguments['group_ids'] );
		$this->request_by	= $arguments['request_by'];


		if ( !empty( $this->client_id ) ) {

			$this->get_requests_arguments = array(
													'client_id'	=> $this->client_id,
												);
			$this->get_requests	= self::get_membership_requests( $this->get_requests_arguments );
			$this->on_database = $this->get_requests[$this->client_id]['group_ids'];



			/**
			 * On database but not on array:
			 * delete it from requests table
			 */
			$this->remove = array();
			if ( !empty( $this->on_database ) ) {
				foreach ( $this->on_database as $this->key => $this->group_id ) {
					if ( !in_array( $this->group_id, $this->group_ids ) ) {
						$this->remove[] = $this->group_id;
					}
				}
				if ( !empty( $this->remove ) ) {
					$this->delete_ids = implode( ',', $this->remove );
					echo $this->delete_ids;
					$this->statement = $this->dbh->prepare("DELETE FROM " . TABLE_MEMBERS_REQUESTS . " WHERE client_id=:client_id AND FIND_IN_SET(group_id, :remove)");
					$this->statement->bindParam(':client_id', $this->client_id, PDO::PARAM_INT);
					$this->statement->bindParam(':remove', $this->delete_ids);
					$this->statement->execute();
				}
			}

			/**
			 * On array but not on database:
			 * add the request
			 */
			$this->add = array();
			if ( !empty( $this->group_ids ) ) {
				foreach ( $this->group_ids as $this->key => $this->group_id ) {
					if ( !in_array( $this->group_id, $this->on_database ) ) {
						$this->add[] = $this->group_id;
					}
				}
				if ( !empty( $this->add ) ) {
					$this->add_arguments = array(
												'client_id'		=> $this->client_id,
												'group_ids'		=> $this->add,
												'request_by'	=> $this->request_by,
											);
					$this->process_add = self::group_request_membership( $this->add_arguments );
				}
			}

			/**
			 * TODO: Send email to admin informing of new requests
			 */
		}
	}

}