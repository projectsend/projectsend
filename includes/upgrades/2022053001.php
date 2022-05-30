<?php
function upgrade_2022053001()
{
    add_option_if_not_exists('files_default_expire', '0');
    add_option_if_not_exists('files_default_expire_days_after', '30');
}
