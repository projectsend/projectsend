<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use App\Modules\Groups\Models\Group;
use App\Modules\Platform\Captcha\Captcha;
use App\Modules\Platform\Captcha\CaptchaProvider;
use App\Modules\Platform\Captcha\CaptchaSettings;
use App\Modules\Platform\Captcha\CaptchaVerifier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

/**
 * The guest surface: reading and writing comments on a publicly-listed
 * file without an account.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::PublicListingEnabled, true);
    $this->settings->set(Setting::PublicListingSlug, 'public');
    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);
    $this->settings->set(Setting::CommentsGuestModeration, true);

    // Off unless a test switches it on — the setting store outlives the
    // per-test rollback, and so does the breaker inside the verifier.
    $this->settings->set(Setting::CaptchaProvider, 'none');
    $this->settings->set(Setting::CaptchaKeySource, 'own');
    $this->settings->set(Setting::CaptchaOnPublicComments, true);
    Captcha::forgetDisplayCache();
    CaptchaVerifier::forgetOutage();

    // A public file reachable through a public group, the way the public
    // listing actually exposes one.
    $this->group = Group::query()->create(['name' => 'Showcase', 'public' => true]);
    $this->file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    shareFileWithGroup($this->file, $this->group);
});

test('the public file page tells its theme where the comments live', function () {
    $this->get("/public/files/{$this->file->slug}")
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('public/themes/default/file')
                ->where('comments_enabled', true)
                ->where('comments_endpoint', "/public/files/{$this->file->slug}/comments")
        );
});

test('a visitor sees approved public comments and nothing else', function () {
    $client = User::factory()->client()->create();

    $public = FileComment::factory()->for($this->file)->everyone()->create(['author_id' => $this->admin->id]);
    FileComment::factory()->for($this->file)->inThreadOf($client)->create(['author_id' => $client->id]);
    FileComment::factory()->for($this->file)->onlyMe()->create(['author_id' => $this->admin->id]);
    FileComment::factory()->for($this->file)->fromGuest()->pending()->create(['body' => 'Held back']);

    $response = $this->getJson("/public/files/{$this->file->slug}/comments")->assertOk();

    expect($response->json('comments'))->toHaveCount(1)
        ->and($response->json('comments.0.id'))->toBe($public->id)
        ->and(json_encode($response->json()))->not->toContain('Held back');
});

test('a visitor can post, and the comment waits for approval', function () {
    $this->postJson("/public/files/{$this->file->slug}/comments", [
        'body' => 'Lovely photo',
        'guest_name' => 'A passer-by',
    ])->assertCreated();

    $comment = FileComment::query()->sole();

    expect($comment->isPending())->toBeTrue()
        ->and($comment->guest_name)->toBe('A passer-by')
        ->and($comment->author_id)->toBeNull()
        ->and($comment->client_context_id)->toBeNull();

    // Still invisible to the public — the author's own session is the one
    // exception, covered separately below.
    $this->flushSession();

    expect($this->getJson("/public/files/{$this->file->slug}/comments")->json('comments'))->toHaveCount(0);
});

test('a visitor must give a name', function () {
    $this->postJson("/public/files/{$this->file->slug}/comments", ['body' => 'Anonymous'])
        ->assertJsonValidationErrors('guest_name');
});

test('a visitor cannot post when public comments are switched off', function () {
    $this->settings->set(Setting::PublicCommentsEnabled, false);

    $this->postJson("/public/files/{$this->file->slug}/comments", [
        'body' => 'Hello',
        'guest_name' => 'Nobody',
    ])->assertForbidden();
});

test('a visitor cannot post when the author setting excludes them', function () {
    $this->settings->set(Setting::CommentsAuthors, 'staff_and_clients');

    $this->postJson("/public/files/{$this->file->slug}/comments", [
        'body' => 'Hello',
        'guest_name' => 'Nobody',
    ])->assertForbidden();
});

test('the endpoint does not exist for a file that is not public', function () {
    $private = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->getJson("/public/files/{$private->slug}/comments")->assertNotFound();
    $this->postJson("/public/files/{$private->slug}/comments", ['body' => 'x', 'guest_name' => 'y'])->assertNotFound();
});

test('the endpoint does not exist when commenting is switched off entirely', function () {
    $this->settings->set(Setting::CommentsScope, 'none');

    $this->getJson("/public/files/{$this->file->slug}/comments")->assertNotFound();
});

test('the endpoint does not answer under the wrong listing slug', function () {
    $this->getJson("/elsewhere/files/{$this->file->slug}/comments")->assertNotFound();
});

test('a signed-in visitor is served as themselves, not as a stranger', function () {
    $client = User::factory()->client()->create();
    $mine = FileComment::factory()->for($this->file)->inThreadOf($client)->create(['author_id' => $client->id]);
    FileComment::factory()->for($this->file)->everyone()->create(['author_id' => $this->admin->id]);

    $ids = $this->actingAs($client)->getJson("/public/files/{$this->file->slug}/comments")->json('comments.*.id');

    // Being logged in must not show you less than a stranger sees, and
    // your own thread is yours wherever you happen to be reading it.
    expect($ids)->toContain($mine->id)->toHaveCount(2);
});

test('the composer knows whether the author has an account, so the page does not have to', function () {
    // The public page serves both, and cannot tell them apart — it has no
    // viewer. Getting this from the page instead would ask a signed-in
    // reader for their name and tell them their comment is held.
    expect($this->getJson("/public/files/{$this->file->slug}/comments")->json('is_guest'))->toBeTrue();

    expect($this->actingAs(User::factory()->client()->create())
        ->getJson("/public/files/{$this->file->slug}/comments")->json('is_guest'))->toBeFalse();
});

test('the composer only promises a review when there is going to be one', function () {
    expect($this->getJson("/public/files/{$this->file->slug}/comments")->json('guest_moderated'))->toBeTrue();

    $this->settings->set(Setting::CommentsGuestModeration, false);

    // With moderation off the comment appears at once, so the notice must
    // not still say it will be reviewed.
    expect($this->getJson("/public/files/{$this->file->slug}/comments")->json('guest_moderated'))->toBeFalse();
});

test('browsing the public listing does not spend the budget for commenting', function () {
    // The bug this pins: a bare `throttle:60,1` keys on sha1(domain|ip)
    // with no route in it, so every public route shared one counter and the
    // tightest limit on it — the comment POST — decided for all of them. A
    // visitor who had opened a few pages was told "Too Many Attempts" on
    // their first comment. The named third argument is what separates them.
    // Twenty of each: comfortably inside the browsing budget, and four
    // times the whole commenting one.
    foreach (range(1, 20) as $ignored) {
        $this->get("/public/files/{$this->file->slug}")->assertOk();
        $this->getJson("/public/files/{$this->file->slug}/comments")->assertOk();
    }

    $this->postJson("/public/files/{$this->file->slug}/comments", ['body' => 'Lovely', 'guest_name' => 'Visitor'])
        ->assertCreated();
});

test('a visitor may post ten times a minute, across every public file at once', function () {
    // One bucket per visitor, not per file — which is what makes it useful
    // against a flood, and what makes ten the floor rather than five: a
    // refused attempt costs exactly as much as a posted one.
    $second = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    shareFileWithGroup($second, $this->group);

    foreach (range(1, 10) as $i) {
        // Alternating files proves the two share one bucket rather than
        // getting ten each.
        $slug = $i % 2 === 0 ? $second->slug : $this->file->slug;

        $this->postJson("/public/files/{$slug}/comments", ['body' => "Comment {$i}", 'guest_name' => 'Visitor'])
            ->assertCreated();
    }

    $this->postJson("/public/files/{$this->file->slug}/comments", ['body' => 'One too many', 'guest_name' => 'Visitor'])
        ->assertStatus(429)
        // The composer reads this header rather than the body: the
        // framework's "Too Many Attempts." has no catalogue entry and says
        // neither what happened nor when to try again.
        ->assertHeader('Retry-After');
});

test('a thread a visitor may not write to says so, instead of looking empty', function () {
    // The reported case: commenting is on for the file, so the section
    // renders, but this visitor cannot write. Without a reason the composer
    // simply vanishes and the empty list reads "No comments yet" — which
    // says nobody has commented, not that the box is shut.
    $this->settings->set(Setting::CommentsAuthors, 'staff_and_clients');

    $payload = $this->getJson("/public/files/{$this->file->slug}/comments")->assertOk();

    expect($payload->json('can_comment'))->toBeFalse()
        ->and($payload->json('cannot_comment_reason'))->toBe('Only people who are signed in can comment here.');

    $this->settings->set(Setting::CommentsAuthors, 'everyone');

    // And nothing to explain when there is nothing in the way.
    expect($this->getJson("/public/files/{$this->file->slug}/comments")->json('cannot_comment_reason'))->toBeNull();
});

test('a visitor keeps seeing their own comment while it waits for approval', function () {
    $this->postJson("/public/files/{$this->file->slug}/comments", [
        'body' => 'Lovely photo',
        'guest_name' => 'A passer-by',
    ])->assertCreated()
        // Returned in the very response to posting, so it does not look
        // like the post failed.
        ->assertJsonPath('comments.0.body', 'Lovely photo')
        ->assertJsonPath('comments.0.pending', true);

    // And still on the next request in the same session.
    expect($this->getJson("/public/files/{$this->file->slug}/comments")->json('comments'))->toHaveCount(1);
});

test('one visitor never sees another visitor\'s held comment', function () {
    $this->postJson("/public/files/{$this->file->slug}/comments", [
        'body' => 'Mine',
        'guest_name' => 'First visitor',
    ])->assertCreated();

    // A different session is a different stranger. Anything else would put
    // unapproved comments in front of the public, which is the whole thing
    // moderation exists to stop.
    $this->flushSession();

    $response = $this->getJson("/public/files/{$this->file->slug}/comments");

    expect($response->json('comments'))->toHaveCount(0)
        ->and(json_encode($response->json()))->not->toContain('Mine');
});

test('seeing your own held comment does not let you act on it', function () {
    $posted = $this->postJson("/public/files/{$this->file->slug}/comments", [
        'body' => 'Mine',
        'guest_name' => 'A passer-by',
    ])->assertCreated();

    // Visible, but not theirs to edit, delete or release.
    expect($posted->json('comments.0.can_update'))->toBeFalse()
        ->and($posted->json('comments.0.can_delete'))->toBeFalse()
        ->and($posted->json('comments.0.can_approve'))->toBeFalse();
});

test('a held comment stays out of the count a signed-in viewer sees', function () {
    $this->postJson("/public/files/{$this->file->slug}/comments", [
        'body' => 'Mine',
        'guest_name' => 'A passer-by',
    ])->assertCreated();

    $client = User::factory()->client()->create();

    // The session belongs to the visitor, not to the file — signing in
    // must not inherit it.
    expect($this->actingAs($client)->getJson("/public/files/{$this->file->slug}/comments")->json('comments'))
        ->toHaveCount(0);
});

/** Switch a CAPTCHA on for this installation. */
function protectComments(): void
{
    CaptchaSettings::for(CaptchaProvider::Turnstile)->fill([
        'site_key' => 'site-key',
        'secret_key' => 'secret-key',
    ])->save();

    app(Settings::class)->set(Setting::CaptchaProvider, CaptchaProvider::Turnstile->value);
    Captcha::forgetDisplayCache();
}

