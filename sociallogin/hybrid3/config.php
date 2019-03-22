<?php
/**
 * Build a configuration array to pass to `Hybridauth\Hybridauth`
 */
 ini_set('display_errors', 1);
 ini_set('display_startup_errors', 1);
 error_reporting(E_ALL);
require_once('../../sys.includes.php');

$config = [
  /**
   * Set the Authorization callback URL to https://path/to/hybridauth/examples/example_06/callback.php.
   * Understandably, you need to replace 'path/to/hybridauth' with the real path to this script.
   */
  'callback' => 'http://demo4.rndshosting.com/msend/sociallogin/hybrid3/callback.php',
  'providers' => [
    'Twitter' => [
      'enabled' => true,
      'keys' => [
        'key' => TWITTER_CLIENT_ID,
        'secret' => TWITTER_CLIENT_SECRET,
      ],
    ],
    'Facebook' => [
      'enabled' => true,
      'keys' => [
        'key' => FACEBOOK_CLIENT_ID,
        'secret' => FACEBOOK_CLIENT_SECRET,
      ],
    ],
    'LinkedIn' => [
      'enabled' =>true,
      'keys' => [
        'id' => LINKEDIN_CLIENT_ID,
        'secret' => LINKEDIN_CLIENT_SECRET,
      ],
    ],
    'Yahoo' => [
      'enabled' => true,
      'keys' => [
        'id' => YAHOO_CLIENT_ID,
        'secret' => YAHOO_CLIENT_SECRET,
      ]
    ],
  ],
];
