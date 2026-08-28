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
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Where a chunked upload's parts wait while the transfer is running,
    | before they are assembled onto the storage disk. Leave this unset:
    | it exists so the test suite can give each parallel worker its own
    | directory, since parts are real files on a real path rather than a
    | faked disk, and session ids restart at 1 in every worker's database.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Platform seats
    |--------------------------------------------------------------------------
    |
    | How many staff accounts and how many clients this installation may
    | hold. Unset means unlimited, which is every self-hosted install: this
    | exists for a managed one, where the operator sold a number and the
    | application is the only process that can actually count against it.
    |
    | An operator stating the installation's own limit is not the same as
    | the application inventing a plan tier — the distinction config/api.php
    | draws when it declines to key a rate limit off billing. Nothing here
    | knows what a plan is; it accepts a number and refuses to exceed it.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Capabilities this installation has been told it may not use
    |--------------------------------------------------------------------------
    |
    | Comma-separated capability keys, subtracted from what the edition
    | grants. Only ever subtracted: nothing here can switch a capability on,
    | because a variable that could would put the hosted edition's screens
    | one line of .env away on every self-hosted install.
    |
    | For a managed installation whose plan does not include something its
    | edition otherwise has -- branding.customize on a free plan is the case
    | this was built for. Unknown keys are ignored rather than fatal: the
    | variable outlives both the plan that wrote it and the release that
    | named the key, and an instance refusing to boot over a stale one would
    | be an outage on upgrade day.
    |
    */

    'capabilities_disabled' => env('PROJECTSEND_CAPABILITIES_DISABLED'),

    'platform' => [
        'max_staff_users' => env('PROJECTSEND_PLATFORM_MAX_STAFF_USERS'),
        'max_clients' => env('PROJECTSEND_PLATFORM_MAX_CLIENTS'),

        // Seeded into Setting::TwoFactorEnforcement on first boot and never
        // afterwards — see SeedSettingsCommand. Here rather than read from
        // env() at the point of use, because config:cache stops .env being
        // read at all and that is how TRUSTED_PROXIES came to silently do
        // nothing.
        'two_factor_enforcement' => env('PROJECTSEND_TWO_FACTOR_ENFORCEMENT'),
    ],

    'uploads' => [
        'parts_path' => env('UPLOAD_PARTS_PATH'),
    ],

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

    'version' => '2.2.1',

    /*
    |--------------------------------------------------------------------------
    | Official links
    |--------------------------------------------------------------------------
    */

    // Read through App\Modules\Platform\OfficialLinks rather than
    // directly: which of the two front doors "website" means, and whether
    // the donation link is offered at all, both depend on the edition.
    'links' => [
        'website' => 'https://www.projectsend.org/',
        // The hosted service's own front door. A managed installation
        // links here instead — including from the "Powered by" line on
        // client-facing pages and outgoing email.
        'website_cloud' => 'https://www.projectsend.cloud/',
        // Where this code lives, and the same repository
        // CheckForUpdatesCommand asks for the latest release. v1 remains
        // available at github.com/projectsend/legacy.
        'source' => 'https://github.com/projectsend/projectsend',
        'open_collective' => 'https://opencollective.com/projectsend',
        // Kept identical to the invitation update.sh prints when an update
        // finishes — the two are the same offer, made in the terminal and
        // then again on the screen the administrator lands on.
        'discord' => 'https://discord.gg/VT9n6cyvXT',
    ],

];
