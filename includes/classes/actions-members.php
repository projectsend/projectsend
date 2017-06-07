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
		$this->group_ids	= is_array( $arguments['group_ids'] ) ? $arguments['group_ids'] : array( $arguments['group_ids'] );;
		$this->added_by		= $arguments['added_by'];
		
		if ( in_array( CURRENT_USER_LEVEL, array(9,8) ) ) {
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
		$this->group_ids	= is_array( $arguments['group_ids'] ) ? $arguments['group_ids'] : array( $arguments['group_ids'] );;
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
	
	function group_request_membership($arguments)
	{
	}

	function group_approve_membership($arguments)
	{
	}

	function group_deny_membership($arguments)
	{
	}
}

?>