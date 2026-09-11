<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\AccountContentDeletion;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GHSA-w29w-pj29-x7ww. Deleting an account that owns content makes the
 * admin choose who inherits it, and the picker narrows that list to the
 * viewer's own roster — its comment says so: "a client-scoped staff member
 * is not shown the name of somebody they can reach nothing of."
 *
 * The write asked a different question. `exists, active, not the account
 * being deleted` is true of every account on the installation, so a scoped
 * staff member could name one the picker had deliberately kept off the
 * list, and a roster client's files would land with a client on somebody
 * else's roster — readable, editable and deletable there, because a client
 * owns what they uploaded.
 *
 * The picker and the write now run one predicate, not two that agree by
 * inspection.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    $role = Role::query()->create(['name' => 'Reps '.Str::random(6), 'client_scoped' => true]);
    foreach ([Permission::DeleteClients, Permission::EditClients, Permission::CreateClients] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    $this->rep = User::factory()->create(['role_id' => $role->id]);

    // On this rep's roster, and owning something worth inheriting.
    $this->mine = User::factory()->client()->create(['name' => 'Mine']);
    $this->rep->assignedClients()->sync([$this->mine->id]);

    $this->file = File::factory()->create(['uploaded_by' => $this->mine->id]);
    $this->folder = Folder::query()->create([
        'name' => 'Theirs', 'slug' => 'theirs-'.Str::random(6), 'path' => '/', 'created_by' => $this->mine->id,
    ]);

    // On nobody's roster as far as this rep is concerned.
    $this->stranger = User::factory()->client()->create(['name' => 'Not Mine']);
});

test('the picker does not offer a client outside the roster', function () {
    // The promise the write has to keep. Asserted first so that a change
    // loosening the picker cannot quietly make the rest of this file vacuous.
    $names = collect(app(AccountContentDeletion::class)->candidates($this->rep))
        ->pluck('name');

    expect($names)->toContain('Mine')
        ->and($names)->not->toContain('Not Mine');
});

test('a scoped staff member cannot hand content to a client off their roster', function () {
    $this->actingAs($this->rep)->delete("/clients/{$this->mine->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $this->stranger->id,
    ])->assertSessionHasErrors('reassign_to_id');

    expect($this->file->fresh()->uploaded_by)->toBe($this->mine->id)
        ->and($this->folder->fresh()->created_by)->toBe($this->mine->id)
        ->and(User::withTrashed()->find($this->mine->id)->trashed())->toBeFalse();
});

test('the API twin refuses it too', function () {
    $token = $this->rep->createToken('t', [Permission::DeleteClients->value])->plainTextToken;

    $this->withToken($token)
        ->deleteJson("/api/v1/clients/{$this->mine->id}", [
            'content_action' => 'reassign',
            'reassign_to_id' => $this->stranger->id,
        ])->assertStatus(422);

    expect($this->file->fresh()->uploaded_by)->toBe($this->mine->id);
});

test('refusing reads the same as an account that is not there at all', function () {
    // Otherwise the refusal is an oracle: a scoped staff member could walk
    // the id space and learn which accounts exist outside their roster by
    // the difference between the two answers.
    $outsider = $this->actingAs($this->rep)->delete("/clients/{$this->mine->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $this->stranger->id,
    ]);

    $nobody = $this->actingAs($this->rep)->delete("/clients/{$this->mine->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => 99999999,
    ]);

    expect($outsider->getSession()->get('errors')->get('reassign_to_id'))
        ->toBe($nobody->getSession()->get('errors')->get('reassign_to_id'));
});

test('a client on the roster is still a valid target', function () {
    $alsoMine = User::factory()->client()->create(['name' => 'Also Mine']);
    $this->rep->assignedClients()->sync([$this->mine->id, $alsoMine->id]);

    $this->actingAs($this->rep)->delete("/clients/{$this->mine->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $alsoMine->id,
    ])->assertRedirect();

    expect($this->file->fresh()->uploaded_by)->toBe($alsoMine->id)
        ->and($this->folder->fresh()->created_by)->toBe($alsoMine->id);
});

test('a staff account is still a valid target, because staff are narrowed nowhere', function () {
    $this->actingAs($this->rep)->delete("/clients/{$this->mine->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $this->admin->id,
    ])->assertRedirect();

    expect($this->file->fresh()->uploaded_by)->toBe($this->admin->id);
});

test('an unscoped administrator can still reassign to anybody', function () {
    // The tightening is about the roster, and an unscoped staff member has
    // no roster — StaffLibraryScope::clients() is every client for them.
    $this->actingAs($this->admin)->delete("/clients/{$this->mine->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $this->stranger->id,
    ])->assertRedirect();

    expect($this->file->fresh()->uploaded_by)->toBe($this->stranger->id);
});

test('the advisory controls still behave as they did', function () {
    // Both were already enforced, and both must survive the new rule:
    // an inactive account, and the account being deleted.
    $inactive = User::factory()->client()->create(['active' => false]);
    $this->rep->assignedClients()->sync([$this->mine->id, $inactive->id]);

    $this->actingAs($this->rep)->delete("/clients/{$this->mine->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $inactive->id,
    ])->assertSessionHasErrors('reassign_to_id');

    $this->actingAs($this->rep)->delete("/clients/{$this->mine->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $this->mine->id,
    ])->assertSessionHasErrors('reassign_to_id');

    expect($this->file->fresh()->uploaded_by)->toBe($this->mine->id);
});
