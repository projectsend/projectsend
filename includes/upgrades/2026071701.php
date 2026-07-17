<?php
function upgrade_2026071701()
{
    add_option_if_not_exists('cron_delete_decrypt_temp_files', 0);
}
