<?php
function upgrade_2022052101()
{
    add_option_if_not_exists('notifications_send_when_saving_files', '1');
}
