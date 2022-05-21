<?php
function upgrade_2022051901()
{
    add_option_if_not_exists('cron_command_line_only', '1');
    add_option_if_not_exists('cron_email_summary_send', '0');
    add_option_if_not_exists('cron_email_summary_address_to', '');
}
