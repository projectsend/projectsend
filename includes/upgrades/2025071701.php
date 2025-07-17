<?php

function upgrade_2025071701()
{
    // Need to set the captcha_method to a default value due to UI changes
    if(get_option('captcha_method') == null) {
        save_option('captcha_method', 'none');
    }
}
