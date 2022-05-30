<?php
function upgrade_2022053002()
{
    add_option_if_not_exists('privacy_record_downloads_ip_address', 'all');
}
