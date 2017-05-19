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
			$statemente = $dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id)"
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
			$statemente = $dbh->prepare("DELETE FROM " . TABLE_MEMBERS . " WHERE client_id = :client AND group_id = :group");
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