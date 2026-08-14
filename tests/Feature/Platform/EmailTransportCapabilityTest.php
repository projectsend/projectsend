<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Settings\MailConfigApplier;
use App\Modules\Platform\Settings\MailProviderSettings;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('community edition keeps saving and applying the transport as before', function () {
    config()->set('projectsend.edition', Edition::Community);
    $this->actingAs($this->admin);

    $this->get('/system/settings/email')->assertInertia(
        fn (AssertableInertia $page) => $page->where('capabilities', fn (Collection $capabilities) => $capabilities->contains('email.transport.configure')),
    );

    $this->patch('/system/settings/email', validEmailSettingsPayload([
        'host' => 'smtp.community.test',
        'from_address' => 'sender@community.test',
    ]))->assertRedirect();

    expect(MailProviderSettings::current()->host)->toBe('smtp.community.test');

    app(MailConfigApplier::class)->flush();
    app(MailConfigApplier::class)->apply();

    expect(config('mail.mailers.smtp.host'))->toBe('smtp.community.test')
        ->and(config('mail.from.address'))->toBe('sender@community.test');
});

test('cloud edition ignores submitted transport fields but still saves sender identity', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    $this->actingAs($this->admin);

    $this->get('/system/settings/email')->assertInertia(
        fn (AssertableInertia $page) => $page->where('capabilities', fn (Collection $capabilities) => ! $capabilities->contains('email.transport.configure')),
    );

    $this->patch('/system/settings/email', validEmailSettingsPayload([
        'host' => 'smtp.attacker.test',
        'port' => 2525,
        'username' => 'smuggled',
        'from_address' => 'sender@cloud.test',
        'from_name' => 'Cloud Sender',
    ]))->assertRedirect();

    $stored = MailProviderSettings::current();

    expect($stored->host)->not->toBe('smtp.attacker.test')
        ->and($stored->from_address)->toBe('sender@cloud.test')
        ->and($stored->from_name)->toBe('Cloud Sender');
});

test('cloud edition never honors a stored transport host, even one written outside the form, while sender identity still applies', function () {
    config()->set('projectsend.edition', Edition::Cloud);

    $baselineHost = config('mail.mailers.smtp.host');

    // Simulate a stray/legacy row with a host set, bypassing the controller
    // entirely — the runtime gate must hold regardless of how it got there.
    MailProviderSettings::query()->create([
        'provider' => 'custom',
        'host' => 'smtp.stray-row.test',
        'port' => 2525,
        'encryption' => 'tls',
        'from_address' => 'identity@cloud.test',
        'from_name' => 'Cloud Identity',
    ]);

    app(MailConfigApplier::class)->flush();
    app(MailConfigApplier::class)->apply();

    expect(config('mail.mailers.smtp.host'))->toBe($baselineHost)
        ->and(config('mail.from.address'))->toBe('identity@cloud.test')
        ->and(config('mail.from.name'))->toBe('Cloud Identity');
});

test('sending a test email is unavailable in the cloud edition', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    $this->actingAs($this->admin);

    $this->post('/system/settings/email/test', ['recipient' => 'someone@example.test'])->assertNotFound();
});

test('sending a test email stays available in the community edition', function () {
    config()->set('projectsend.edition', Edition::Community);
    $this->actingAs($this->admin);

    $this->post('/system/settings/email/test', ['recipient' => 'someone@example.test'])->assertRedirect();
});
