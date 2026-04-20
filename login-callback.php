<?php
use ProjectSend\Classes\Session as Session;

require_once 'bootstrap.php';

global $hybridauth;
global $auth;

try {
    // Verify we have a provider in session
    if (!Session::has('SOCIAL_LOGIN_NETWORK')) {
        throw new Exception(__('Invalid social login session. Please try again.', 'cftp_admin'));
    }

    $provider = Session::get('SOCIAL_LOGIN_NETWORK');

    // Note: HybridAuth validates the OAuth state parameter internally
    // for CSRF protection, so we don't need to implement it separately

    // Authenticate with provider
    $adapter = $hybridauth->authenticate($provider);

    if (!$adapter->isConnected()) {
        throw new Exception(__('Failed to connect to social login provider.', 'cftp_admin'));
    }

    // Process the social login
    $auth->socialLogin($provider);

} catch (Exception $e) {
    // Log the error
    error_log('Social login error: ' . $e->getMessage());

    // Clear session
    Session::remove('SOCIAL_LOGIN_NETWORK');

    // Disconnect adapter if connected
    if (isset($adapter) && $adapter->isConnected()) {
        try {
            $adapter->disconnect();
        } catch (Exception $disconnect_error) {
            error_log('Error disconnecting adapter: ' . $disconnect_error->getMessage());
        }
    }

    // Redirect to login with error message
    $_SESSION['error_message'] = $e->getMessage();
    ps_redirect(BASE_URI . 'index.php?error=social_login');
}
