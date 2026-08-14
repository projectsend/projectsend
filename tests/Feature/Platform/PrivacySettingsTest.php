<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('staff can view and update privacy settings', function () {
    // Settings are cached across tests (Cache::rememberForever survives
    // the per-test DB rollback) — reset to defaults explicitly rather
    // than assuming nothing else in the suite has touched them.
    $settings = app(Settings::class);
    $settings->set(Setting::DownloadIpLogging, 'all');
    $settings->set(Setting::AccountErasureGraceDays, 30);
    $settings->set(Setting::DiscourageSearchIndexing, false);

    $this->actingAs($this->admin)->get('/system/settings/privacy')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/privacy')
            ->where('download_ip_logging', 'all')
            ->where('account_erasure_grace_days', 30)
            ->where('api_request_log_retention_days', 30)
            ->where('discourage_search_indexing', false),
    );

    $this->actingAs($this->admin)->patch('/system/settings/privacy', [
        'download_ip_logging' => 'anonymous_only',
        'account_erasure_grace_days' => 14,
        'account_erasure_content_action' => 'cascade_delete',
        'api_request_log_retention_days' => 7,
        'discourage_search_indexing' => true,
    ])->assertRedirect();

    $settings = app(Settings::class);
    expect($settings->get(Setting::DownloadIpLogging))->toBe('anonymous_only')
        ->and($settings->get(Setting::AccountErasureGraceDays))->toBe(14)
        ->and($settings->get(Setting::ApiRequestLogRetentionDays))->toBe(7)
        ->and($settings->get(Setting::DiscourageSearchIndexing))->toBeTrue();

    expect(ActivityLog::query()->where('action', Action::SettingsUpdated)->where('context->section', 'privacy')->exists())
        ->toBeTrue();
});

test('an invalid download IP logging value is rejected', function () {
    $this->actingAs($this->admin)->patch('/system/settings/privacy', [
        'download_ip_logging' => 'bogus',
        'account_erasure_grace_days' => 30,
        'discourage_search_indexing' => false,
    ])->assertSessionHasErrors('download_ip_logging');
});

test('reassign on erasure requires a target account', function () {
    $this->actingAs($this->admin)->patch('/system/settings/privacy', [
        'download_ip_logging' => 'all',
        'account_erasure_grace_days' => 30,
        'account_erasure_content_action' => 'reassign',
        // no account_erasure_reassign_to
        'api_request_log_retention_days' => 30,
        'discourage_search_indexing' => false,
    ])->assertSessionHasErrors('account_erasure_reassign_to');

    // And it must be an existing, active account.
    $inactive = User::factory()->create(['active' => false]);
    $this->actingAs($this->admin)->patch('/system/settings/privacy', [
        'download_ip_logging' => 'all',
        'account_erasure_grace_days' => 30,
        'account_erasure_content_action' => 'reassign',
        'account_erasure_reassign_to' => $inactive->id,
        'api_request_log_retention_days' => 30,
        'discourage_search_indexing' => false,
    ])->assertSessionHasErrors('account_erasure_reassign_to');
});

test('clients cannot access privacy settings', function () {
    $this->admin; // setup complete

    $this->actingAs(User::factory()->client()->create())
        ->get('/system/settings/privacy')
        ->assertRedirect(route('dashboard'));
});

test('the noindex meta tag reflects the setting', function () {
    // Match the exact tag, not the bare word "noindex" — the shared
    // Inertia prop of that name is always embedded as JSON in the page
    // (data-page attribute) regardless of its value, so a substring
    // check on the word alone would always find a hit.
    $metaTag = '<meta name="robots" content="noindex">';

    // Settings are cached (Cache::rememberForever), which survives the
    // per-test DB rollback — reset explicitly rather than assuming the
    // code default is still what's cached from an earlier test.
    app(Settings::class)->set(Setting::DiscourageSearchIndexing, false);
    $this->actingAs($this->admin)->get('/dashboard')->assertDontSee($metaTag, false);

    app(Settings::class)->set(Setting::DiscourageSearchIndexing, true);
    $this->actingAs($this->admin)->get('/dashboard')->assertSee($metaTag, false);
});
