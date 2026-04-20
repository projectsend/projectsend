<?php

function upgrade_2025071401()
{
    add_option_if_not_exists('mail_smtp_secure', 'none');
}
