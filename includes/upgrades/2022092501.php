<?php
function upgrade_2022092501()
{
    add_option_if_not_exists('email_2fa_code_subject_customize', '0');
    add_option_if_not_exists('email_2fa_code_subject', '');
    add_option_if_not_exists('email_2fa_code_customize', '0');
    add_option_if_not_exists('email_2fa_code_text', '');
}
