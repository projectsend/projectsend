<?php
function upgrade_2022112101()
{
    add_option_if_not_exists('download_logging_ignore_file_author', '0');
}
