<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->admin = User::factory()->create();

    // Settings survive the per-test rollback, so nothing here may assume
    // a default — see the note in CaptchaSettingsTest.
    $settings = app(Settings::class);
    $settings->set(Setting::CheckForUpdates, true);
    $settings->set(Setting::FetchNews, true);

    config()->set('projectsend.edition', Edition::Community);
});

/*
|--------------------------------------------------------------------------
| Two daily calls out of the container, and who may stop them
|--------------------------------------------------------------------------
|
| An operator could stop neither. The news feed had no switch of any kind,
| and the update check had one whose default is on — so a managed fleet
| believed it had disabled updates through an environment variable that
| nothing in this application reads.
|
| They are not the same case, and are not fixed the same way. Which
| mechanism each gets is the point of these tests.
*/

test('the news feed can be switched off, and says so rather than failing', function () {
    Http::fake();
    app(Settings::class)->set(Setting::FetchNews, false);

    $this->artisan('projectsend:fetch-news')
        ->expectsOutputToContain('switched off')
        ->assertSuccessful();

    // Not merely "no items stored" — the request never left.
    Http::assertNothingSent();
});

test('the news feed is on by default, so nothing changes for an existing install', function () {
    Http::fake(['*' => Http::response([])]);

    $this->artisan('projectsend:fetch-news')->assertSuccessful();

    Http::assertSentCount(1);
});

// The news itself is both editions — a Cloud client with view_news sees
// that card. What is Community-only is the *choice*: announcements about
// the product are what a hosted customer should be told, and one
// administrator switching them off for everybody on that instance is not
// a decision the platform hands over.
//
// The exact opposite of the update check below, which does not run on a
// managed instance at all. The two look alike and point in different
// directions, so both directions are pinned.
test('a managed instance fetches the news whatever its setting says', function () {
    Http::fake(['*' => Http::response([])]);
    config()->set('projectsend.edition', Edition::Cloud);

    // Off — including a row left behind by an instance that used to be
    // self-hosted, which is the case that would otherwise go silent.
    app(Settings::class)->set(Setting::FetchNews, false);

    $this->artisan('projectsend:fetch-news')->assertSuccessful();

    Http::assertSentCount(1);
});

test('a managed instance is not offered the switch, and cannot be sent it', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    app(Settings::class)->set(Setting::FetchNews, true);

    $this->actingAs($this->admin)->get('/system/settings/general')->assertInertia(
        fn (Inertia\Testing\AssertableInertia $page) => $page
            ->where('can_configure_news', false)
            ->where('fetch_news', null),
    );

    // A hand-crafted PATCH must not do what the absent checkbox could not.
    $this->actingAs($this->admin)
        ->patch('/system/settings/general', generalPayload(['fetch_news' => false]))
        ->assertRedirect();

    expect(app(Settings::class)->get(Setting::FetchNews))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The update check is the other kind
|--------------------------------------------------------------------------
|
| On a managed installation the result is unreachable rather than
| unwanted: the dashboard's System card and the update UI are both gated
| on Capability::SystemUpdates, which is Community-only, and the image is
| chosen by whoever provisioned the instance. That is a fact about the
| edition, not a preference — so it is a capability, not a Setting.
*/

test('the update check does not run where its answer could never be seen', function () {
    Http::fake();
    config()->set('projectsend.edition', Edition::Cloud);

    // On, and it still must not call out: the capability decides first.
    app(Settings::class)->set(Setting::CheckForUpdates, true);

    $this->artisan('projectsend:check-for-updates')
        ->expectsOutputToContain('do not apply')
        ->assertSuccessful();

    Http::assertNothingSent();
});

test('a self-hosted install keeps its own switch, both ways', function () {
    Http::fake(['*' => Http::response([])]);

    app(Settings::class)->set(Setting::CheckForUpdates, false);

    $this->artisan('projectsend:check-for-updates')
        ->expectsOutputToContain('disabled')
        ->assertSuccessful();

    Http::assertNothingSent();

    app(Settings::class)->set(Setting::CheckForUpdates, true);

    $this->artisan('projectsend:check-for-updates')->assertSuccessful();

    Http::assertSentCount(1);
});

/*
|--------------------------------------------------------------------------
| Reachable without a shell
|--------------------------------------------------------------------------
|
| A setting an operator cannot find is not a switch, it is a row. The
| update toggle beside it is hidden where the capability is absent; this
| one must not be, because the card it controls is shown in both editions.
*/

test('a self-hosted installation is offered the switch', function () {
    $this->actingAs($this->admin)->get('/system/settings/general')->assertInertia(
        fn (Inertia\Testing\AssertableInertia $page) => $page
            ->where('can_configure_news', true)
            ->where('fetch_news', true),
    );
});

test('saving the settings page can turn the feed off and on', function () {
    Http::fake();

    $this->actingAs($this->admin)
        ->patch('/system/settings/general', generalPayload(['fetch_news' => false]))
        ->assertRedirect();

    expect(app(Settings::class)->get(Setting::FetchNews))->toBeFalse();

    $this->artisan('projectsend:fetch-news')->assertSuccessful();
    Http::assertNothingSent();

    $this->actingAs($this->admin)
        ->patch('/system/settings/general', generalPayload(['fetch_news' => true]))
        ->assertRedirect();

    expect(app(Settings::class)->get(Setting::FetchNews))->toBeTrue();
});

/** The general form posts every field it owns; only the interesting one varies. */
function generalPayload(array $overrides = []): array
{
    return array_merge([
        'site_name' => 'ProjectSend',
        'timezone' => 'UTC',
    ], $overrides);
}
