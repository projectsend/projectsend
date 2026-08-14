<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\CommentingRules;
use App\Modules\Comments\CommentVisibility;
use App\Modules\Comments\FileComments;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The settings half of the feature: which files accept comments and who
 * may write one.
 *
 * Every test here sets the settings it depends on explicitly, including
 * the ones it expects to be at their default — the Settings cache is not
 * rolled back with the database, so a value left over from an earlier test
 * would otherwise decide the outcome of this one.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->client = User::factory()->client()->create();
    $this->settings = app(Settings::class);
    $this->rules = app(CommentingRules::class);

    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'staff_and_clients');
    $this->settings->set(Setting::PublicCommentsEnabled, false);
    $this->settings->set(Setting::CommentsGuestModeration, true);
});

test('the scope setting decides which files accept comments', function (string $scope, bool $onPublic, bool $onShared, bool $onCommentable) {
    $this->settings->set(Setting::CommentsScope, $scope);

    $public = File::factory()->public()->create();
    $shared = File::factory()->create();
    $marked = File::factory()->create(['commentable' => true]);

    expect($this->rules->enabledFor($public))->toBe($onPublic)
        ->and($this->rules->enabledFor($shared))->toBe($onShared)
        ->and($this->rules->enabledFor($marked))->toBe($onCommentable);
})->with([
    // scope,          public, shared, commentable-but-not-public
    ['none', false, false, false],
    ['all', true, true, true],
    ['public_files', true, false, false],
    // A "commentable" file that is not public is still a shared file.
    ['shared_files', false, true, true],
    ['selected', false, false, true],
]);

test('the authors setting decides who may write', function (string $authors, bool $staffMay, bool $clientMay, bool $guestMay) {
    $this->settings->set(Setting::CommentsAuthors, $authors);
    // Anonymous authors are only ever possible when public comments are
    // switched on, since Everyone is the only visibility they can produce.
    $this->settings->set(Setting::PublicCommentsEnabled, true);

    $file = File::factory()->public()->create();

    expect($this->rules->canPost($this->admin, $file))->toBe($staffMay)
        ->and($this->rules->canPost($this->client, $file))->toBe($clientMay)
        ->and($this->rules->canPost(null, $file))->toBe($guestMay);
})->with([
    ['staff', true, false, false],
    ['clients', false, true, false],
    ['staff_and_clients', true, true, false],
    ['everyone', true, true, true],
]);

test('a client who cannot see the file cannot comment on it', function () {
    $file = File::factory()->create();

    expect($this->rules->canPost($this->client, $file))->toBeFalse();
});

test('a guest cannot comment on a file that is not public, even when authors is everyone', function () {
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);

    expect($this->rules->canPost(null, File::factory()->create()))->toBeFalse();
});

test('a guest cannot comment when public comments are switched off', function () {
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, false);

    expect($this->rules->canPost(null, File::factory()->public()->create()))->toBeFalse();
});

test('the public audience is only offered when the switch is on and the file is public', function () {
    $public = File::factory()->public()->create();
    $shared = File::factory()->create();

    $staffOptions = [CommentVisibility::OnlyMe, CommentVisibility::StaffOnly, CommentVisibility::Clients];

    expect($this->rules->allowedVisibilities($this->admin, $public))->toBe($staffOptions);

    $this->settings->set(Setting::PublicCommentsEnabled, true);

    expect($this->rules->allowedVisibilities($this->admin, $public))
        ->toBe([...$staffOptions, CommentVisibility::Everyone])
        // Same switch, non-public file: still not offered.
        ->and($this->rules->allowedVisibilities($this->admin, $shared))->toBe($staffOptions);
});

test('the audience list is four fixed options and never grows with the client count', function () {
    $file = File::factory()->create();

    foreach (range(1, 5) as $ignored) {
        shareFileWith($file, User::factory()->client()->create());
    }

    // The whole point of replacing the recipient picker: five clients on
    // the file, still three options for staff.
    expect($this->rules->allowedVisibilities($this->admin, $file))->toHaveCount(3);
});

test('a client is never offered the staff-only audience', function () {
    $file = File::factory()->create();
    shareFileWith($file, $this->client);

    expect($this->rules->allowedVisibilities($this->client, $file))
        ->toBe([CommentVisibility::OnlyMe, CommentVisibility::Clients])
        ->not->toContain(CommentVisibility::StaffOnly);
});

test('the client channel is called by the name of whoever is reading it', function () {
    // One channel, two ends. Staff address "Staff and clients" — the team
    // reads it too, and a label of just "Clients" read as "clients instead
    // of staff", which is how somebody concluded there was no
    // staff-and-clients option at all. A client addresses "Staff", which
    // for them is the literal truth: their comment never reaches another
    // client.
    expect(CommentVisibility::Clients->label(forStaff: true))->toBe('Staff and clients')
        ->and(CommentVisibility::Clients->label(forStaff: false))->toBe('Staff');
});

test('an anonymous author is offered the public visibility and nothing else', function () {
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);

    expect($this->rules->allowedVisibilities(null, File::factory()->public()->create()))
        ->toBe([CommentVisibility::Everyone]);
});

test('nobody is offered any visibility on a file outside the scope', function () {
    $this->settings->set(Setting::CommentsScope, 'selected');

    expect($this->rules->allowedVisibilities($this->admin, File::factory()->create()))->toBe([]);
});

