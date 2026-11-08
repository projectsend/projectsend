<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\CustomAssets\CustomAssetsBridge;
use Illuminate\Http\Request;
use ProjectSend\CommunityModules\Modules\CustomAssets\Models\CustomAsset;

/*
 * A public-surface asset needs only create_assets. Nobody is signed in on
 * the sign-in screens, so they count as public, and an asset there would
 * read the administrator's password and two-factor code as they are typed.
 * The pages where a credential is typed render no asset at all.
 */

beforeEach(function () {
    config()->set('projectsend.edition', Edition::Community);
    $this->admin = User::factory()->create();

    CustomAsset::query()->create([
        'title' => 'Keylogger',
        'language' => 'js',
        'content' => 'window.__stolen = "MARKER-7f3a";',
        'surfaces' => ['public', 'portal', 'staff'],
        'position' => 'head',
        'enabled' => true,
        'created_by' => $this->admin->id,
    ]);
});

test('an asset still renders on an ordinary page', function () {
    expect(app(CustomAssetsBridge::class)->render('head', Request::create('/anything')))->toContain('MARKER-7f3a');
});

test('no asset renders where a password or code is typed', function (string $uri) {
    $this->get($uri)->assertOk()->assertDontSee('MARKER-7f3a', false);
})->with(['/login', '/forgot-password']);

test('no asset renders on the two-factor challenge or the password re-check', function () {
    $this->actingAs($this->admin)->get('/confirm-password')->assertOk()->assertDontSee('MARKER-7f3a', false);
    $this->actingAs($this->admin)->get('/settings/password')->assertOk()->assertDontSee('MARKER-7f3a', false);
});
