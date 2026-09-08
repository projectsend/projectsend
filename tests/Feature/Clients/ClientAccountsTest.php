<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Clients\ClientAccounts;
use App\Modules\Clients\Notifications\ClientWelcomeNotification;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| What a client account is
|--------------------------------------------------------------------------
|
| Three surfaces make one now -- the staff screens, /api/v1/clients, and
| the platform control plane in the private package, which reaches this
| class by name because it cannot import a host class. That last one fakes
| this class entirely in its own suite, so nothing on that side can show
| that a client created through it is really a client. This file is where
| that is shown.
*/

function makeAccount(array $arguments = [])
{
    return app(ClientAccounts::class)->create(...array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'a-generated-passphrase',
    ], $arguments));
}

test('the account is a client, active, approved and verified', function () {
    $client = makeAccount();

    expect($client->type)->toBe(UserType::Client)
        ->and($client->active)->toBeTrue()
        // Created by somebody who already knows who this is: there is
        // nothing to approve and no address to confirm.
        ->and($client->account_requested)->toBeFalse()
        ->and($client->email_verified_at)->not->toBeNull()
        ->and($client->role_id)->toBe(
            Role::query()->where('name', SystemRole::Client->value)->value('id')
        );
});

test('the password is stored hashed, never as it arrived', function () {
    $client = makeAccount(['password' => 'a-generated-passphrase']);

    expect($client->password)->not->toBe('a-generated-passphrase')
        ->and(Hash::check('a-generated-passphrase', $client->password))->toBeTrue();
});

test('creating an account is written to the activity log', function () {
    $client = makeAccount();

    expect(ActivityLog::query()
        ->where('action', Action::UserCreated->value)
        ->where('subject_id', $client->id)
        ->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The quota
|--------------------------------------------------------------------------
*/

test('an omitted quota is stored as zero, which means inherit', function () {
    // 0 is not "no space" -- ClientStorageUsage::quotaMb() reads the site
    // default for it at enforcement time, which is what makes changing a
    // plan's allowance one setting rather than a sweep over every account.
    expect(makeAccount()->storage_quota_mb)->toBe(0);
});

test('a quota given is the quota stored', function () {
    expect(makeAccount(['storageQuotaMb' => 500])->storage_quota_mb)->toBe(500);
});

/*
|--------------------------------------------------------------------------
| The seat cap
|--------------------------------------------------------------------------
|
| Enforced here rather than left to each caller: the platform sets this cap
| and the platform is also what calls the control plane, so this is what
| stops a leaked control token minting accounts without limit.
*/

test('a full installation refuses to create another client', function () {
    config()->set('projectsend.platform.max_clients', 1);
    makeAccount();

    expect(fn () => makeAccount(['email' => 'grace@example.com']))
        ->toThrow(ValidationException::class);
});

test('the refusal happens before anything is written', function () {
    config()->set('projectsend.platform.max_clients', 1);
    makeAccount();

    try {
        makeAccount(['email' => 'grace@example.com']);
    } catch (ValidationException) {
        // Expected.
    }

    expect(User::query()->where('email', 'grace@example.com')->exists())->toBeFalse();
});

test('the refusal names the field the caller asked it to name', function () {
    config()->set('projectsend.platform.max_clients', 1);
    makeAccount();

    try {
        makeAccount(['email' => 'grace@example.com', 'emailField' => 'contact_email']);
        $this->fail('Expected the seat guard to refuse.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('contact_email');
    }
});

/*
|--------------------------------------------------------------------------
| The welcome
|--------------------------------------------------------------------------
*/

test('the welcome email is sent by default', function () {
    Notification::fake();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    $client = makeAccount();

    Notification::assertSentTo($client, ClientWelcomeNotification::class);
});

test('a caller that sends its own welcome can turn this one off', function () {
    // Two mails about one account read as a mistake. The portal's is the
    // one that can explain what the customer signed up for.
    Notification::fake();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    $client = makeAccount(['welcome' => false]);

    Notification::assertNotSentTo($client, ClientWelcomeNotification::class);
});

test('no welcome goes out when this installation sends no mail at all', function () {
    Notification::fake();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, false);

    $client = makeAccount();

    Notification::assertNotSentTo($client, ClientWelcomeNotification::class);
});
