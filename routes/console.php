<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('projectsend:purge-erasures')->daily();
// Hourly, not daily: this one frees disk that an account is holding
// against its own upload limits, so the gap between a session going stale
// and the sweep noticing is a gap where somebody cannot upload. Daily made
// that gap up to two days wide.
Schedule::command('projectsend:purge-stale-uploads')->hourly();
Schedule::command('projectsend:purge-zip-downloads')->daily();
Schedule::command('projectsend:check-for-updates')->daily();
Schedule::command('projectsend:fetch-news')->daily();
Schedule::command('projectsend:purge-expired-files')->daily();
Schedule::command('projectsend:purge-orphan-files')->daily();
Schedule::command('projectsend:purge-api-request-logs')->daily();
Schedule::command('projectsend:purge-failed-jobs')->daily();
Schedule::command('projectsend:purge-notifications')->daily();
Schedule::command('projectsend:refresh-mail-oauth-tokens')->daily();
