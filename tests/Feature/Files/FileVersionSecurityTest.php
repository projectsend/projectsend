<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->versions = app(FileVersions::class);
});

/**
 * A client can see plenty of files they do not own. Because a revision
 * inherits the recipients of the file it revises, letting a client name a
 * merely-shared file as the original would hand their upload that file's
 * entire recipient list — every other client and group who holds it.
 *
 * FilePolicy::setVersion is the control, checked on BOTH ends inside
 * FileVersions::link(). The candidate list filters the same way, but a
 * previous_file_id can be posted directly, so these assertions go through
 * the service rather than through the picker.
 */
test('a client cannot mark their upload as a revision of a file merely shared with them', function () {
    $client = User::factory()->client()->create();

    $sharedWithThem = File::factory()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($sharedWithThem, $client);

    $theirUpload = File::factory()->create(['uploaded_by' => $client->id]);

    expect(fn () => $this->versions->link($theirUpload, $sharedWithThem, $client))
        ->toThrow(AuthorizationException::class);

    // And it must not be half-linked afterwards.
    expect($theirUpload->fresh()->previous_file_id)->toBeNull()
        ->and($theirUpload->fresh()->version_root_id)->toBeNull();
});

test('a client cannot use another client\'s file as the original', function () {
    $client = User::factory()->client()->create();
    $other = User::factory()->client()->create();

    $theirsUpload = File::factory()->create(['uploaded_by' => $other->id]);
    $mine = File::factory()->create(['uploaded_by' => $client->id]);

    expect(fn () => $this->versions->link($mine, $theirsUpload, $client))
        ->toThrow(AuthorizationException::class);
});

test('a client cannot use a staff-owned file as the original', function () {
    $client = User::factory()->client()->create();

    $staffFile = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $mine = File::factory()->create(['uploaded_by' => $client->id]);

    expect(fn () => $this->versions->link($mine, $staffFile, $client))
        ->toThrow(AuthorizationException::class);
});

test('a client cannot mark someone else\'s file as a revision of their own', function () {
    $client = User::factory()->client()->create();

    $mine = File::factory()->create(['uploaded_by' => $client->id]);
    $notMine = File::factory()->create(['uploaded_by' => $this->admin->id]);

    // The other end of the same rule: both files must be theirs.
    expect(fn () => $this->versions->link($notMine, $mine, $client))
        ->toThrow(AuthorizationException::class);
});

test('a client can link two of their own uploads', function () {
    $client = User::factory()->client()->create();

    $first = File::factory()->create(['uploaded_by' => $client->id]);
    $second = File::factory()->create(['uploaded_by' => $client->id]);

    $this->versions->link($second, $first, $client);

    expect($second->fresh()->previous_file_id)->toBe($first->id);
});

test('a client cannot link a file that is already shared with other people', function () {
    $client = User::factory()->client()->create();
    $someoneElse = User::factory()->client()->create();

    $first = File::factory()->create(['uploaded_by' => $client->id]);
    $second = File::factory()->create(['uploaded_by' => $client->id]);

    // Linking moves the subject's recipients onto the original, so allowing
    // this would let a client widen their original's audience by proxy.
    shareFileWith($second, $someoneElse);

    expect(fn () => $this->versions->link($second, $first, $client))
        ->toThrow(ValidationException::class, 'already shared with other people');
});

test('a client\'s version candidates are their own uploads only', function () {
    $client = User::factory()->client()->create();

    $mine = File::factory()->create(['uploaded_by' => $client->id, 'name' => 'My draft']);
    $shared = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'My draft']);
    shareFileWith($shared, $client);

    // Same name on purpose: an exact-match search must still not surface it.
    $ids = $this->versions->candidates(null, $client, 'My draft')->pluck('id')->all();

    expect($ids)->toBe([$mine->id]);
});

