<?php

declare(strict_types=1);

namespace App\Modules\Platform\System;

use App\Modules\Platform\Capabilities\CapabilityRegistry;
use Illuminate\Support\Facades\DB;

/**
 * What this installation is running on — the handful of facts anyone
 * answering "what have you got?" on a bug report needs.
 *
 * Shared by the dashboard's System widget and /system/about rather than
 * computed twice, because the database version is the one row here that
 * is not a constant: it needs a query that can fail on a connection
 * that is up enough to serve the page but not to answer `version()`,
 * and a second copy of that try/catch would eventually stop matching
 * the first.
 */
final class SystemEnvironment
{
    public function __construct(
        private readonly CapabilityRegistry $capabilities,
    ) {}

    /**
     * @return array{version: string, edition: string, php: string, laravel: string, database: string}
     */
    public function toArray(): array
    {
        return [
            'version' => (string) config('projectsend.version'),
            'edition' => $this->capabilities->edition()->value,
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'database' => $this->database(),
        ];
    }

    private function database(): string
    {
        try {
            $version = (string) (DB::selectOne('select version() as v')->v ?? '');
        } catch (\Throwable) {
            $version = '';
        }

        return trim(DB::connection()->getDriverName().' '.$version);
    }
}
