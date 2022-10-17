<?php
function option_exists($name)
{
    $dbh = get_dbh();
    $statement = $dbh->prepare("SELECT name FROM " . get_table('options') . " WHERE name=:name");
    $statement->execute([
        ':name' => $name,
    ]);
    return ($statement->rowCount() > 0);
}

function get_table($name)
{
    global $app;
    $container = $app->getContainer();
    $table = $container->get('db')->getTable($name);
    return $table;
}

function get_option($name, $escape = false)
{
    global $app;
    $container = $app->getContainer();
    $options = $container->get('options');

    try {
        $value = $options->getOption($name);
        if ($escape == true) {
            $value = html_output($value);
        }

        return $value;
    } catch (\PDOException $e) {
        return null;
    }

    return null;
}

function save_option($name, $value)
{
    $dbh = get_dbh();

    if (option_exists($name)) {
        $save = $dbh->prepare( "UPDATE " . get_table('options') . " SET value=:value WHERE name=:name" );
        $save->bindParam(':value', $value);
        $save->bindParam(':name', $name);
        $result = $save->execute();
    }
    else {
        if (!empty($dbh)) {
            $save = $dbh->prepare("INSERT INTO " . get_table('options') . " (name, value)"
            ." VALUES (:name, :value)");
            $save->bindParam(':name', $name);
            $save->bindParam(':value', $value);
            $result = $save->execute();
        }
    }

    return $result;
}
