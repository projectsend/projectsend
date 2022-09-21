<?php
function upgrade_2022092001()
{
    add_option_if_not_exists('public_listing_enable_preview', '1');
}
