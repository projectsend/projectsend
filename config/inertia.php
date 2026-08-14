<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The package default looks in js/Pages (capital P); this project keeps
    | pages in js/pages, which matters on case-sensitive filesystems.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

        'page_paths' => [
            resource_path('js/pages'),
        ],

        'page_extensions' => [
            'js',
            'jsx',
            'ts',
            'tsx',
        ],

    ],

];
