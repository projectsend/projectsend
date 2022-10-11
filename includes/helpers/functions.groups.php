<?php
/**
 * Check if a group id exists on the database.
 * Used on the Edit group page.
 *
 * @return bool
 */
function group_exists_id($id)
{
    $dbh = get_dbh();
    $statement = $dbh->prepare("SELECT * FROM " . TABLE_GROUPS . " WHERE id=:id");
    $statement->bindParam(':id', $id, PDO::PARAM_INT);
    $statement->execute();
    if ($statement->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

/**
 * Get all the group information knowing only the id
 *
 * @return array
 */
function get_group_by_id($id)
{
    $dbh = get_dbh();
    $statement = $dbh->prepare("SELECT * FROM " . TABLE_GROUPS . " WHERE id=:id");
    $statement->bindParam(':id', $id, PDO::PARAM_INT);
    $statement->execute();
    $statement->setFetchMode(PDO::FETCH_ASSOC);

    while ($row = $statement->fetch()) {
        $information = array(
            'id' => html_output($row['id']),
            'created_by' => html_output($row['created_by']),
            'created_date' => html_output($row['timestamp']),
            'name' => html_output($row['name']),
            'description' => html_output($row['description']),
            'public' => html_output($row['public']),
            'public_token' => html_output($row['public_token']),
        );
        if (!empty($information)) {
            return $information;
        } else {
            return false;
        }
    }
}

/**
 * Return an array of existing groups
 * @todo add limit and order to the query
 * @todo use Group class on response
 */
function get_groups($arguments)
{
    $dbh = get_dbh();

    $group_ids = !empty($arguments['group_ids']) ? $arguments['group_ids'] : array();
    $group_ids = is_array($group_ids) ? $group_ids : array($group_ids);
    $is_public = !empty($arguments['public']) ? $arguments['public'] : '';
    $created_by = !empty($arguments['created_by']) ? $arguments['created_by'] : '';
    $search = !empty($arguments['search']) ? $arguments['search'] : '';

    $groups = [];
    $query = "SELECT * FROM " . TABLE_GROUPS;

    $parameters = [];
    if (!empty($group_ids)) {
        $parameters[] = "FIND_IN_SET(id, :ids)";
    }
    if (!empty($is_public)) {
        $parameters[] = "public=:public";
    }
    if (!empty($created_by)) {
        $parameters[] = "created_by=:created_by";
    }
    if (!empty($search)) {
        $parameters[] = "(name LIKE :name OR description LIKE :description)";
    }

    if (!empty($parameters)) {
        $p = 1;
        foreach ($parameters as $parameter) {
            if ($p == 1) {
                $connector = " WHERE ";
            } else {
                $connector = " AND ";
            }
            $p++;

            $query .= $connector . $parameter;
        }
    }

    $statement = $dbh->prepare($query);

    if (!empty($group_ids)) {
        $group_ids = implode(',', $group_ids);
        $statement->bindParam(':ids', $group_ids);
    }
    if (!empty($is_public)) {
        switch ($is_public) {
            case 'true':
                $pub = 1;
                break;
            case 'false':
                $pub = 0;
                break;
        }
        $statement->bindValue(':public', $pub, PDO::PARAM_INT);
    }
    if (!empty($created_by)) {
        $statement->bindParam(':created_by', $created_by);
    }
    if (!empty($search)) {
        $search_value = '%' . $search . '%';
        $statement->bindValue(':name', $search_value);
        $statement->bindValue(':description', $search_value);
    }

    $statement->execute();
    $statement->setFetchMode(PDO::FETCH_ASSOC);
    while ($data_group = $statement->fetch()) {
        $all_groups[$data_group['id']] = array(
            'id'=> $data_group['id'],
            'name'=> $data_group['name'],
            'description'=> $data_group['description'],
            'created_by'=> $data_group['created_by'],
            'public'=> $data_group['public'],
            'public_token' => $data_group['public_token'],
        );
    }

    if (!empty($all_groups) > 0) {
        return $all_groups;
    } else {
        return array();
    }
}

function can_view_public_group($group_id, $group_token)
{
    $can_view_group = false;

    $group = get_group_by_id($group_id);
    if ($group['public_token'] == $group_token) {
        if ($group['public'] == 1) {
            $can_view_group = true;
        }
    }

    return $can_view_group;
}

function get_group_assigned_files($group_id)
{
    $return = [];
    if (empty($group_id)) {
        return $return;
    }

    $dbh = get_dbh();

    $files_ids = [];
    $query = "SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE group_id = :group_id";
    $sql = $dbh->prepare($query);
    $sql->bindParam(':group_id', $group_id);
    $sql->execute();
    $sql->setFetchMode(PDO::FETCH_ASSOC);
    while ($row = $sql->fetch()) {
        $return[] = $row['file_id'];
    }

    return $return;
}

function get_groups_assigned_files($groups_ids = [])
{
    $return = [];
    if (empty($groups_ids)) {
        return $return;
    }

    $dbh = get_dbh();

    if (is_array($groups_ids)) {
        $groups_ids = implode(',', $groups_ids);
    }

    $query = "SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE FIND_IN_SET(group_id, :groups_ids)";
    $sql = $dbh->prepare($query);
    $sql->bindParam(':groups_ids', $groups_ids);
    $sql->execute();
    $sql->setFetchMode(PDO::FETCH_ASSOC);
    while ($row = $sql->fetch()) {
        $return[] = $row['file_id'];
    }

    return $return;
}

function count_public_files_in_group($group_id = null)
{
    if (!$group_id) {
        return 0;
    }

    $dbh = get_dbh();
    $query = "SELECT DISTINCT id FROM " . TABLE_FILES_RELATIONS . " WHERE group_id=:group_id";
    $sql = $dbh->prepare($query);
    $sql->bindParam(':group_id', $group_id);
    $sql->execute();
    $sql->setFetchMode(PDO::FETCH_ASSOC);
    return $sql->rowCount();
}

function count_public_files_not_in_groups()
{
    $args = [
        'group' => null,
        'pagination' => [
            'page' => 1,
            'start' => 0,
            'per_page' => -1, //get_option('pagination_results_per_page')
        ]
    ];
    
    $files = get_public_files($args);

    return $files['pagination']['total'];
}