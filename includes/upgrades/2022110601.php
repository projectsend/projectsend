<?php
function upgrade_2022110601()
{
    add_option_if_not_exists('clients_files_list_include_public', '0');
    add_option_if_not_exists('clients_can_upload_to_public_folders', '0');
}