test('the composer starts on the conversational audience, not the narrowest', function () {
    $file = File::factory()->create();
    shareFileWith($file, $this->client);

    // A client who never notices the dropdown must not write their
    // question to themselves — that failure is silent on both ends.
    expect($this->rules->defaultVisibility($this->client, $file))->toBe(CommentVisibility::Clients)
        ->and($this->rules->defaultVisibility($this->admin, $file))->toBe(CommentVisibility::Clients);
});

test('a visitor has one audience and it is the default', function () {
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);

    expect($this->rules->defaultVisibility(null, File::factory()->public()->create()))
        ->toBe(CommentVisibility::Everyone);
});

test('there is no default when nobody may write', function () {
    $this->settings->set(Setting::CommentsScope, 'none');

    expect($this->rules->defaultVisibility($this->admin, File::factory()->create()))->toBeNull();
});

test('an audience this file cannot have is still shown, with the reason', function () {
    $this->settings->set(Setting::PublicCommentsEnabled, false);
    $file = File::factory()->public()->create();

    $options = collect($this->rules->visibilityOptions($this->admin, $file))
        ->keyBy(fn (array $option): string => $option['visibility']->value);

    // Leaving it out is indistinguishable from the feature not existing —
    // which is how it read to the first person who went looking for it.
    expect($options)->toHaveKey('everyone')
        ->and($options['everyone']['available'])->toBeFalse()
        ->and($options['everyone']['reason'])->toBe('Public comments are turned off for this site.')
        ->and($options['clients']['available'])->toBeTrue()
        ->and($options['clients']['reason'])->toBeNull();
});

test('the reason names the site switch first, then the file', function () {
    $public = File::factory()->public()->create();
    $shared = File::factory()->create();

    $reason = fn (User $viewer, File $file): ?string => collect($this->rules->visibilityOptions($viewer, $file))
        ->firstWhere(fn (array $option): bool => $option['visibility'] === CommentVisibility::Everyone)['reason'];

    // Off site-wide: say so, because turning it on unblocks every file.
    expect($reason($this->admin, $public))->toBe('Public comments are turned off for this site.');

    $this->settings->set(Setting::PublicCommentsEnabled, true);

    // On, but this file is not public: now the file is the thing to change.
    expect($reason($this->admin, $shared))->toBe('Only a file that is publicly visible can have public comments.')
        ->and($reason($this->admin, $public))->toBeNull();
});

test('showing an unavailable audience does not make it postable', function () {
    $this->settings->set(Setting::PublicCommentsEnabled, false);
    $file = File::factory()->public()->create();

    // The composer explains the option; the service still refuses it.
    expect($this->rules->allowedVisibilities($this->admin, $file))->not->toContain(CommentVisibility::Everyone);

    app(FileComments::class)
        ->post($file, $this->admin, CommentVisibility::Everyone, 'Hello world');
})->throws(AuthorizationException::class);

test('every refusal to post comes with a sentence saying why', function () {
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);

    $public = File::factory()->public()->create();
    $shared = File::factory()->create();

    // The pairing is the invariant: canPost() is defined as "no reason to
    // refuse", so a refusal without words for it cannot exist. Silence is
    // what put "No comments yet" under a closed comment box.
    foreach ([$this->admin, $this->client, null] as $viewer) {
        foreach ([$public, $shared] as $file) {
            foreach (['none', 'all', 'public_files', 'selected'] as $scope) {
                $this->settings->set(Setting::CommentsScope, $scope);

                expect($this->rules->postingBlockedReason($viewer, $file) === null)
                    ->toBe($this->rules->canPost($viewer, $file));
            }
        }
    }
});

test('the reason names the switch that is actually in the way', function () {
    $public = File::factory()->public()->create();

    // Out of scope beats everything else: no author setting could help.
    $this->settings->set(Setting::CommentsScope, 'selected');
    expect($this->rules->postingBlockedReason($this->admin, $public))->toBe('Comments are closed on this file.');

    $this->settings->set(Setting::CommentsScope, 'all');

    // The reported case: a visitor on a public file, commenting open but
    // not to them. Signing in is a real route to commenting here, so the
    // sentence points at it rather than saying the thread is closed.
    expect($this->rules->postingBlockedReason(null, $public))->toBe('Only people who are signed in can comment here.');

    // Public comments on, but the authors setting still excludes visitors.
    $this->settings->set(Setting::PublicCommentsEnabled, true);
    expect($this->rules->postingBlockedReason(null, $public))->toBe('Only people who are signed in can comment here.');

    // Authors set to everyone, public comments off: same sentence, and
    // still true — a signed-in client may write to staff.
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, false);
    expect($this->rules->postingBlockedReason(null, $public))->toBe('Only people who are signed in can comment here.');

    $this->settings->set(Setting::CommentsAuthors, 'staff');
    expect($this->rules->postingBlockedReason($this->client, $public))->toBe('Only staff can comment here.');

    $this->settings->set(Setting::CommentsAuthors, 'clients');
    expect($this->rules->postingBlockedReason($this->admin, $public))->toBe('Only clients can comment here.');
});

test('a client is not shown the staff-only audience even as an explanation', function () {
    $file = File::factory()->create();
    shareFileWith($file, $this->client);

    $shown = collect($this->rules->visibilityOptions($this->client, $file))
        ->map(fn (array $option): string => $option['visibility']->value);

    expect($shown)->not->toContain('staff_only');
});
