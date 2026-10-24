<?php

declare(strict_types=1);

use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Mcp\Tools\DeleteFile;
use App\Modules\Mcp\Tools\GetDownloadStatus;
use App\Modules\Mcp\Tools\GetFile;
use App\Modules\Mcp\Tools\GetUsage;
use App\Modules\Mcp\Tools\GetWebUploadLink;
use App\Modules\Mcp\Tools\ListFiles;
use App\Modules\Mcp\Tools\RevokeLink;
use App\Modules\Mcp\Tools\ShareByLink;
use Tests\Support\McpWorld;

/*
 * The file tools, on the happy path and at the edges: the shape every
 * success carries, links that are reused rather than minted twice,
 * options dropped with a warning when the person may not set them, the
 * warning about space, and the activity entry every write leaves.
 */

beforeEach(function (): void {
    McpContext::set(null);
    config()->set('app.url', 'https://acme.projectsend.site');
});

it('answers every success with an absolute web_url, next_steps and a warnings array', function (): void {
    $staff = McpWorld::staff();
    $file = McpWorld::file($staff);

    foreach ([
        [GetUsage::class, []],
        [ListFiles::class, []],
        [GetFile::class, ['file_id' => $file->getKey()]],
        [GetWebUploadLink::class, []],
        [ShareByLink::class, ['file_id' => $file->getKey()]],
        [GetDownloadStatus::class, ['file_id' => $file->getKey()]],
    ] as [$tool, $arguments]) {
        $answer = McpWorld::call($tool, $arguments, $staff);

        expect(McpWorld::error($answer))->toBeNull("{$tool} failed: ".json_encode($answer))
            ->and($answer['web_url'])->toStartWith('https://acme.projectsend.site/')
            ->and($answer['next_steps'])->not->toBe('')
            ->and($answer['warnings'])->toBeArray();
    }
});

it('lists files newest first with their active link and download count', function (): void {
    $staff = McpWorld::staff();
    $old = McpWorld::file($staff, ['name' => 'old', 'created_at' => now()->subDay()]);
    $new = McpWorld::file($staff, ['name' => 'new']);
    McpWorld::link($new);
    McpWorld::link($old, ['expires_at' => now()->subHour()]);

    $answer = McpWorld::call(ListFiles::class, [], $staff);

    expect($answer['total'])->toBe(2)
        ->and($answer['files'][0]['name'])->toBe('new')
        ->and($answer['files'][0]['link'])->not->toBeNull()
        ->and($answer['files'][1]['name'])->toBe('old')
        // An expired link is no link: the assistant would hand out a
        // URL that does not work.
        ->and($answer['files'][1]['link'])->toBeNull()
        ->and($answer['files'][0]['downloads_count'])->toBe(0);
});

it('finds what the person just uploaded with since, and pages by cursor', function (): void {
    $staff = McpWorld::staff();
    McpWorld::file($staff, ['name' => 'before', 'created_at' => now()->subHour(), 'updated_at' => now()->subHour()]);
    $since = now()->subMinute()->toIso8601String();
    McpWorld::file($staff, ['name' => 'after']);
    McpWorld::file($staff, ['name' => 'after-too']);

    $answer = McpWorld::call(ListFiles::class, ['since' => $since, 'limit' => 1], $staff);

    expect($answer['total'])->toBe(2)
        ->and(count($answer['files']))->toBe(1)
        ->and($answer['next_cursor'])->not->toBeNull();

    $page = McpWorld::call(ListFiles::class, ['since' => $since, 'limit' => 1, 'cursor' => $answer['next_cursor']], $staff);

    expect(count($page['files']))->toBe(1)
        ->and($page['files'][0]['name'])->not->toBe($answer['files'][0]['name']);
});

it('returns the existing plain link rather than minting a second one', function (): void {
    $staff = McpWorld::staff();
    $file = McpWorld::file($staff);
    $existing = McpWorld::link($file);

    $answer = McpWorld::call(ShareByLink::class, ['file_id' => $file->getKey()], $staff);

    expect($answer['created'])->toBeFalse()
        ->and($answer['link']['id'])->toBe($existing->getKey())
        ->and($answer['link']['url'])->toBe('https://acme.projectsend.site/s/'.$existing->token)
        ->and(ShareLink::query()->count())->toBe(1);
});

