<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\CommentVisibility;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

/**
 * The author half of what DeletedClientThreadTest describes for
 * `client_context_id`: both columns are cascadeOnDelete, neither cascade
 * ever fires because users are soft-deleted, and everything that asked
 * the relation instead of the column read the survivor as absent.
 *
 * Here the absence was filled in three different ways — "guest" on the
 * two screens, "client" in the API — beside a name that stayed right,
 * and the author filter and name search dropped the comment entirely.
 */
beforeEach(function () {
    $settings = app(Settings::class);
    $settings->set(Setting::CommentsScope, 'all');
    $settings->set(Setting::CommentsAuthors, 'everyone');

    $this->admin = User::factory()->create(['name' => 'Admin']);
    $this->dana = User::factory()->create(['name' => 'Dana Staff']);
    $this->file = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Merger terms']);

    $this->comment = FileComment::factory()->for($this->file)->create([
        'author_id' => $this->dana->id,
        'body' => 'internal note',
        'visibility' => CommentVisibility::StaffOnly,
    ]);
});

/**
 * The management screen's first row, as `name / type`.
 *
 * @param  array<string, string>  $query
 */
function authorRow(array $query = []): string
{
    $row = '';

    test()->actingAs(test()->admin)
        ->get('/comments'.($query === [] ? '' : '?'.http_build_query($query)))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$row): void {
            $entry = $page->toArray()['props']['entries'][0];
            $row = $entry['author_name'].' / '.$entry['author_type'];
        });

    return $row;
}

/**
 * How many rows the screen returns for a query.
 *
 * @param  array<string, string>  $query
 */
function commentsMatching(array $query): int
{
    $n = 0;

    test()->actingAs(test()->admin)
        ->get('/comments?'.http_build_query($query))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$n): void {
            $n = count($page->toArray()['props']['entries']);
        });

    return $n;
}

test('the moderation screen keeps a deleted author\'s type', function () {
    expect(authorRow())->toBe('Dana Staff / staff');

    $this->dana->delete();

    expect(authorRow())->toBe('Dana Staff / staff');
});

test('the file\'s own comment list keeps it too', function () {
    $this->dana->delete();

    $comments = $this->actingAs($this->admin)
        ->getJson("/files/{$this->file->id}/comments")
        ->assertOk()
        ->json('comments');

    expect($comments[0]['author_name'])->toBe('Dana Staff')
        ->and($comments[0]['author_type'])->toBe('staff');
});

test('the author-type filter still finds the comment', function () {
    expect(commentsMatching(['author_type' => 'staff']))->toBe(1);

    $this->dana->delete();

    expect(commentsMatching(['author_type' => 'staff']))->toBe(1);
});

test('the name search still finds the comment', function () {
    $this->dana->delete();

    expect(commentsMatching(['search' => 'Dana']))->toBe(1);
});

// The half that must not move: a comment with no author_id is a guest's,
// and deleting accounts does not turn one into staff.
test('a guest comment is still a guest comment', function () {
    FileComment::factory()->for($this->file)->create([
        'author_id' => null,
        'guest_name' => 'A visitor',
        'body' => 'a question from outside',
        'visibility' => CommentVisibility::Everyone,
        'approved_at' => now(),
    ]);

    $this->dana->delete();

    expect(commentsMatching(['author_type' => 'guest']))->toBe(1);
});

test('the API reports a deleted staff author as staff', function () {
    $this->dana->delete();

    Sanctum::actingAs($this->admin, ['edit_others_files']);

    $author = $this->getJson("/api/v1/files/{$this->file->id}/comments")
        ->assertOk()
        ->json('data.0.author');

    expect($author['name'])->toBe('Dana Staff')
        ->and($author['type'])->toBe('staff')
        ->and($author['id'])->toBe($this->dana->id);
});
