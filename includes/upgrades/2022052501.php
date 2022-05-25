<?php
function upgrade_2022052501()
{
    add_option_if_not_exists('cron_delete_orphan_files_types', 'not_allowed');
}
