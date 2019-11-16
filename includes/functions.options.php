<?php
    function option_exists($name)
    {
        global $dbh;

        $get = $dbh->prepare( "SELECT name FROM " . TABLE_OPTIONS . " WHERE name=:name" );
        $get->bindParam(':name', $name);
        $get->execute();
        $row = $get->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return true;
        }

        return false;
    }

    function get_option($name)
    {
        global $dbh;

        $get = $dbh->prepare( "SELECT value FROM " . TABLE_OPTIONS . " WHERE name=:name" );
        $get->bindParam(':name', $name);
        $get->execute();
        $row = $get->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row['value'];
        }
    }

    function save_option($name, $value)
    {
        global $dbh;

        if (option_exists($name)) {
            $save = $dbh->prepare( "UPDATE " . TABLE_OPTIONS . " SET value=:value WHERE name=:name" );
            $save->bindParam(':value', $value);
            $save->bindParam(':name', $name);
            $result = $save->execute();
        }
        else {
            $save = $dbh->prepare("INSERT INTO " . TABLE_OPTIONS . " (name, value)"
            ." VALUES (:name, :value)");
            $save->bindParam(':name', $name);
            $save->bindParam(':value', $value);
            $result = $save->execute();
        }

        return $result;
    }
