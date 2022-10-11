<?php
function option_exists($name)
{
    $dbh = get_dbh();
    $statement = $dbh->prepare("SELECT name FROM " . TABLE_OPTIONS . " WHERE name=:name");
    $statement->execute([
        ':name' => $name,
    ]);
    return ($statement->rowCount() > 0);
}

function get_option($name, $escape = false)
{
    $dbh = get_dbh();
    if (empty($dbh)) {
        return null;
    }

    try {
        if (table_exists(TABLE_OPTIONS)) {
            $statement = $dbh->prepare("SELECT * FROM " . TABLE_OPTIONS . " WHERE name=:name");
            $statement->execute([
                ':name' => $name,
            ]);
            if ($statement->rowCount() == 0) {
                return null;
            }
        
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            while ( $row = $statement->fetch() ) {
                $value = $row['value'];
                if ($escape == true) {
                    $value = html_output($value);
                }
        
                return $value;
            }
        }
    } catch (\PDOException $e) {
        return null;
    }

    return null;
}

function save_option($name, $value)
{
    $dbh = get_dbh();

    if (option_exists($name)) {
        $save = $dbh->prepare( "UPDATE " . TABLE_OPTIONS . " SET value=:value WHERE name=:name" );
        $save->bindParam(':value', $value);
        $save->bindParam(':name', $name);
        $result = $save->execute();
    }
    else {
        if (!empty($dbh)) {
            $save = $dbh->prepare("INSERT INTO " . TABLE_OPTIONS . " (name, value)"
            ." VALUES (:name, :value)");
            $save->bindParam(':name', $name);
            $save->bindParam(':value', $value);
            $result = $save->execute();
        }
    }

    return $result;
}
