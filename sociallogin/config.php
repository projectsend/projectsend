<?php
/*!
* HybridAuth
* http://hybridauth.sourceforge.net | http://github.com/hybridauth/hybridauth
* (c) 2009-2012, HybridAuth authors | http://hybridauth.sourceforge.net/licenses.html
*/

// ----------------------------------------------------------------------------------------
//	HybridAuth Config file: http://hybridauth.sourceforge.net/userguide/Configuration.html
// ----------------------------------------------------------------------------------------


require_once('../sys.includes.php');
//echo LINKEDIN_CLIENT_ID;exit;
//print_r($_SESSION);exit;


//"keys"    => array ( "id" => FACEBOOK_CLIENT_ID, "secret" => FACEBOOK_CLIENT_SECRET ), 

//echo FACEBOOK_CLIENT_ID;
//echo BASE_URI.'<br>';//."sociallogin/hybridauth/index.php";
$config =array(
		"base_url" => BASE_URI."sociallogin/hybridauth/index.php", 
		"providers" => array ( 

			"Facebook" => array ( 
				"enabled" => true,
				"keys"    => array ( "id" => FACEBOOK_CLIENT_ID, "secret" => FACEBOOK_CLIENT_SECRET ), 
				"scope"   => "email", // optional
          			"display" => "popup", // optional 
				"trustForwarded" => true,
			),

			"Twitter" => array ( 
				"enabled" => true,
               			"keys" => array ( "key" => TWITTER_CLIENT_ID, "secret" => TWITTER_CLIENT_SECRET ),
				"includeEmail" => true,
			),
			"Yahoo" => array(
					"enabled" => true,
					"keys" => array("key" => YAHOO_CLIENT_ID, "secret" => YAHOO_CLIENT_SECRET),
			),
			"LinkedIn" => array(
					"enabled" => true, 
					"keys" => array("key" => LINKEDIN_CLIENT_ID, "secret" => LINKEDIN_CLIENT_SECRET),
					"scope"   => array("r_fullprofile", "r_emailaddress", "w_share"), // optional
          				"fields"  => array("id", "email-address", "first-name", "last-name"), // optional
				),
		/*	"office365" => array(
					"enabled" => true,
					"keys" => array("key" => WINDOWS_CLIENT_ID),
					//"scope" => "r_emailaddress"
				),*/
		),
		// if you want to enable logging, set 'debug_mode' to true  then provide a writable file by the web server on "debug_file"
		"debug_mode" => false,
		"debug_file" => "",
	);
//echo "<pre>";print_r($config);exit();
