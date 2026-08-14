<?php

use App\Modules\Platform\Capabilities\Edition;

return [

    /*
    |--------------------------------------------------------------------------
    | Edition
    |--------------------------------------------------------------------------
    |
    | Which edition this installation runs as. Edition is configuration, not a
    | code branch: every behavioural difference between editions must flow
    | through the capability registry, never through ad-hoc edition checks.
    |
    | Supported: "community", "cloud"
    |
    */

    'edition' => Edition::from((string) env('PROJECTSEND_EDITION', 'community')),

    /*
    |--------------------------------------------------------------------------
    | Chunked upload part size (MB)
    |--------------------------------------------------------------------------
    |
    | Each resumable-upload part travels as one request of this size;
    | web-server/PHP body limits only need to cover a single part.
    |
    */

    'upload_part_size_mb' => 20,

    /*
    |--------------------------------------------------------------------------
    | CAPTCHA
    |--------------------------------------------------------------------------
    |
    | Two things live here rather than in the settings store, for two
    | different reasons.
    |
    | "disabled" is an escape hatch: a wrong secret key cannot lock anybody
    | out (see CaptchaResult), but an operator who has managed it some other
    | way needs a fix that touches no database and needs no working login.
    |
    | The managed keys are the platform's own, applied to every tenant on
    | cloud and absent everywhere else. In config rather than the tenant
    | database so a database dump never carries our credential, and so
    | rotating it is one fleet-wide change instead of a migration. They do
    | nothing without Capability::CaptchaManagedKeys.
    |
    */

    'captcha' => [
        'disabled' => (bool) env('PROJECTSEND_CAPTCHA_DISABLED', false),

        'managed' => [
            'provider' => env('PROJECTSEND_CAPTCHA_MANAGED_PROVIDER'),
            'site_key' => env('PROJECTSEND_CAPTCHA_MANAGED_SITE_KEY'),
            'secret_key' => env('PROJECTSEND_CAPTCHA_MANAGED_SECRET_KEY'),
            'score_threshold' => (float) env('PROJECTSEND_CAPTCHA_MANAGED_SCORE_THRESHOLD', 0.5),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Release identity
    |--------------------------------------------------------------------------
    |
    | Per-release facts that ship with the code. Not settings: they never
    | vary per install or tenant.
    |
    */

    'version' => '2.0.0',

    /*
    |--------------------------------------------------------------------------
    | Official links
    |--------------------------------------------------------------------------
    */

    'links' => [
        'website' => 'https://www.projectsend.org/',
        // Where this code lives, and the same repository
        // CheckForUpdatesCommand asks for the latest release. v1 remains
        // available at github.com/projectsend/legacy.
        'source' => 'https://github.com/projectsend/projectsend',
        'open_collective' => 'https://opencollective.com/projectsend',
    ],

];