it('mints a new link when a cap is asked for, and logs it as the assistant', function (): void {
    $staff = McpWorld::staff();
    $file = McpWorld::file($staff);
    McpWorld::link($file);

    $answer = McpWorld::call(ShareByLink::class, ['file_id' => $file->getKey(), 'max_downloads' => 3], $staff);

    expect($answer['created'])->toBeTrue()
        ->and($answer['link']['max_downloads'])->toBe(3)
        ->and(ShareLink::query()->count())->toBe(2);

    $entry = ActivityLog::query()->where('action', 'share_link.created')->latest('id')->first();

    expect($entry->origin->value)->toBe('mcp')
        ->and($entry->api_token_name)->toBe('Claude');
});

it('drops an expiry the person may not set, and says so', function (): void {
    $staff = McpWorld::staff(permissions: ['upload', 'edit_files']);
    $file = McpWorld::file($staff);

    $answer = McpWorld::call(ShareByLink::class, ['file_id' => $file->getKey(), 'expires_at' => now()->addWeek()->toDateString()], $staff);

    expect(McpWorld::error($answer))->toBeNull()
        ->and($answer['link']['expires_at'])->toBeNull()
        ->and($answer['warnings'][0])->toContain('expiry');
});

it('offers a portal of their own when a free account asks for a link that stops by itself, and keeps the one link', function (): void {
    $client = McpWorld::client();
    $file = McpWorld::file($client);
    $existing = McpWorld::link($file);

    $answer = McpWorld::call(ShareByLink::class, ['file_id' => $file->getKey(), 'expires_at' => now()->addWeek()->toDateString(), 'max_downloads' => 3], $client);

    expect(McpWorld::error($answer))->toBeNull(json_encode($answer))
        // The plain link it already had, not a second one beside it.
        ->and($answer['created'])->toBeFalse()
        ->and($answer['link']['id'])->toBe($existing->getKey())
        ->and(ShareLink::query()->count())->toBe(1)
        ->and($answer['warnings'])->toHaveCount(1)
        ->and($answer['warnings'][0])->toStartWith('The expiry and the download cap were ignored')
        ->and($answer['warnings'][0])->toContain('a portal of their own (Pro, from $19/month)');

    parse_str((string) parse_url($answer['upgrade_url'], PHP_URL_QUERY), $query);

    expect($query['utm_content'])->toBe('share_by_link');
});

it('leaves the sign-up link out when a staff role is what cannot set the expiry', function (): void {
    $staff = McpWorld::staff(permissions: ['upload', 'edit_files']);
    $file = McpWorld::file($staff);

    $answer = McpWorld::call(ShareByLink::class, ['file_id' => $file->getKey(), 'expires_at' => now()->addWeek()->toDateString()], $staff);

    expect($answer)->not->toHaveKey('upgrade_url')
        ->and($answer['warnings'][0])->toBe('The expiry was ignored: this account cannot set expiry dates on links.');
});

it('refuses an expiry in the past as a validation failure on the field', function (): void {
    $staff = McpWorld::staff();
    $file = McpWorld::file($staff);

    $error = McpWorld::error(McpWorld::call(ShareByLink::class, ['file_id' => $file->getKey(), 'expires_at' => '2020-01-01'], $staff));

    expect($error['code'])->toBe('validation_failed')
        ->and($error['errors'])->toHaveKey('expires_at');
});

it('sends the assistant to the sharing root when the file is a new version', function (): void {
    $staff = McpWorld::staff();
    $root = McpWorld::file($staff, ['name' => 'contract']);
    $revision = McpWorld::file($staff, ['name' => 'contract v2', 'version_root_id' => $root->getKey()]);

    $error = McpWorld::error(McpWorld::call(ShareByLink::class, ['file_id' => $revision->getKey()], $staff));

    expect($error['code'])->toBe('file_is_revision')
        ->and($error['sharing_root_id'])->toBe($root->getKey())
        ->and($error['next_call'])->toBe(['tool' => 'share_by_link', 'arguments' => ['file_id' => $root->getKey()]]);
});

it('revokes a link by id or by its URL, and answers nothing-to-do the second time', function (): void {
    $staff = McpWorld::staff();
    $file = McpWorld::file($staff);
    $byId = McpWorld::link($file);
    $byUrl = McpWorld::link($file);

    $first = McpWorld::call(RevokeLink::class, ['link_id' => $byId->getKey()], $staff);
    $second = McpWorld::call(RevokeLink::class, ['link_url' => 'https://acme.projectsend.site/s/'.$byUrl->token.'/download'], $staff);
    $again = McpWorld::error(McpWorld::call(RevokeLink::class, ['link_id' => $byId->getKey()], $staff));

    expect($first['revoked'])->toBeTrue()
        ->and($second['revoked'])->toBeTrue()
        ->and(ShareLink::query()->count())->toBe(0)
        ->and($again['code'])->toBe('not_found')
        ->and($again['action'])->toContain('Nothing to do');
});

