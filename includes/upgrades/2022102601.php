<?php
function upgrade_2022102601()
{
    add_option_if_not_exists('public_listing_home_show_link', '0');
}
