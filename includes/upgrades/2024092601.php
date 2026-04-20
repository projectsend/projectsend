<?php

function upgrade_2024092601()
{
    add_option_if_not_exists('upload_chunk_size', '1');
}
