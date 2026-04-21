<?php
function upgrade_2026041801()
{
    add_option_if_not_exists('x_signin_enabled', 'false');
    add_option_if_not_exists('x_client_id', '');
    add_option_if_not_exists('x_client_secret', '');
}
