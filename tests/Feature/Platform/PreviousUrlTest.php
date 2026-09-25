<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/*
 * `back()` prefers the Referer header and falls back to the URL the session
 * last recorded. Laravel records every GET that is not marked as Ajax, and a
 * plain fetch() is not marked: the notification bell's poll for its count
 * became the "previous page". Wherever the Referer does not arrive (a proxy
 * or a browser that strips it), saving a settings form redirected there, and
 * Inertia showed the raw {"count":0} in an error dialog (#1799).
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
    app(Settings::class)->set(Setting::DownloadIpLogging, 'all');
});

function savePrivacySettings(): Illuminate\Testing\TestResponse
{
    // No from(): the test client sends no Referer, which is the condition
    // under which the bug appears.
    return test()->patch('/system/settings/privacy', [
        'download_ip_logging' => 'all',
        'account_erasure_grace_days' => 30,
        'account_erasure_content_action' => 'cascade_delete',
        'account_self_delete_files' => 'after_grace_period',
        'account_self_delete_scope' => 'any',
        'api_request_log_retention_days' => 30,
        'discourage_search_indexing' => false,
    ]);
}

test('a JSON poll between opening a form and saving it is not where the save goes back to', function () {
    $this->actingAs($this->admin)->get('/system/settings/privacy')->assertOk();

    // Exactly what the bell sends: a GET asking for JSON, not marked as Ajax.
    $this->actingAs($this->admin)
        ->get('/notifications/unread-count', ['Accept' => 'application/json'])
        ->assertOk();

    savePrivacySettings()->assertRedirect('/system/settings/privacy');
});

test('an ordinary page visit is still remembered as the previous page', function () {
    // The pin: the fix must not stop back() working at all.
    $this->actingAs($this->admin)->get('/system/settings/privacy')->assertOk();
    $this->actingAs($this->admin)->get('/system/settings/general')->assertOk();

    savePrivacySettings()->assertRedirect('/system/settings/general');
});
