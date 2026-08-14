<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Capabilities\Edition;
use ProjectSend\CommunityModules\Modules\CustomAssets\Models\CustomAsset;

/**
 * The host half of the Custom Assets integration: the root Blade view is
 * the only place these slots are emitted, and it covers all three
 * surfaces (public, portal, staff) because there is only one root view.
 */
beforeEach(function () {
    // Same contract as CustomAssetActivityLogTest: the package is not a
    // Composer dependency of this repo and CI drops private packages
    // entirely, so every test here is inapplicable without it. Skipped in
    // beforeEach rather than per-test because all five need it.
    if (! class_exists(CustomAsset::class)) {
        $this->markTestSkipped('projectsend/community-modules is not installed in this environment.');
    }

    // A staff user has to exist or EnsureSetupIsComplete redirects
    // everything to /setup.
    $this->staff = User::factory()->create();
});

function makeAsset(array $overrides = []): CustomAsset
{
    return CustomAsset::query()->create(array_merge([
        'title' => 'Snippet',
        'language' => 'js',
        'content' => 'window.__asset_marker = 1;',
        'surfaces' => ['staff'],
        'position' => 'head',
        'enabled' => true,
        'created_by' => 1,
    ], $overrides));
}

test('an enabled asset is emitted into its slot for a matching surface', function () {
    config()->set('projectsend.edition', Edition::Community);
    makeAsset(['position' => 'head', 'surfaces' => ['staff']]);

    $html = $this->actingAs($this->staff)->get('/dashboard')->assertOk()->getContent();

    expect($html)->toContain('window.__asset_marker = 1;')
        // In <head>, not somewhere else in the document.
        ->and(substr($html, 0, strpos($html, '</head>')))->toContain('window.__asset_marker = 1;');
});

test('each position lands in its own slot', function () {
    config()->set('projectsend.edition', Edition::Community);
    makeAsset(['position' => 'head', 'content' => 'HEAD_MARK', 'language' => 'html']);
    makeAsset(['position' => 'body_top', 'content' => 'TOP_MARK', 'language' => 'html']);
    makeAsset(['position' => 'body_bottom', 'content' => 'BOTTOM_MARK', 'language' => 'html']);

    $html = $this->actingAs($this->staff)->get('/dashboard')->assertOk()->getContent();

    $head = strpos($html, 'HEAD_MARK');
    $top = strpos($html, 'TOP_MARK');
    $bottom = strpos($html, 'BOTTOM_MARK');

    expect($head)->toBeLessThan(strpos($html, '</head>'))
        ->and($top)->toBeGreaterThan(strpos($html, '<body'))
        ->and($bottom)->toBeGreaterThan($top)
        ->and($bottom)->toBeLessThan(strpos($html, '</body>'));
});

test('an asset for another surface is not emitted', function () {
    config()->set('projectsend.edition', Edition::Community);
    makeAsset(['surfaces' => ['public'], 'content' => 'PUBLIC_ONLY', 'language' => 'html']);

    $html = $this->actingAs($this->staff)->get('/dashboard')->assertOk()->getContent();

    expect($html)->not->toContain('PUBLIC_ONLY');
});

test('a disabled asset is not emitted', function () {
    config()->set('projectsend.edition', Edition::Community);
    makeAsset(['enabled' => false, 'content' => 'DISABLED_MARK', 'language' => 'html']);

    $html = $this->actingAs($this->staff)->get('/dashboard')->assertOk()->getContent();

    expect($html)->not->toContain('DISABLED_MARK');
});

// The authoring gate is not the only thing keeping arbitrary markup out of
// a hosted install: a row that predates it, arrives in a restored backup,
// or is written straight to the table must still render nothing.
test('nothing is emitted in the cloud edition, even for an asset already in the table', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    makeAsset(['content' => 'LEFTOVER_MARK', 'language' => 'html']);

    $html = $this->actingAs($this->staff)->get('/dashboard')->assertOk()->getContent();

    expect($html)->not->toContain('LEFTOVER_MARK');
});
