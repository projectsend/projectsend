<?php

function upgrade_2025091601()
{
    add_option_if_not_exists('recaptcha_v3_site_key', null);
    add_option_if_not_exists('recaptcha_v3_secret_key', null);
    add_option_if_not_exists('recaptcha_v3_score_threshold', '0.5');
}
