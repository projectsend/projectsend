<?php
    global $hybridauth;
    $config = array(
        "base_url" => BASE_URI,
        'callback' => BASE_URI . 'login-callback.php',
        "providers" => array(
            "Facebook" => array(
                "enabled" => get_option('facebook_signin_enabled'),
                "keys" => array("id" => get_option("facebook_client_id"), "secret" => get_option("facebook_client_secret")),
                "trustForwarded" => false,
            ),
            "Google" => array(
                "enabled" => get_option('google_signin_enabled'),
                "keys" => array("id" => get_option("google_client_id"), "secret" => get_option("google_client_secret")),
            ),
            "LinkedIn" => array(
                "enabled" => get_option('linkedin_signin_enabled'),
                "keys" => array("id" => get_option("SOCIAL_LOGIN_LINKEDIN_ID"), "secret" => get_option("SOCIAL_LOGIN_LINKEDIN_SECRET")),
                "fields" => array(),
            ),
            "Twitter" => array(
                "enabled" => get_option('twitter_signin_enabled'),
                "keys" => array("id" => get_option("SOCIAL_LOGIN_LIVE_ID"), "secret" => get_option("SOCIAL_LOGIN_LIVE_SECRET")),
            ),
            "WindowsLive" => array(
                "enabled" => get_option('windowslive_signin_enabled'),
                "keys" => array("id" => get_option("SOCIAL_LOGIN_LIVE_ID"), "secret" => get_option("SOCIAL_LOGIN_LIVE_SECRET")),
            ),
            "Yahoo" => array(
                "enabled" => get_option('yahoo_signin_enabled'),
                "keys" => array("id" => get_option("SOCIAL_LOGIN_LIVE_ID"), "secret" => get_option("SOCIAL_LOGIN_LIVE_SECRET")),
            ),
            "OpenID" => array(
                "enabled" => get_option('oidc_signin_enabled'),
            ),
            "MicrosoftGraph" => array(
                "enabled" => get_option('microsoftgraph_signin_enabled'),
                "keys" => array("id" => get_option("microsoftgraph_client_id"), "secret" => get_option("microsoftgraph_client_secret")),
                "tenant" => get_option('microsoftgraph_client_tenant')
            )
        ),
        // debug_mode possible values
        // - "error" log only error messages
        // - "info" log info and error messages (ignore debug messages)
        // - false
        "debug_mode" => false,
        // Path to file writable by the web server. Required if 'debug_mode' is not false
        "debug_file" => ROOT_DIR."/hybridauth.log",
    );

    $hybridauth = new Hybridauth\Hybridauth($config);