it('deletes a file, counts the links it took with it, and logs the deletion', function (): void {
    $staff = McpWorld::staff();
    $file = McpWorld::file($staff);
    McpWorld::link($file);
    McpWorld::link($file, ['expires_at' => now()->subDay()]);

    $answer = McpWorld::call(DeleteFile::class, ['file_id' => $file->getKey()], $staff);

    expect($answer['deleted'])->toBeTrue()
        ->and($answer['links_revoked'])->toBe(1)
        ->and(ActivityLog::query()->where('action', 'file.deleted')->exists())->toBeTrue();

    $again = McpWorld::error(McpWorld::call(DeleteFile::class, ['file_id' => $file->getKey()], $staff));

    expect($again['code'])->toBe('not_found');
});

it('reports downloads from the activity log without a single IP address', function (): void {
    $staff = McpWorld::staff();
    $file = McpWorld::file($staff);
    $client = McpWorld::clientAccount();

    ActivityLog::query()->create(['actor_id' => $client->getKey(), 'actor_name' => 'Acme Ltd', 'action' => 'file.downloaded', 'origin' => 'ui', 'ip_address' => '203.0.113.9', 'subject_type' => $file->getMorphClass(), 'subject_id' => $file->getKey(), 'subject_name' => 'informe-q3', 'created_at' => now()->subHour()]);
    ActivityLog::query()->create(['action' => 'share_link.downloaded', 'origin' => 'public', 'ip_address' => '203.0.113.10', 'subject_type' => $file->getMorphClass(), 'subject_id' => $file->getKey(), 'subject_name' => 'informe-q3', 'created_at' => now()]);

    $answer = McpWorld::call(GetDownloadStatus::class, ['file_id' => $file->getKey()], $staff);

    expect($answer['summary']['total'])->toBe(2)
        ->and($answer['summary']['distinct_clients'])->toBe(1)
        ->and($answer['downloads'][0]['via'])->toBe('link')
        ->and($answer['downloads'][0]['who'])->toBeNull()
        ->and($answer['downloads'][1]['via'])->toBe('client')
        ->and($answer['downloads'][1]['who']['name'])->toBe('Acme Ltd')
        ->and(json_encode($answer))->not->toContain('203.0.113');
});

it('warns about space once it is nearly full, in the person\'s own numbers', function (): void {
    $client = McpWorld::client();
    $client->forceFill(['storage_quota_mb' => 2048])->save();
    McpWorld::file($client, ['size' => (int) (1.7 * 1024 ** 3)]);

    $answer = McpWorld::call(ListFiles::class, [], $client);

    expect($answer['warnings'])->toHaveCount(1)
        ->and($answer['warnings'][0])->toContain('1.7 GB of its 2 GB (85%)');

    $usage = McpWorld::call(GetUsage::class, [], $client);

    expect($usage['storage']['percent'])->toBe(85)
        ->and($usage['upgrade']['url'])->toContain('utm_content=get_usage')
        ->and($usage['upgrade']['adds'])->toBe(['client accounts and team seats', 'links with an expiry date or a download cap']);
});

it('says nothing about plans to an installation that has none', function (): void {
    $staff = McpWorld::staff();

    $usage = McpWorld::call(GetUsage::class, [], $staff);

    expect(McpWorld::error($usage))->toBeNull()
        ->and($usage['plan'])->toBeNull()
        ->and($usage)->not->toHaveKey('upgrade')
        ->and($usage['account']['type'])->toBe('staff')
        ->and((new GetUsage)->description())->not->toContain('plan')
        ->and((new GetUsage)->description())->not->toContain('portal');
});

it('hands a free account its own upload page, and staff the library\'s', function (): void {
    $client = McpWorld::client();
    $staff = McpWorld::staff();

    expect(McpWorld::call(GetWebUploadLink::class, [], $client)['upload_url'])->toBe('https://acme.projectsend.site/my-files/upload')
        ->and(McpWorld::call(GetWebUploadLink::class, ['folder_id' => 4], $staff)['upload_url'])->toBe('https://acme.projectsend.site/files/upload?folder=4');
});
