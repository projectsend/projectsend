<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Clients\Notifications\ClientWelcomeNotification;
use App\Modules\Files\Notifications\FileShareDigestNotification;
use App\Modules\Files\Notifications\FileSharedNotification;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Notifications\EmailTemplate;
use App\Modules\Platform\Notifications\EmailTemplateResolver;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('the index lists every slot with its customized status', function () {
    EmailTemplate::query()->create(['slot' => EmailTemplateSlot::ClientWelcome->value, 'subject' => 'Hi', 'body' => 'Hello.']);

    $this->actingAs($this->admin)->get('/system/settings/email-templates')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/email-templates/index')
            ->has('templates', count(EmailTemplateSlot::cases()))
            ->where(
                'templates',
                fn ($templates) => collect($templates)->firstWhere('slot', 'client_welcome')['customized'] === true
                    && collect($templates)->firstWhere('slot', 'file_shared')['customized'] === false,
            ),
    );
});

test('editing a slot prefills the current effective subject and body', function () {
    $this->actingAs($this->admin)->get('/system/settings/email-templates/client_welcome')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/email-templates/edit')
            ->where('subject', 'Welcome')
            ->where('customized', false),
    );

    EmailTemplate::query()->create(['slot' => 'client_welcome', 'subject' => 'Custom subject', 'body' => 'Custom body.']);
    app(EmailTemplateResolver::class)->flush();

    $this->actingAs($this->admin)->get('/system/settings/email-templates/client_welcome')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('subject', 'Custom subject')
            ->where('body', 'Custom body.')
            ->where('customized', true),
    );
});

test('saving a template override persists it and resets removes it', function () {
    $this->actingAs($this->admin)->patch('/system/settings/email-templates/client_welcome', [
        'subject' => 'Hi there',
        'body' => 'Glad to have you.',
    ])->assertRedirect();

    $override = app(EmailTemplateResolver::class)->resolve(EmailTemplateSlot::ClientWelcome);
    expect($override)->toBe(['subject' => 'Hi there', 'body' => 'Glad to have you.']);

    $this->actingAs($this->admin)->delete('/system/settings/email-templates/client_welcome')->assertRedirect();

    expect(app(EmailTemplateResolver::class)->resolve(EmailTemplateSlot::ClientWelcome))->toBeNull();
});

test('saving a template requires both subject and body', function () {
    $this->actingAs($this->admin)->patch('/system/settings/email-templates/client_welcome', [
        'subject' => '',
        'body' => '',
    ])->assertSessionHasErrors(['subject', 'body']);
});

test('staff without the permission are forbidden from managing email templates', function () {
    $staffer = User::factory()->role(SystemRole::Uploader)->create();

    $this->actingAs($staffer)->get('/system/settings/email-templates')->assertForbidden();
    $this->actingAs($staffer)->patch('/system/settings/email-templates/client_welcome', [
        'subject' => 'x',
        'body' => 'y',
    ])->assertForbidden();
});

test('clients are sent home, never shown the email templates screen', function () {
    $this->actingAs(User::factory()->client()->create())
        ->get('/system/settings/email-templates')->assertRedirect(route('dashboard'));
});

test('a staff member granted only the templates permission can reach it', function () {
    $staffer = User::factory()->role(SystemRole::Uploader)->create();
    RolePermission::query()->create(['role_id' => $staffer->role_id, 'permission' => Permission::EditEmailTemplates->value]);

    $this->actingAs($staffer)->get('/system/settings/email-templates')->assertOk();
});

