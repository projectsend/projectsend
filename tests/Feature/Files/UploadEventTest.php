<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Events\FileWasStored;
use App\Modules\Files\Models\File;
use App\Modules\Files\Sharing\CreateShareLink;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

/*
|--------------------------------------------------------------------------
| One seam for every upload path
|--------------------------------------------------------------------------
|
| Dispatched from StoreUploadedFile rather than from a controller, because
| that is where the chunked flow and the synchronous POST converge. A
| listener registered elsewhere — a package, say — should not have to know
| which route a file arrived by.
*/

test('storing a file announces it, whichever path stored it', function () {
    Event::fake([FileWasStored::class]);

    $this->actingAs($this->admin)->post('/files', [
        'file' => Illuminate\Http\UploadedFile::fake()->create('report.pdf', 12, 'application/pdf'),
        'name' => '',
        'description' => '',
    ])->assertRedirect();

    Event::assertDispatched(FileWasStored::class, function (FileWasStored $event): bool {
        return $event->file->original_name === 'report.pdf'
            && $event->uploader->is($this->admin);
    });
});

// The row has to be complete when a listener sees it, or a listener that
// reads the file back gets a half-built one.
test('the file it carries is already stored and readable', function () {
    $seen = null;
    Event::listen(FileWasStored::class, function (FileWasStored $event) use (&$seen): void {
        $seen = File::query()->find($event->file->id);
    });

    $this->actingAs($this->admin)->post('/files', [
        'file' => Illuminate\Http\UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'),
        'name' => '',
        'description' => '',
    ])->assertRedirect();

    expect($seen)->not->toBeNull()
        ->and($seen->uploaded_by)->toBe($this->admin->id)
        ->and(Storage::disk($seen->disk)->exists($seen->path))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Minting a link, from outside a request
|--------------------------------------------------------------------------
*/

test('it mints a long random token and never a chosen one by default', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $link = app(CreateShareLink::class)->for($file, $this->admin);

    // The token is the whole authorization for /s/{token} — there is
    // nothing behind it — so its only defence is being unguessable.
    // 32 characters is about 190 bits, more than a UUID's 122.
    expect(strlen($link->token))->toBe(32)
        ->and($link->expires_at)->toBeNull()
        ->and($link->max_downloads)->toBeNull()
        ->and($link->created_by)->toBe($this->admin->id);
});

test('two links for the same file never share a token', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $action = app(CreateShareLink::class);

    expect($action->for($file, $this->admin)->token)
        ->not->toBe($action->for($file, $this->admin)->token);
});

// The controller still owns the permission questions — whether this
// person may set an expiry or a cap is a fact about them, and the action
// has no viewer to ask.
test('the staff form still refuses an expiry to somebody without the permission', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $limited = staffWithPermissions(['upload', 'edit_files']);
    $file->forceFill(['uploaded_by' => $limited->id])->save();

    $this->actingAs($limited)->post("/files/{$file->id}/share-links", [
        'expires_at' => now()->addWeek()->toDateString(),
        'max_downloads' => 3,
    ])->assertRedirect();

    $link = $file->shareLinks()->sole();

    expect($link->expires_at)->toBeNull()
        ->and($link->max_downloads)->toBeNull();
});
