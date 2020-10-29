<?php
// global $options: \ProjectSend\Classes\Options already set in app init

function option_exists($name)
{
    global $options;

    if (!empty($options)) {
        if (array_key_exists($name, $options->options)) {
            return true;
        }
    }

    return false;
}

function get_option($name)
{
    global $options;

    if (!empty($options)) {
        if (option_exists($name)) {
            return $options->options[$name];
        }
    }

    return null;
}

function save_option($name, $value)
{
    global $options;
    global $dbh;

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

    $options->options[$name] = $value;

    return $result;
}