test('every slot has a working sample preview, reflecting a customized override', function () {
    EmailTemplate::query()->create([
        'slot' => EmailTemplateSlot::FileShared->value,
        'subject' => 'Custom preview subject',
        'body' => 'Custom preview body.',
    ]);

    foreach (EmailTemplateSlot::cases() as $slot) {
        $this->actingAs($this->admin)
            ->get("/system/settings/email-templates/{$slot->value}/preview")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    $this->actingAs($this->admin)
        ->get('/system/settings/email-templates/file_shared/preview')
        ->assertSee('Custom preview body.', false);
});

test('clients and unpermissioned staff cannot preview email templates', function () {
    $this->actingAs(User::factory()->client()->create())
        ->get('/system/settings/email-templates/client_welcome/preview')->assertRedirect(route('dashboard'));

    $staffer = User::factory()->role(SystemRole::Uploader)->create();
    $this->actingAs($staffer)->get('/system/settings/email-templates/client_welcome/preview')->assertForbidden();
});

test('an un-customized notification renders exactly as before', function () {
    $mail = (new FileSharedNotification('contract.pdf', false))->toMail(new User);

    expect($mail->subject)->toBe('A file has been shared with you')
        ->and($mail->introLines)->toBe(['The file "contract.pdf" has been shared with you.']);
});

test('customizing FileShared changes the rendered subject and body with placeholders substituted', function () {
    EmailTemplate::query()->create([
        'slot' => EmailTemplateSlot::FileShared->value,
        'subject' => 'New file for you',
        'body' => "Heads up!\n\nWe shared \":name\" with you.",
    ]);

    $mail = (new FileSharedNotification('contract.pdf', false))->toMail(new User);

    expect($mail->subject)->toBe('New file for you')
        ->and($mail->introLines)->toBe(['Heads up!', 'We shared "contract.pdf" with you.'])
        ->and($mail->actionText)->toBe('View your files');
});

test('an un-customized share digest lists every item after the fixed intro', function () {
    $items = [
        new PendingNotification(['subject_name' => 'contract.pdf', 'context' => ['is_folder' => false]]),
        new PendingNotification(['subject_name' => 'Reports', 'context' => ['is_folder' => true]]),
    ];

    $mail = (new FileShareDigestNotification($items))->toMail(new User);

    expect($mail->subject)->toBe('2 items have been shared with you')
        ->and($mail->introLines)->toBe([
            'The following 2 items have been shared with you:',
            'File: contract.pdf',
            'Folder: Reports',
        ])
        ->and($mail->actionText)->toBe('View your files');
});

test('customizing FileShareDigest changes the intro but the item list is still always appended', function () {
    EmailTemplate::query()->create([
        'slot' => EmailTemplateSlot::FileShareDigest->value,
        'subject' => ':count new things for you',
        'body' => "Busy day!\n\nHere's what landed (:count items):",
    ]);

    $items = [
        new PendingNotification(['subject_name' => 'contract.pdf', 'context' => ['is_folder' => false]]),
        new PendingNotification(['subject_name' => 'Reports', 'context' => ['is_folder' => true]]),
    ];

    $mail = (new FileShareDigestNotification($items))->toMail(new User);

    expect($mail->subject)->toBe('2 new things for you')
        ->and($mail->introLines)->toBe([
            'Busy day!',
            "Here's what landed (2 items):",
            'File: contract.pdf',
            'Folder: Reports',
        ]);
});

test('customizing ClientWelcome does not affect the action button', function () {
    EmailTemplate::query()->create([
        'slot' => EmailTemplateSlot::ClientWelcome->value,
        'subject' => 'Hi there',
        'body' => 'Glad to have you.',
    ]);

    $mail = (new ClientWelcomeNotification)->toMail(new User);

    expect($mail->subject)->toBe('Hi there')
        ->and($mail->introLines)->toBe(['Glad to have you.'])
        ->and($mail->actionText)->toBe('Log in');
});

test('customizing PasswordReset keeps the reset link intact', function () {
    EmailTemplate::query()->create([
        'slot' => EmailTemplateSlot::PasswordReset->value,
        'subject' => 'Reset your password',
        'body' => 'Someone asked to reset your password.',
    ]);

    $notification = new ResetPasswordNotification('the-token');
    $mail = $notification->toMail($this->admin);

    expect($mail->subject)->toBe('Reset your password')
        ->and($mail->introLines)->toBe(['Someone asked to reset your password.'])
        ->and($mail->actionText)->toBe('Reset Password')
        ->and($mail->actionUrl)->toContain('the-token');
});
