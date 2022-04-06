<?php
function upgrade_2022040601()
{
    add_option_if_not_exists('cron_enable', 0);
    add_option_if_not_exists('cron_key', generate_password(24));
    add_option_if_not_exists('cron_send_emails', 0);
    add_option_if_not_exists('cron_delete_expired_files', 0);
    add_option_if_not_exists('cron_delete_orphan_files', 0);
}
