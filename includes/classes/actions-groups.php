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
		$this->members = $arguments['members'];
		$this->ispublic = $arguments['public'];
		$this->public_token		= generateRandomString(32);
		$this->timestamp = time();

		/** Who is creating the group? */
		$this->this_admin = get_current_user_username();

		$this->sql_query = $this->dbh->prepare("INSERT INTO " . TABLE_GROUPS . " (name, description, public, public_token, created_by)"
												." VALUES (:name, :description, :public, :token, :this_admin)");
		$this->sql_query->bindParam(':name', $this->name);
		$this->sql_query->bindParam(':description', $this->description);
		$this->sql_query->bindParam(':public', $this->ispublic, PDO::PARAM_INT);
		$this->sql_query->bindParam(':this_admin', $this->this_admin);
		$this->sql_query->bindParam(':token', $this->public_token);
		$this->sql_query->execute();


		$this->id = $this->dbh->lastInsertId();
		$this->state['new_id'] = $this->id;
		$this->state['public_token'] = $this->public_token;

		/** Create the members records */
		if ( !empty( $this->members ) ) {
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
		$this->ispublic = $arguments['public'];
		$this->timestamp = time();

		/** Who is adding the members to the group? */
		$this->this_admin = get_current_user_username();

		/** SQL query */
		$this->sql_query = $this->dbh->prepare( "UPDATE " . TABLE_GROUPS . " SET name = :name, description = :description, public = :public WHERE id = :id" );
		$this->sql_query->bindParam(':name', $this->name);
		$this->sql_query->bindParam(':description', $this->description);
		$this->sql_query->bindParam(':public', $this->ispublic, PDO::PARAM_INT);
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

	/**
	 * Return an array of existing groups
	 */
	function get_groups($arguments)
	{
		$this->group_ids	= !empty( $arguments['group_ids'] ) ? $arguments['group_ids'] : array();
		$this->group_ids	= is_array( $this->group_ids ) ? $this->group_ids : array( $this->group_ids );
		$this->is_public	= !empty( $arguments['public'] ) ? $arguments['public'] : '';
		$this->created_by	= !empty( $arguments['created_by'] ) ? $arguments['created_by'] : '';
		$this->search		= !empty( $arguments['search'] ) ? $arguments['search'] : '';

		$this->groups = array();
		$this->query = "SELECT * FROM " . TABLE_GROUPS;

		$this->parameters = array();
		if ( !empty( $this->group_ids ) ) {
			$this->parameters[] = "FIND_IN_SET(id, :ids)";
		}
		if ( !empty( $this->is_public ) ) {
			$this->parameters[] = "public=:public";
		}
		if ( !empty( $this->created_by ) ) {
			$this->parameters[] = "created_by=:created_by";
		}
		if ( !empty( $this->search ) ) {
			$this->parameters[] = "(name LIKE :name OR description LIKE :description)";
		}
		
		if ( !empty( $this->parameters ) ) {
			$this->p = 1;
			foreach ( $this->parameters as $this->parameter ) {
				if ( $this->p == 1 ) {
					$this->connector = " WHERE ";
				}
				else {
					$this->connector = " AND ";
				}
				$this->p++;
				
				$this->query .= $this->connector . $this->parameter;
			}
		}

		$this->statement = $this->dbh->prepare($this->query);

		if ( !empty( $this->group_ids ) ) {
			$this->group_ids = implode( ',', $this->group_ids );
			$this->statement->bindParam(':ids', $this->group_ids);
		}
		if ( !empty( $this->is_public ) ) {
			switch ( $this->is_public ) {
				case 'true':
					$this->pub = 1;
					break;
				case 'false':
					$this->pub = 0;
					break;
			}
			$this->statement->bindValue(':public', $this->pub, PDO::PARAM_INT);
		}
		if ( !empty( $this->created_by ) ) {
			$this->statement->bindParam(':created_by', $this->created_by);
		}
		if ( !empty( $this->search ) ) {
			$this->search_value = '%' . $this->search . '%';
			$this->statement->bindValue(':name', $this->search_value);
			$this->statement->bindValue(':description', $this->search_value);
		}
		
		$this->statement->execute();
		$this->statement->setFetchMode(PDO::FETCH_ASSOC);
		while( $this->data_group = $this->statement->fetch() ) {
			$this->all_groups[$this->data_group['id']] = array(
										'id'			=> $this->data_group['id'],
										'name'			=> $this->data_group['name'],
										'description'	=> $this->data_group['description'],
										'created_by'	=> $this->data_group['created_by'],
										'public'		=> $this->data_group['public'],
									);
		}
		
		if ( !empty($this->all_groups) > 0 ) {		
			return $this->all_groups;
		}
		else {
			return false;
		}
	}
}
