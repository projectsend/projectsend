<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Captcha\Captcha;
use App\Modules\Platform\Captcha\CaptchaForm;
use App\Modules\Platform\Captcha\CaptchaProvider;
use App\Modules\Platform\Captcha\CaptchaSettings;
use App\Modules\Platform\Captcha\CaptchaVerifier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'staff_and_clients');
    $this->settings->set(Setting::PublicCommentsEnabled, false);
    $this->settings->set(Setting::CommentsEditWindowMinutes, 15);
});

test('a token can read a file\'s comments', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $client = User::factory()->client()->create();
    shareFileWith($file, $client);

    FileComment::factory()->for($file)->inThreadOf($client)->create(['author_id' => $client->id, 'body' => 'A question']);

    Sanctum::actingAs($this->admin, ['edit_others_files']);

    $this->getJson("/api/v1/files/{$file->id}/comments")
        ->assertOk()
        ->assertJsonPath('data.0.body', 'A question')
        ->assertJsonPath('data.0.conversation.client_id', $client->id)
        ->assertJsonPath('data.0.author.type', 'client')
        // Personal data collected for moderation, not for integrations.
        ->assertJsonMissingPath('data.0.ip_address');
});

test('a token replies into a client\'s conversation by answering their comment', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $client = User::factory()->client()->create();
    shareFileWith($file, $client);

    $question = FileComment::factory()->for($file)->inThreadOf($client)->create(['author_id' => $client->id]);

    Sanctum::actingAs($this->admin, ['edit_others_files']);

    $this->postJson("/api/v1/files/{$file->id}/comments", [
        'body' => 'Here you go',
        'visibility' => 'clients',
        'reply_to' => $question->id,
    ])->assertCreated()->assertJsonPath('data.conversation.client_id', $client->id);

    expect(FileComment::query()->latest('id')->first()->client_context_id)->toBe($client->id);
});

test('a token has no way to address a client it was not shown', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $client = User::factory()->client()->create();
    shareFileWith($file, $client);

    Sanctum::actingAs($this->admin, ['edit_others_files']);

    // No client id is accepted anywhere; a fresh comment reaches every
    // client on the file, which is the only audience on offer.
    $this->postJson("/api/v1/files/{$file->id}/comments", [
        'body' => 'Here you go',
        'visibility' => 'clients',
        'client_context_id' => $client->id,
    ])->assertCreated()->assertJsonPath('data.conversation', null);

    expect(FileComment::query()->sole()->client_context_id)->toBeNull();
});

test('a token cannot ask for a visibility the settings do not allow', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);

    Sanctum::actingAs($this->admin, ['edit_others_files']);

    $this->postJson("/api/v1/files/{$file->id}/comments", [
        'body' => 'Hello world',
        'visibility' => 'everyone',
    ])->assertForbidden();
});

test('a token without a file ability cannot reach comments at all', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    Sanctum::actingAs($this->admin, ['manage_clients']);

    $this->getJson("/api/v1/files/{$file->id}/comments")->assertForbidden();
});

test('a client-scoped token sees its own clients\' threads and not the others', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $mine = User::factory()->client()->create();
    $theirs = User::factory()->client()->create();
    shareFileWith($file, $mine);
    shareFileWith($file, $theirs);

    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->attach($mine->id);

    $visible = FileComment::factory()->for($file)->inThreadOf($mine)->create(['author_id' => $mine->id]);
    FileComment::factory()->for($file)->inThreadOf($theirs)->create(['author_id' => $theirs->id, 'body' => 'Not yours']);

    Sanctum::actingAs($manager, ['edit_files']);

    $response = $this->getJson("/api/v1/files/{$file->id}/comments")->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($visible->id)
        ->and(json_encode($response->json()))->not->toContain('Not yours')
        ->and(json_encode($response->json()))->not->toContain($theirs->name);
});

test('a client-scoped token cannot reply into a conversation outside its scope', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $mine = User::factory()->client()->create();
    $stranger = User::factory()->client()->create();
    shareFileWith($file, $mine);
    shareFileWith($file, $stranger);

    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->attach($mine->id);

    $hers = FileComment::factory()->for($file)->inThreadOf($stranger)->create(['author_id' => $stranger->id]);

    Sanctum::actingAs($manager, ['edit_files']);

    // The file is theirs to open — one of their clients has it. Her
    // comment is not, so the reply target resolves to nothing and the
    // comment lands addressed to every client rather than into hers.
    $this->postJson("/api/v1/files/{$file->id}/comments", [
        'body' => 'Hello',
        'visibility' => 'clients',
        'reply_to' => $hers->id,
    ])->assertCreated()->assertJsonPath('data.conversation', null);
});

