<?php
function user_meta_exists($user_id = null, $meta_name = null)
{
    global $dbh;

    if (empty($user_id) or empty($meta_name)) {
        return false;
    }

    if (!is_numeric($user_id)) {
        return false;
    }

    $statement = $dbh->prepare( "SELECT id FROM " . TABLE_USER_META . " WHERE user_id=:user_id AND name=:name");
    $statement->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $statement->bindParam(':name', $meta_name);
    $statement->execute();
    $count = $statement->rowCount();
    if ($count > 0) {
        return true;
    }

    return false;
}

function get_user_meta($user_id = null, $meta_name = null)
{
    global $dbh;

    if (empty($user_id) or empty($meta_name)) {
        return false;
    }

    if (!is_numeric($user_id)) {
        return false;
    }

    $statement = $dbh->prepare( "SELECT * FROM " . TABLE_USER_META . " WHERE user_id=:user_id AND name=:name");
    $statement->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $statement->bindParam(':name', $meta_name);
    $statement->execute();
    $count = $statement->rowCount();
    if ($count > 0) {
        while ( $row = $statement->fetch() ) {
            $meta = array(
                'id' => html_output($row['id']),
                'name' => html_output($row['name']),
                'value' => html_output($row['value']),
                'created_date' => html_output($row['timestamp']),
                'updated_at' => html_output($row['updated_at']),
            );
    
            return $meta;
        }
    }

    return null;
}


function get_all_user_meta($user_id = null)
{
    global $dbh;

    if (empty($user_id)) {
        return false;
    }

    if (!is_numeric($user_id)) {
        return false;
    }

    $return = [];
    $statement = $dbh->prepare( "SELECT * FROM " . TABLE_USER_META . " WHERE user_id=:user_id");
    $statement->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $statement->execute();
    $count = $statement->rowCount();
    if ($count > 0) {
        while ( $row = $statement->fetch() ) {
            $return[] = array(
                'id' => html_output($row['id']),
                'name' => html_output($row['name']),
                'value' => html_output($row['value']),
                'created_date' => html_output($row['timestamp']),
                'updated_at' => html_output($row['updated_at']),
            );    
        }

        return $return;
    }

    return null;
}


/**
 * Save a value to the database
 *
 * @param int $user_id
 * @param string $meta_name
 * @param boolean $update_if_exists Update an existing meta row instead of creating a new one
 * @return bool
 */
function save_user_meta($user_id = null, $meta_name = null, $meta_value = null, $update_if_exists = false)
{
    global $dbh;

    if (empty($user_id) or empty($meta_name)) {
        return false;
    }

    if (!is_numeric($user_id)) {
        return false;
    }

    if ($update_if_exists == true)
    {
        if (user_meta_exists($user_id, $meta_name)) {
            $statement = $dbh->prepare( "UPDATE " . TABLE_USER_META . " SET value=:value, updated_at=NOW() WHERE user_id=:user_id AND name=:name" );
            $statement->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $statement->bindValue(':name', $meta_name);
            $statement->bindValue(':value', $meta_value);

            return $statement->execute();
        }
    }

    $statement = $dbh->prepare("INSERT INTO " . TABLE_USER_META . " (user_id, name, value) VALUES (:user_id, :name, :value)");
    $statement->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $statement->bindValue(':name', $meta_name);
    $statement->bindValue(':value', $meta_value);
    return $statement->execute();
}

function delete_user_meta($user_id = null, $meta_name = null)
{
    global $dbh;

    if (empty($user_id) or empty($meta_name)) {
        return false;
    }

    if (!is_numeric($user_id)) {
        return false;
    }

    $statement = $dbh->prepare( "DELETE FROM " . TABLE_USER_META . " WHERE user_id=:user_id AND name=:name");
    $statement->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $statement->bindParam(':name', $meta_name);

    return $statement->execute();
}
