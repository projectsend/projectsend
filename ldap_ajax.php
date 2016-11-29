<?php

$ldapConn = ldap_connect('ldap.forumsys.com');
ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);
$password=$_POST['password'];
$mail = $_POST['email'];
if(ldap_bind($ldapConn, 'cn=read-only-admin,dc=example,dc=com', 'password')) {

            $arr = array('dn', 1);
            $result = ldap_search($ldapConn, 'dc=example,dc=com', "(mail=$mail)", $arr);
            $entries = ldap_get_entries($ldapConn, $result);
                //print_r($entries);
            if ($entries['count'] > 0) {
                if (ldap_bind($ldapConn, $entries[0]['dn'], $password)) {	
                    	// user with mail $mail is checked with password $password
                	echo "success";
                }else{
                    echo 'failed';
                }
            }

        }
ldap_close($ldapConn);

