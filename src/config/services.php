<?php

return [

    'twig' => [
        'path' => [
            ADMIN_VIEWS_DIR
        ],
        'options' => [
            'cache' => $app->getCacheDir().'/twig',
        ]
    ],

    'monolog' => [
        'name'  => 'app',
        'path'  => $app->getLogDir().'/'.$app->getEnvironment().'.log',
        'level' => Monolog\Logger::ERROR
    ]

];
