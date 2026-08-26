<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\DB;

/**
 * The library query is memoised per user, because building one costs
 * several lookups per assigned client and the policies ask for it once
 * per row. These pin the two things that makes fragile — one user's
 * query reaching another, and a caller's own constraints leaking into
 * everybody else's copy — plus the count that made it worth doing.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();

    $settings = app(Settings::class);
    $settings->set(Setting::CommentsScope, 'all');
    $settings->set(Setting::CommentsAuthors, 'everyone');
});

function scopedModeratorWithLibrary(int $clients, int $filesEach): User
{
    $role = Role::query()->create(['name' => 'Scoped moderator '.Role::query()->count(), 'client_scoped' => true]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'moderate_comments'],
        ['role_id' => $role->id, 'permission' => 'upload'],
    ]);

    $manager = User::factory()->create(['role_id' => $role->id]);

    for ($i = 0; $i < $clients; $i++) {
        $client = User::factory()->client()->create();
        $manager->assignedClients()->attach($client->id);

        for ($f = 0; $f < $filesEach; $f++) {
            $file = File::factory()->public()->create(['uploaded_by' => $manager->id]);
            shareFileWith($file, $client);
            FileComment::factory()->for($file)->fromGuest()->pending()->create();
        }
    }

    return $manager;
}

test('what one scoped user may see is never handed to another', function () {
    $scope = app(StaffLibraryScope::class);

    $mine = User::factory()->client()->create();
    $theirs = User::factory()->client()->create();

    $me = scopedModeratorWithLibrary(0, 0);
    $me->assignedClients()->attach($mine->id);
    $them = scopedModeratorWithLibrary(0, 0);
    $them->assignedClients()->attach($theirs->id);

    $myFile = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($myFile, $mine);

    expect($scope->files($me)->whereKey($myFile->id)->exists())->toBeTrue()
        ->and($scope->files($them)->whereKey($myFile->id)->exists())->toBeFalse()
        // Asked again, in the other order: a memo that answered from the
        // wrong user's query would only show up on the second call.
        ->and($scope->files($them)->whereKey($myFile->id)->exists())->toBeFalse()
        ->and($scope->files($me)->whereKey($myFile->id)->exists())->toBeTrue();
});

test('what one caller adds to the query does not follow the next one', function () {
    $scope = app(StaffLibraryScope::class);

    $client = User::factory()->client()->create();
    $manager = scopedModeratorWithLibrary(0, 0);
    $manager->assignedClients()->attach($client->id);

    $first = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $second = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($first, $client);
    shareFileWith($second, $client);

    // Narrow one copy to a single file, then ask again from scratch.
    expect($scope->files($manager)->whereKey($first->id)->count())->toBe(1)
        ->and($scope->files($manager)->count())->toBe(2);
});

test('the moderation screen does not ask the library once per row', function () {
    $manager = scopedModeratorWithLibrary(clients: 5, filesEach: 5);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->actingAs($manager)->get('/comments')->assertOk();

    // Twenty-five rows, five assigned clients. Built once per request the
    // page costs about sixty queries; built per row it was 465. The bound
    // is deliberately loose — it is here to catch the shape coming back,
    // not to pin an exact number.
    expect($queries)->toBeLessThan(150);
});
