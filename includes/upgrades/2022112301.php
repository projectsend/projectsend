<?php
function upgrade_2022112301()
{
    add_option_if_not_exists('uploads_organize_folders_by_date', '0');
}
