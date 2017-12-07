`core:AttributeCopy`
===================

Filter that renames attributes.


Examples
--------

Copy a single attribute (user's uid will be copied to the user's username):

    'authproc' => array(
        50 => array(
            'class' => 'core:AttributeCopy',
            'uid' => 'username',
        ),
    ),

