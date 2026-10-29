<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Mcp\Tools\CreateClient;
use App\Modules\Mcp\Tools\GetDownloadStatus;
use App\Modules\Mcp\Tools\GetFile;
use App\Modules\Mcp\Tools\ListFiles;
use App\Modules\Mcp\Tools\RevokeLink;
use App\Modules\Mcp\Tools\SendFileToClient;
use App\Modules\Mcp\Tools\ShareByLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\McpWorld;

/*
| What the tools let an account do that the web would not, found in a
| security review of the work since 2.7.0 (2026-10-28). Mostly a client's:
| the web holds a client to the links they made themselves, on files
| they uploaded (ClientShareLinks, GHSA-g9g5-qxp7-f7v2), and to their own
| files' downloads (OwnFileDownloads).
*/

beforeEach(function (): void {
    Storage::fake('files');
    McpContext::set(null);
    $this->admin = User::factory()->create();
});

function boundaryShare(File $file, User $client): void
{
    $file->assignments()->create(['assignable_type' => (new User)->getMorphClass(), 'assignable_id' => $client->id]);
}

function boundaryMayPublish(User $client): void
{
    RolePermission::query()->create(['role_id' => $client->role_id, 'permission' => 'upload_public']);
    $client->unsetRelation('role');
    app()->forgetScopedInstances();
}

it('does not show a client the links staff made, on their upload or on a file shared with them', function (): void {
    $client = McpWorld::client();
    $own = McpWorld::file($client);
    $shared = McpWorld::file($this->admin, ['name' => 'shared-with-grace']);
    boundaryShare($shared, $client);
    $onOwn = McpWorld::link($own, ['created_by' => $this->admin->id]);
    $onShared = McpWorld::link($shared, ['created_by' => $this->admin->id]);

    $own_ = McpWorld::call(GetFile::class, ['file_id' => $own->id], $client);
    $shared_ = McpWorld::call(GetFile::class, ['file_id' => $shared->id], $client);
    $list = McpWorld::call(ListFiles::class, [], $client);

    expect(json_encode([$own_, $shared_, $list]))
        ->not->toContain($onOwn->token)
        ->not->toContain($onShared->token);
});

it('makes a client their own link rather than handing them a staff one', function (): void {
    $client = McpWorld::client();
    boundaryMayPublish($client);
    $own = McpWorld::file($client);
    $staffLink = McpWorld::link($own, ['created_by' => $this->admin->id]);

    $answer = McpWorld::call(ShareByLink::class, ['file_id' => $own->id], $client);

    expect(McpWorld::error($answer))->toBeNull()
        ->and(json_encode($answer))->not->toContain($staffLink->token)
        ->and(ShareLink::query()->where('created_by', $client->id)->count())->toBe(1);
});

it('does not let a client revoke a link staff made on their upload', function (): void {
    $client = McpWorld::client();
    $own = McpWorld::file($client);
    $staffLink = McpWorld::link($own, ['created_by' => $this->admin->id]);

    $answer = McpWorld::call(RevokeLink::class, ['link_id' => $staffLink->id], $client);

    expect(McpWorld::error($answer))->not->toBeNull()
        ->and($staffLink->fresh())->not->toBeNull();
});

it('does not let a client who may not publish make a public link', function (): void {
    $client = McpWorld::client();
    $own = McpWorld::file($client);

    $answer = McpWorld::call(ShareByLink::class, ['file_id' => $own->id], $client);

    expect(McpWorld::error($answer))->not->toBeNull()
        ->and(ShareLink::query()->count())->toBe(0);
});

it('shows a client the downloads of their own files only, not who else downloaded a file shared with them', function (): void {
    $client = McpWorld::client();
    $other = McpWorld::clientAccount('other@globex.example', 'Globex Other');
    $shared = McpWorld::file($this->admin);
    boundaryShare($shared, $client);
    boundaryShare($shared, $other);
    ActivityLog::query()->create(['actor_id' => $other->id, 'actor_name' => 'Globex Other', 'action' => 'file.downloaded', 'origin' => 'ui', 'subject_type' => $shared->getMorphClass(), 'subject_id' => $shared->id, 'subject_name' => 'informe-q3', 'created_at' => now()->subHour()]);

    $answer = McpWorld::call(GetDownloadStatus::class, ['file_id' => $shared->id], $client);

    expect(json_encode($answer))->not->toContain('Globex Other');
});

it('does not let staff without upload create a file by sending content', function (): void {
    $staff = McpWorld::staff(['edit_files', 'manage_clients', 'edit_clients']);
    $client = McpWorld::clientAccount();

    $answer = McpWorld::call(SendFileToClient::class, ['client_id' => $client->id, 'filename' => 'note.txt', 'content' => 'hello'], $staff);

    expect(McpWorld::error($answer))->not->toBeNull()
        ->and(File::query()->count())->toBe(0);
});

it('keeps a new client\'s password in the cache only encrypted, for a retry to read', function (): void {
    $staff = McpWorld::staff();

    $first = McpWorld::call(CreateClient::class, ['name' => 'Initech', 'email' => 'ops@initech.example', 'idempotency_key' => 'k-1'], $staff);
    $again = McpWorld::call(CreateClient::class, ['name' => 'Initech', 'email' => 'ops@initech.example', 'idempotency_key' => 'k-1'], $staff);

    $password = $first['password_once'] ?? null;
    $cached = Cache::get('platform-control:mcp:idempotency:'.hash('sha256', 'mcp:'.$staff->id.':create_client:k-1'));

    expect($password)->toBeString()
        ->and($cached)->not->toBeNull()
        ->and(json_encode($cached))->not->toContain((string) $password)
        // The retry still gets it: the first answer may never have arrived.
        ->and($again['password_once'] ?? null)->toBe($password);
});
