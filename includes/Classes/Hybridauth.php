<?php
namespace ProjectSend\Classes;

class Hybridauth {
    protected $config;
    protected $instance;

    public function __construct(Options $options)
    {
        $this->config = [
            "base_url" => BASE_URI,
            'callback' => BASE_URI . 'login-callback.php',
            "providers" => array(
                "Facebook" => array(
                    "enabled" => $options->getOption('facebook_signin_enabled'),
                    "keys" => array("id" => $options->getOption("facebook_client_id"), "secret" => $options->getOption("facebook_client_secret")),
                    "trustForwarded" => false,
                ),
                "Google" => array(
                    "enabled" => $options->getOption('google_signin_enabled'),
                    "keys" => array("id" => $options->getOption("google_client_id"), "secret" => $options->getOption("google_client_secret")),
                ),
                "LinkedIn" => array(
                    "enabled" => $options->getOption('linkedin_signin_enabled'),
                    "keys" => array("id" => $options->getOption("linkedin_client_id"), "secret" => $options->getOption("linkedin_client_secret")),
                    "fields" => array(),
                ),
                "Twitter" => array(
                    "enabled" => $options->getOption('twitter_signin_enabled'),
                    "keys" => array("id" => $options->getOption("twitter_client_id"), "secret" => $options->getOption("twitter_client_secret")),
                ),
                "WindowsLive" => array(
                    "enabled" => $options->getOption('windowslive_signin_enabled'),
                    "keys" => array("id" => $options->getOption("windowslive_client_id"), "secret" => $options->getOption("windowslive_client_secret")),
                ),
                "Yahoo" => array(
                    "enabled" => $options->getOption('yahoo_signin_enabled'),
                    "keys" => array("id" => $options->getOption("yahoo_client_id"), "secret" => $options->getOption("yahoo_client_secret")),
                ),
                "OpenID" => array(
                    "enabled" => $options->getOption('oidc_signin_enabled'),
                ),
                "MicrosoftGraph" => array(
                    "enabled" => $options->getOption('microsoftgraph_signin_enabled'),
                    "keys" => array("id" => $options->getOption("microsoftgraph_client_id"), "secret" => $options->getOption("microsoftgraph_client_secret")),
                    "tenant" => $options->getOption('microsoftgraph_client_tenant')
                )
            ),
            // debug_mode possible values
            // - "error" log only error messages
            // - "info" log info and error messages (ignore debug messages)
            // - false
            "debug_mode" => false,
            // Path to file writable by the web server. Required if 'debug_mode' is not false
            "debug_file" => ROOT_DIR."/hybridauth.log",
        ];

        $this->instance = new \Hybridauth\Hybridauth($this->config);
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getHybridauth()
    {
        return $this->instance;
    }
}