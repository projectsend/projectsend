<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Jobs\SendClientUploadMail;
use App\Modules\Files\Models\File;
use App\Modules\Files\Notifications\AdminClientUploadDigestNotification;
use App\Modules\Files\Notifications\AdminClientUploadedNotification;
use App\Modules\Files\Uploads\ClientUploadMail;
use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/*
 * Issue #1840: a client uploading a folder of 200 files used to send each
 * admin address 200 emails. Now a burst of uploads is one email.
 */

beforeEach(function () {
    User::factory()->create();
    $this->client = User::factory()->client()->create(['name' => 'Acme Ltd']);

    $settings = app(Settings::class);
    $settings->set(Setting::EmailNotificationsEnabled, true);
    $settings->set(Setting::AdminNotificationEmails, ['ops@example.com', 'boss@example.com']);
});

function clientUploadMailFile(User $client, string $name): File
{
    return File::factory()->create(['uploaded_by' => $client->id, 'name' => $name]);
}

function runClientUploadMail(int $clientId): void
{
    app()->call([new SendClientUploadMail($clientId), 'handle']);
}

/** A send as it runs for real: once the client has been quiet for the debounce. */
function runClientUploadMailAfterQuiet(int $clientId): void
{
    test()->travel(ClientUploadMail::DEBOUNCE_SECONDS)->seconds();
    runClientUploadMail($clientId);
}

test('a burst of uploads becomes one email per admin address, listing every file', function () {
    Queue::fake();
    Notification::fake();

    foreach (['Drawing_001', 'Drawing_002', 'Report_001'] as $name) {
        app(ClientUploadMail::class)->queue($this->client, clientUploadMailFile($this->client, $name));
    }

    // Each upload queues a send for later — later, which is the whole
    // point — and none has gone out yet.
    Queue::assertPushed(SendClientUploadMail::class, 3);
    Queue::assertPushed(SendClientUploadMail::class, fn (SendClientUploadMail $job): bool => $job->delay !== null);
    Notification::assertNothingSent();

    runClientUploadMailAfterQuiet($this->client->id);

    Notification::assertSentOnDemandTimes(AdminClientUploadDigestNotification::class, 2);
    Notification::assertNotSentTo(Notification::route('mail', 'ops@example.com'), AdminClientUploadedNotification::class);

    // The sends queued for the other two uploads find nothing left.
    Notification::fake();
    runClientUploadMail($this->client->id);
    Notification::assertNothingSent();
});

test('a send stands down while the client is still uploading, so a long upload is still one email', function () {
    Queue::fake();
    Notification::fake();

    // A file every 90 seconds, for longer than the quiet period: a fixed
    // delay would have mailed after the first two minutes.
    for ($i = 1; $i <= 4; $i++) {
        app(ClientUploadMail::class)->queue($this->client, clientUploadMailFile($this->client, "part-{$i}"));
        $this->travel(90)->seconds();
        // The send the previous file queued comes due mid-burst.
        runClientUploadMail($this->client->id);
        if ($i < 4) {
            Notification::assertNothingSent();
        }
    }

    // 90 seconds after the last file is not yet quiet; 120 is.
    Notification::assertNothingSent();
    $this->travel(30)->seconds();
    runClientUploadMail($this->client->id);

    Notification::assertSentOnDemandTimes(AdminClientUploadDigestNotification::class, 2);
    expect(PendingNotification::query()->count())->toBe(0);
});

test('a client uploading without pause still gets an email every quarter of an hour', function () {
    Queue::fake();
    Notification::fake();

    app(ClientUploadMail::class)->queue($this->client, clientUploadMailFile($this->client, 'first'));
    $this->travel(SendClientUploadMail::MAX_HOLD_SECONDS + 1)->seconds();
    app(ClientUploadMail::class)->queue($this->client, clientUploadMailFile($this->client, 'just-now'));

    runClientUploadMail($this->client->id);

    Notification::assertSentOnDemandTimes(AdminClientUploadDigestNotification::class, 2);
});

test('a single upload still sends the single-file email', function () {
    Queue::fake();
    Notification::fake();

    $file = clientUploadMailFile($this->client, 'plan');
    app(ClientUploadMail::class)->queue($this->client, $file);
    runClientUploadMailAfterQuiet($this->client->id);

    Notification::assertSentOnDemand(
        AdminClientUploadedNotification::class,
        fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'ops@example.com',
    );
    Notification::assertSentOnDemandTimes(AdminClientUploadDigestNotification::class, 0);
});

test('nothing is collected when email is off or no address is set', function () {
    Queue::fake();
    app(Settings::class)->set(Setting::AdminNotificationEmails, []);

    app(ClientUploadMail::class)->queue($this->client, clientUploadMailFile($this->client, 'plan'));

    expect(PendingNotification::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('email switched off during the wait sends nothing, and leaves nothing behind', function () {
    Queue::fake();
    Notification::fake();

    app(ClientUploadMail::class)->queue($this->client, clientUploadMailFile($this->client, 'plan'));
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, false);
    runClientUploadMailAfterQuiet($this->client->id);

    Notification::assertNothingSent();
    expect(PendingNotification::query()->count())->toBe(0);
});

test('one client\'s uploads never land in another client\'s email', function () {
    Queue::fake();
    Notification::fake();
    $other = User::factory()->client()->create();

    app(ClientUploadMail::class)->queue($this->client, clientUploadMailFile($this->client, 'mine-1'));
    app(ClientUploadMail::class)->queue($this->client, clientUploadMailFile($this->client, 'mine-2'));
    app(ClientUploadMail::class)->queue($other, clientUploadMailFile($other, 'theirs'));

    runClientUploadMailAfterQuiet($this->client->id);

    expect(PendingNotification::query()->pluck('subject_name')->all())->toBe(['theirs']);
});

test('a file name in the list cannot plant a link', function () {
    $mail = (new AdminClientUploadDigestNotification('Acme Ltd', $this->client->id, [
        new PendingNotification(['subject_name' => '[Re-verify your account](https://attacker.example/login)']),
        new PendingNotification(['subject_name' => 'plan.pdf']),
    ]))->toMail($this->client);

    $html = (string) app(Markdown::class)->render($mail->markdown ?: 'notifications::email', array_merge($mail->toArray(), $mail->viewData));

    expect($html)->not->toContain('href="https://attacker.example')
        ->toContain('plan.pdf');
});

test('the digest is an editable template that previews', function () {
    expect(EmailTemplateSlot::AdminClientUploadDigest->placeholders())->toHaveKeys(['client', 'count']);

    $this->actingAs(User::query()->firstOrFail())
        ->get(route('email-templates.preview', EmailTemplateSlot::AdminClientUploadDigest->value))
        ->assertOk()
        ->assertSee('Drawing_001.pdf');
});

test('a queue that cannot wait still gets every email, at once', function () {
    // phpunit.xml runs the sync driver, which is exactly this case.
    Notification::fake();

    app(ClientUploadMail::class)->queue($this->client, clientUploadMailFile($this->client, 'plan'));

    Notification::assertSentOnDemand(AdminClientUploadedNotification::class);
    expect(PendingNotification::query()->count())->toBe(0);
});
