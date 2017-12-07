<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * clients groups.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

class GroupActions
{

	var $group = '';

	function __construct() {
		global $dbh;
		$this->dbh = $dbh;
	}

	/**
	 * Validate the information from the form.
	 */
	function validate_group($arguments)
	{
		require(ROOT_DIR.'/includes/vars.php');

		global $valid_me;
		$this->state = array();

		$this->id = $arguments['id'];
		$this->name = $arguments['name'];

		/**
		 * These validations are done both when creating a new group and
		 * when editing an existing one.
		 */
		$valid_me->validate('completed',$this->name,$validation_no_name);

		if ($valid_me->return_val) {
			return 1;
		}
		else {
			return 0;
		}
	}

	/**
	 * Create a new group.
	 */
	function create_group($arguments)
	{
		
		$this->state = array();

		/** Define the group information */
		$this->name = $arguments['name'];
		$this->description = $arguments['description'];
		$this->organization_type = $arguments['organization_type'];
		$this->members = $arguments['members'];
		$this->timestamp = time();

		/** Who is creating the group? */
		$this->this_admin = get_current_user_username();

		$this->sql_query = $this->dbh->prepare("INSERT INTO " . TABLE_GROUPS . " (name,description,organization_type,created_by)"
												." VALUES (:name, :description, :organization_type,:this_admin)");
		$this->sql_query->bindParam(':name', $this->name);
		$this->sql_query->bindParam(':description', $this->description);
		$this->sql_query->bindParam(':organization_type', $this->organization_type);
		$this->sql_query->bindParam(':this_admin', $this->this_admin);
		$this->sql_query->execute();


		$this->id = $this->dbh->lastInsertId();
		$this->state['new_id'] = $this->id;

		/** Create the members records */
		foreach ($this->members as $this->member) {
			$this->sql_member = $this->dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id)"
													." VALUES (:admin, :member, :id)");
			$this->sql_member->bindParam(':admin', $this->this_admin);
			$this->sql_member->bindParam(':member', $this->member, PDO::PARAM_INT);
			$this->sql_member->bindParam(':id', $this->id, PDO::PARAM_INT);
			$this->sql_member->execute();
		}

		if ($this->sql_query) {
			$this->state['query'] = 1;
		}
		else {
			$this->state['query'] = 0;
		}
		
		return $this->state;
	}

	/**
	 * Edit an existing group.
	 */
	function edit_group($arguments)
	{
		$this->state = array();

		/** Define the group information */
		$this->id = $arguments['id'];
		$this->name = $arguments['name'];
		$this->description = $arguments['description'];
		$this->members = $arguments['members'];
		$this->timestamp = time();

		/** Who is adding the members to the group? */
		$this->this_admin = get_current_user_username();

		/** SQL query */
		$this->sql_query = $this->dbh->prepare( "UPDATE " . TABLE_GROUPS . " SET name = :name, description = :description WHERE id = :id" );
		$this->sql_query->bindParam(':name', $this->name);
		$this->sql_query->bindParam(':description', $this->description);
		$this->sql_query->bindParam(':id', $this->id, PDO::PARAM_INT);
		$this->sql_query->execute();

		/** Clean the memmbers table */
		$this->sql_clean = $this->dbh->prepare("DELETE FROM " . TABLE_MEMBERS . " WHERE group_id = :id");
		$this->sql_clean->bindParam(':id', $this->id, PDO::PARAM_INT);
		$this->sql_clean->execute();
		
		/** Create the members records */
		if (!empty($this->members)) {
			foreach ($this->members as $this->member) {
				$this->sql_member = $this->dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id)"
														." VALUES (:admin, :member, :id)");
				$this->sql_member->bindParam(':admin', $this->this_admin);
				$this->sql_member->bindParam(':member', $this->member, PDO::PARAM_INT);
				$this->sql_member->bindParam(':id', $this->id, PDO::PARAM_INT);
				$this->sql_member->execute();
			}
		}

		if ($this->sql_query) {
			$this->state['query'] = 1;
		}
		else {
			$this->state['query'] = 0;
		}
		
		return $this->state;
	}

	/**
	 * Delete an existing group.
	 */
	function delete_group($group)
	{
		$this->check_level = array(9,8);
		if (isset($group)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $this->dbh->prepare('DELETE FROM ' . TABLE_GROUPS . ' WHERE id=:id');
				$this->sql->bindParam(':id', $group, PDO::PARAM_INT);
				$this->sql->execute();
			}
		}
	}

}

?>