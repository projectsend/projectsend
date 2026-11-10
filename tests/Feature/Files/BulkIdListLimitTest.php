<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Http\Controllers\ZipDownloadsController;
use App\Modules\Files\Models\File;

/*
 * Bulk edit and bulk delete take a list of ids, each checked against the
 * policy. A selection now survives paging, so the list is bounded the way
 * a zip's is, rather than left to grow until the request falls over.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('a bulk delete or edit naming more files than the cap is refused', function () {
    $ids = range(1, ZipDownloadsController::MAX_FILES + 1);

    $this->actingAs($this->admin)->deleteJson(route('files.bulk-destroy'), ['file_ids' => $ids])
        ->assertStatus(422)->assertJsonValidationErrors('file_ids');
    $this->actingAs($this->admin)->patchJson(route('files.bulk-update'), ['file_ids' => $ids])
        ->assertStatus(422)->assertJsonValidationErrors('file_ids');
});

test('a list at the cap is still accepted', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $ids = [$file->id, ...range(1_000_000, 1_000_000 + ZipDownloadsController::MAX_FILES - 2)];

    $this->actingAs($this->admin)->delete(route('files.bulk-destroy'), ['file_ids' => $ids])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(File::query()->find($file->id))->toBeNull();
});
