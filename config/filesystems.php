<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        // Protected client files. Local root matches the nginx
        // X-Accel internal location; cloud points this at S3.
        'files' => [
            'driver' => 'local',
            'root' => storage_path('app/files'),
            'serve' => false,
            'throw' => false,

            // A download is not served by PHP. PHP authorizes it and
            // hands the web server the path with X-Accel-Redirect, so the
            // web server has to open a file PHP wrote. Where the two are
            // different users — cPanel and Plesk commonly do this — it
            // cannot: uploads land 0600 inside a 0700 directory, and
            // traversing 0700 means *being* its owner. Everything else on
            // the site keeps working, which is what makes it hard to
            // place (#1668).
            //
            // FILES_WEB_SERVER_READABLE relaxes both to 0644/0755. Opt-in,
            // because it is strictly weaker: those modes are readable by
            // every account on the machine, and on a single-user host they
            // buy nothing. The files stay off the web either way — nginx
            // only reaches them through an `internal` location.
            //
            // The two halves are enforced differently, and only one of
            // them is absolute. `visibility` makes Flysystem chmod each
            // file after writing it, so 0644 holds whatever the umask is.
            // Directories get no chmod — they are created by mkdir(), and
            // mkdir() masks its mode argument with the process umask — so
            // 0755 here is a ceiling, not a guarantee. A pool running at
            // umask 0077 still produces 0700 and still cannot be
            // traversed; INSTALL.md covers fixing that, because it cannot
            // be fixed from this file.
            // Spread rather than two ternaries so that leaving the flag
            // off is not merely equivalent to the old configuration but
            // literally it — no install that does not need this sees its
            // file modes change.
            ...(env('FILES_WEB_SERVER_READABLE', false)
                ? ['visibility' => 'public', 'permissions' => ['dir' => ['private' => 0755]]]
                : []),
        ],

        // Laravel's stock private disk. Nothing in this application writes
        // to it, and `serve` is off because the framework's /storage route
        // would otherwise hand out whatever ended up there — a door with
        // nothing behind it today is still a door.
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        // Community-only: an admin-configured S3-compatible bucket
        // (AWS, MinIO, etc). Inert stub — the community-modules
        // ExternalStorage module overwrites these values at boot from
        // its own settings row, only when active. A File never resolves
        // to this disk unless that module is installed and enabled.
        'files_external' => [
            'driver' => 's3',
            'key' => '',
            'secret' => '',
            'region' => '',
            'bucket' => '',
            'endpoint' => null,
            'use_path_style_endpoint' => false,
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
