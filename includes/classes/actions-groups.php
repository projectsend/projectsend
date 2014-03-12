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
		global $database;
		$this->state = array();

		/** Define the group information */
		$this->name = $arguments['name'];
		$this->description = $arguments['description'];
		$this->members = $arguments['members'];
		$this->timestamp = time();

		/** Who is creating the group? */
		$this->this_admin = get_current_user_username();

		$this->sql_query = $database->query("INSERT INTO tbl_groups (name,description,created_by)"
											."VALUES ('$this->name', '$this->description','$this->this_admin')");

		$this->id = mysql_insert_id();
		$this->state['new_id'] = $this->id;

		/** Create the members records */
		foreach ($this->members as $this->member) {
			$this->sql_member = $database->query("INSERT INTO tbl_members (added_by,client_id,group_id)"
											."VALUES ('$this->this_admin', '$this->member', '$this->id' )");
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
		global $database;
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
		$this->edit_group_query = "UPDATE tbl_groups SET name = '$this->name', description = '$this->description' WHERE id = $this->id";
		$this->sql_query = $database->query($this->edit_group_query);

		/** Clean the memmbers table */
		$this->sql_clean = $database->query("DELETE FROM tbl_members WHERE group_id = '$this->id'");
		
		/** Create the members records */
		if (!empty($this->members)) {
			foreach ($this->members as $this->member) {
				$this->sql_member = $database->query("INSERT INTO tbl_members (added_by,client_id,group_id)"
												."VALUES ('$this->this_admin', '$this->member', '$this->id' )");
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
		global $database;
		$this->check_level = array(9,8);
		if (isset($group)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $database->query('DELETE FROM tbl_groups WHERE id="' . $group .'"');
			}
		}
	}

}

?>