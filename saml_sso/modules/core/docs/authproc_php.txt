`core:PHP`
==========

This is a filter which makes it possible to run arbitrary PHP code to modify the attributes of an user.

Parameters
----------

`class`
:   This is the name of the filter.
    It must be `'core:PHP'`.

`code`
:   The PHP code that should be run. This code will have only one variable available: `$attributes`.
    This is an associative array of attributes, and can be modified to add or remove attributes.

Examples
--------

Add the `mail` attribute based on the user's `uid` attribute:

    10 => array(
        'class' => 'core:PHP',
        'code' => '
            if (empty($attributes["uid"])) {
                throw new Exception("Missing uid attribute.");
            }

            $uid = $attributes["uid"][0];
            $mail = $uid . "@example.net";
            $attributes["mail"] = array($mail);
        ',
    ),


Create a random number variable:

    10 => array(
        'class' => 'core:PHP',
        'code' => '
            $attributes["random"] = array(
                (string)rand(),
            );
        ',
    ),
