<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\OfficialLinks;
use Inertia\Testing\AssertableInertia;

/**
 * Each edition has its own front door, and only one of them asks for
 * donations.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
});

function officialLinks(Edition $edition): array
{
    config()->set('projectsend.edition', $edition);
    app()->forgetInstance(App\Modules\Platform\Capabilities\CapabilityRegistry::class);

    return app(OfficialLinks::class)->toArray();
}

test('a self-hosted installation points at projectsend.org', function () {
    $links = officialLinks(Edition::Community);

    expect($links['website'])->toBe('https://www.projectsend.org/');
});

test('a managed installation points at projectsend.cloud', function () {
    $links = officialLinks(Edition::Cloud);

    expect($links['website'])->toBe('https://www.projectsend.cloud/');
});

// Omitted rather than hidden by the page: a surface added later cannot
// ask a paying customer for a donation by forgetting to check.
test('a managed installation offers no donation link at all', function () {
    expect(officialLinks(Edition::Cloud))->not->toHaveKey('open_collective')
        ->and(officialLinks(Edition::Community))->toHaveKey('open_collective');
});

test('the source and the community are the same for both', function () {
    $community = officialLinks(Edition::Community);
    $cloud = officialLinks(Edition::Cloud);

    expect($cloud['source'])->toBe($community['source'])
        ->and($cloud['discord'])->toBe($community['discord']);
});

test('the resolved links are what every page is handed', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    app()->forgetInstance(App\Modules\Platform\Capabilities\CapabilityRegistry::class);

    $this->actingAs($this->admin)->get('/system/about')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('links.website', 'https://www.projectsend.cloud/')
            ->missing('links.open_collective'),
    );
});

// The "Powered by ProjectSend" line at the foot of every outgoing message
// is where a recipient meets the product for the first time. Sending a
// hosted customer's recipients to the self-hosting instructions is the
// wrong door, and that footer reads the link straight out of Blade.
test('the mail footers resolve the link rather than reading the config', function () {
    foreach (['html', 'text'] as $format) {
        $footer = file_get_contents(resource_path("views/vendor/mail/{$format}/footer.blade.php"));

        expect($footer)->toContain('OfficialLinks')
            ->and($footer)->not->toContain("config('projectsend.links.website')");
    }
});
