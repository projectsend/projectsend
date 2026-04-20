<?php
function upgrade_2026041802()
{
    add_option_if_not_exists('oidc_signin_enabled', 'false');
    add_option_if_not_exists('oidc_display_name', 'SSO / OIDC');
    add_option_if_not_exists('oidc_issuer_url', '');
    add_option_if_not_exists('oidc_client_id', '');
    add_option_if_not_exists('oidc_client_secret', '');
}