test('a client-scoped staffer cannot link to a file outside their library', function () {
    $mine = User::factory()->client()->create();
    $theirs = User::factory()->client()->create();

    // ClientManager is the client-scoped system role; a role built by
    // staffWithPermissions() is unscoped and would see everything.
    $staffer = User::factory()->role(SystemRole::ClientManager)->create();
    $staffer->assignedClients()->attach($mine->id);

    $inScope = File::factory()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($inScope, $mine);

    $outOfScope = File::factory()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($outOfScope, $theirs);

    expect(fn () => $this->versions->link($inScope, $outOfScope, $staffer))
        ->toThrow(AuthorizationException::class);
});

test('a client-scoped staffer is never offered an out-of-scope candidate', function () {
    $mine = User::factory()->client()->create();
    $theirs = User::factory()->client()->create();

    // ClientManager is the client-scoped system role; a role built by
    // staffWithPermissions() is unscoped and would see everything.
    $staffer = User::factory()->role(SystemRole::ClientManager)->create();
    $staffer->assignedClients()->attach($mine->id);

    $subject = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev D']);
    shareFileWith($subject, $mine);

    $outOfScope = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    shareFileWith($outOfScope, $theirs);

    // Searching for it by exact name must not surface it either.
    expect($this->versions->candidates($subject, $staffer, 'Rev C')->pluck('id')->all())
        ->not->toContain($outOfScope->id);
});

test('a staffer who cannot edit the original cannot link to it', function () {
    // edit_files without edit_others_files: may edit their own uploads only.
    $staffer = staffWithPermissions([Permission::EditFiles->value]);

    $mine = File::factory()->create(['uploaded_by' => $staffer->id]);
    $colleagues = File::factory()->create(['uploaded_by' => $this->admin->id]);

    // Linking moves recipients onto the original and so widens its
    // audience — a bigger act than reading it.
    expect(fn () => $this->versions->link($mine, $colleagues, $staffer))
        ->toThrow(AuthorizationException::class);
});

test('a client cannot post a previous_file_id through the file update endpoint', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    // $guarded = [] on File means these columns are mass-assignable the
    // moment they exist; the controllers must keep building $attributes
    // explicitly.
    $this->actingAs($this->admin)->patch(route('files.update', $file), [
        'name' => $file->name,
        'previous_file_id' => $original->id,
        'version_root_id' => $original->id,
    ]);

    expect($file->fresh()->previous_file_id)->toBeNull()
        ->and($file->fresh()->version_root_id)->toBeNull();
});

test('the api file update endpoint ignores previous_file_id too', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $token = $this->admin->createToken('t', [
        Permission::EditFiles->value,
        Permission::EditOthersFiles->value,
    ])->plainTextToken;

    $this->withToken($token)->patchJson("/api/v1/files/{$file->id}", [
        'previous_file_id' => $original->id,
        'version_root_id' => $original->id,
    ]);

    expect($file->fresh()->previous_file_id)->toBeNull()
        ->and($file->fresh()->version_root_id)->toBeNull();
});

test('bulk edit cannot set a version link either', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)->patch(route('files.bulk-update'), [
        'file_ids' => [$file->id],
        'folder_action' => 'no_change',
        'description_action' => 'set',
        'description' => 'touched',
        'expiration_action' => 'no_change',
        'previous_file_id' => $original->id,
    ]);

    expect($file->fresh()->description)->toBe('touched')
        ->and($file->fresh()->previous_file_id)->toBeNull();
});

test('linking never leaves a partial state when a guard rejects it', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $other = File::factory()->create(['uploaded_by' => $this->admin->id]);

    shareFileWith($revision, $client);
    $this->versions->link($other, $original, $this->admin);

    // $original is taken now, so this must fail — and must not have moved
    // $revision's assignment row on the way to failing.
    try {
        $this->versions->link($revision, $original->fresh(), $this->admin);
    } catch (ValidationException) {
        // expected
    }

    expect(FileAssignment::query()->where('file_id', $revision->id)->count())->toBe(1)
        ->and($revision->fresh()->previous_file_id)->toBeNull();
});
