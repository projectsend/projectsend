<?php

function upgrade_2025041801()
{
    add_option_if_not_exists('prevent_updates_check', '0');
}