test('a token can edit and delete its own comment, but not somebody else\'s words', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $client = User::factory()->client()->create();
    shareFileWith($file, $client);

    $mine = FileComment::factory()->for($file)->create(['author_id' => $this->admin->id]);
    $theirs = FileComment::factory()->for($file)->inThreadOf($client)->create(['author_id' => $client->id]);

    Sanctum::actingAs($this->admin, ['edit_others_files']);

    $this->patchJson("/api/v1/comments/{$mine->id}", ['body' => 'Reworded'])
        ->assertOk()->assertJsonPath('data.body', 'Reworded');

    $this->patchJson("/api/v1/comments/{$theirs->id}", ['body' => 'Rewritten'])->assertForbidden();

    // Moderation does extend to removing one, though.
    $this->deleteJson("/api/v1/comments/{$theirs->id}")->assertNoContent();
});

test('the commentable flag is readable and writable, but only under the scope that uses it', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'commentable' => false]);

    Sanctum::actingAs($this->admin, ['edit_others_files']);

    $this->patchJson("/api/v1/files/{$file->id}", ['commentable' => true])
        ->assertOk()->assertJsonPath('data.commentable', false);

    $this->settings->set(Setting::CommentsScope, 'selected');

    $this->patchJson("/api/v1/files/{$file->id}", ['commentable' => true])
        ->assertOk()->assertJsonPath('data.commentable', true);
});

test('a token with the moderation ability can list and approve what is waiting', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $pending = FileComment::factory()->for($file)->fromGuest('A passer-by')->pending()->create();
    FileComment::factory()->for($file)->everyone()->create(['author_id' => $this->admin->id]);

    Sanctum::actingAs($this->admin, ['moderate_comments']);

    $this->getJson('/api/v1/comments/pending')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pending->id)
        ->assertJsonPath('data.0.approved', false)
        ->assertJsonPath('data.0.author.type', 'guest');

    $this->postJson("/api/v1/comments/{$pending->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.approved', true);

    expect($pending->fresh()->isPending())->toBeFalse();

    // Retrying is safe: already approved, nothing changes and nothing is
    // announced a second time.
    $this->postJson("/api/v1/comments/{$pending->id}/approve")->assertOk();
    expect($this->getJson('/api/v1/comments/pending')->json('data'))->toHaveCount(0);
});

test('moderation needs its own ability, not a file one', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $pending = FileComment::factory()->for($file)->fromGuest()->pending()->create();

    Sanctum::actingAs($this->admin, ['edit_others_files']);

    $this->getJson('/api/v1/comments/pending')->assertForbidden();
    $this->postJson("/api/v1/comments/{$pending->id}/approve")->assertForbidden();
});

test('the queue is scoped by the library boundary, moderation ability or not', function () {
    $mine = User::factory()->client()->create();
    $stranger = User::factory()->client()->create();

    $ours = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $theirs = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($ours, $mine);
    shareFileWith($theirs, $stranger);

    $visible = FileComment::factory()->for($ours)->fromGuest()->pending()->create();
    $hidden = FileComment::factory()->for($theirs)->fromGuest()->pending()->create();

    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->attach($mine->id);
    RolePermission::query()->create([
        'role_id' => $manager->role_id,
        'permission' => 'moderate_comments',
    ]);

    Sanctum::actingAs($manager, ['moderate_comments']);

    expect($this->getJson('/api/v1/comments/pending')->json('data.*.id'))->toBe([$visible->id]);

    // And the boundary holds on the write, not only on the listing.
    $this->postJson("/api/v1/comments/{$hidden->id}/approve")->assertForbidden();
});

// The API authenticates with a bearer token, which is a stronger claim
// than "is a person" — so a CAPTCHA configured for the browser forms must
// never reach it. Asserted here rather than assumed, because the rule
// factory is shared and adding it to one more controller would be easy.
test('a configured captcha never applies to the API', function () {
    CaptchaSettings::for(CaptchaProvider::Turnstile)->fill([
        'site_key' => 'site-key',
        'secret_key' => 'secret-key',
    ])->save();

    $this->settings->set(Setting::CaptchaProvider, CaptchaProvider::Turnstile->value);
    foreach (CaptchaForm::cases() as $form) {
        $this->settings->set($form->setting(), true);
    }
    Captcha::forgetDisplayCache();
    CaptchaVerifier::forgetOutage();

    Http::fake();

    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $client = User::factory()->client()->create();
    shareFileWith($file, $client);

    Sanctum::actingAs($this->admin, ['edit_others_files']);

    $this->postJson("/api/v1/files/{$file->id}/comments", [
        'body' => 'From an integration',
        'visibility' => 'staff_only',
    ])->assertCreated();

    Http::assertNothingSent();

    $this->settings->set(Setting::CaptchaProvider, 'none');
    Captcha::forgetDisplayCache();
});