test('a visitor must solve the security check before commenting', function () {
    protectComments();

    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true, 'action' => 'comment'])]);

    $this->postJson("/public/files/{$this->file->slug}/comments", [
        'body' => 'Is this the final version?',
        'guest_name' => 'Ada',
    ])->assertStatus(422)->assertJsonValidationErrors('captcha_token');

    expect(FileComment::query()->count())->toBe(0);
    Http::assertNothingSent();

    $this->postJson("/public/files/{$this->file->slug}/comments", [
        'body' => 'Is this the final version?',
        'guest_name' => 'Ada',
        'captcha_token' => 'a-token',
    ])->assertCreated();

    expect(FileComment::query()->count())->toBe(1);
});

test('a rejected token posts nothing, and says so where the composer shows errors', function () {
    protectComments();

    Http::fake(['challenges.cloudflare.com/*' => Http::response([
        'success' => false,
        'error-codes' => ['invalid-input-response'],
    ])]);

    $this->postJson("/public/files/{$this->file->slug}/comments", [
        'body' => 'Spam',
        'guest_name' => 'Bot',
        'captcha_token' => 'a-token',
    ])->assertStatus(422)->assertJsonValidationErrors('captcha_token');

    expect(FileComment::query()->count())->toBe(0);
});

// Being signed in should never mean being asked to prove you are a person
// on a page that already knows who you are.
test('a signed-in viewer on the public page is not challenged', function () {
    protectComments();

    Http::fake();

    $client = User::factory()->client()->create();

    $this->actingAs($client)->postJson("/public/files/{$this->file->slug}/comments", [
        'body' => 'From an account',
    ])->assertCreated();

    Http::assertNothingSent();
});

test('the thread says who will be asked for a check and who will not', function () {
    protectComments();

    expect($this->getJson("/public/files/{$this->file->slug}/comments")->json('captcha_required'))->toBeTrue();

    $client = User::factory()->client()->create();

    expect($this->actingAs($client)->getJson("/public/files/{$this->file->slug}/comments")->json('captcha_required'))
        ->toBeFalse();
});

test('an unreachable provider does not silence the public thread', function () {
    protectComments();

    Http::fake(['challenges.cloudflare.com/*' => Http::failedConnection()]);

    $this->postJson("/public/files/{$this->file->slug}/comments", [
        'body' => 'Still gets through',
        'guest_name' => 'Ada',
        'captcha_token' => 'a-token',
    ])->assertCreated();
});
