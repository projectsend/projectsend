<?php

function upgrade_2025042301()
{
    // Check if the old recaptcha was enabled
    $method = null;
    if (get_option('recaptcha_enabled') == 1 &&
        !empty(get_option('recaptcha_site_key')) &&
        !empty(get_option('recaptcha_secret_key'))
    ) {
        $method = 'recaptchav2';
    }
    
    add_option_if_not_exists('captcha_method', $method);
    add_option_if_not_exists('cloudflare_turnstile_site_key', null);
    add_option_if_not_exists('cloudflare_turnstile_secret_key', null);
}
