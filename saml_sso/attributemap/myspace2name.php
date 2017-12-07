<?php
$attributemap = array(

    // See http://developerwiki.myspace.com/index.php?title=People_API for attributes

    // Generated MySpace Attributes
    'myspace_user'            => 'eduPersonPrincipalName', // username OR uid @ myspace.com
    'myspace_targetedID'      => 'eduPersonTargetedID', // http://myspace.com!uid
    'myspace_username'        => 'uid', // myspace username (maybe numeric uid)

    // Attributes Returned by MySpace
    'myspace.name.givenName'  => 'givenName',
    'myspace.name.familyName' => 'sn',
    'myspace.displayName'     => 'displayName',
    'myspace.profileUrl'      => 'labeledURI',
);
