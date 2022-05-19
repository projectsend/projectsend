<?php
function upgrade_2022051801()
{
    add_option_if_not_exists('notifications_max_emails_at_once', '0');
}
