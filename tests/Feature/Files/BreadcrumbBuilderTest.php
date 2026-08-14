<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\BreadcrumbBuilder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    User::factory()->create();
    $this->breadcrumbs = app(BreadcrumbBuilder::class);
});

test('no folder means no trail', function () {
    expect($this->breadcrumbs->for(null))->toBe([])
        ->and($this->breadcrumbs->visible(null, []))->toBe([]);
});

test('a root folder is a trail of one', function () {
    $root = makeFolder('Reports');

    expect($this->breadcrumbs->for($root))->toBe([['id' => $root->id, 'name' => 'Reports']]);
});

// The reason this is worth a test at all: the trail is fetched with a single
// whereIn(), which returns rows in whatever order the database likes, and is
// then put back into ancestor order by hand. A breadcrumb out of order is
// nonsense, and nothing else would notice.
test('a nested trail comes back root-first, not in whatever order the query returned', function () {
    $top = makeFolder('Clients');
    $middle = makeFolder('Acme', $top);
    $leaf = makeFolder('2026', $middle);

    expect($this->breadcrumbs->for($leaf))->toBe([
        ['id' => $top->id, 'name' => 'Clients'],
        ['id' => $middle->id, 'name' => 'Acme'],
        ['id' => $leaf->id, 'name' => '2026'],
    ]);
});

test('the visible trail starts at the first ancestor the viewer can see', function () {
    $top = makeFolder('Clients');
    $middle = makeFolder('Acme', $top);
    $leaf = makeFolder('2026', $middle);

    // Shared from "Acme" down: the client has no business seeing "Clients".
    $trail = $this->breadcrumbs->visible($leaf, [$middle->id, $leaf->id]);

    expect($trail)->toBe([
        ['id' => $middle->id, 'name' => 'Acme'],
        ['id' => $leaf->id, 'name' => '2026'],
    ]);
});

test('a viewer who can see nothing above the folder gets the folder alone', function () {
    $top = makeFolder('Clients');
    $leaf = makeFolder('Acme', $top);

    expect($this->breadcrumbs->visible($leaf, []))->toBe([
        ['id' => $leaf->id, 'name' => 'Acme'],
    ]);
});

test('a fully visible trail matches the unrestricted one', function () {
    $top = makeFolder('Clients');
    $leaf = makeFolder('Acme', $top);

    expect($this->breadcrumbs->visible($leaf, [$top->id, $leaf->id]))
        ->toBe($this->breadcrumbs->for($leaf));
});
