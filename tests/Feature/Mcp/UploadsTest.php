<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Files\Uploads\UploadSession;
use App\Modules\Files\Uploads\UploadTypeRestriction;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Mcp\Support\Uploads;
use App\Modules\Mcp\Tools\CompleteUpload;
use App\Modules\Mcp\Tools\CreateFileFromContent;
use App\Modules\Mcp\Tools\CreateUpload;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Tests\Support\McpWorld;

/*
 * Getting bytes in. Validate before moving a byte (spec §10, test 8),
 * the inline cap, the type and the space, the resumable session, and
 * the two idempotency promises.
 */

beforeEach(function (): void {
    McpContext::set(null);
    config()->set('app.url', 'https://acme.projectsend.site');
    Storage::fake('files');

    // Set rather than assumed: the settings cache outlives a rolled-back
    // test, so a default is whatever the last test left.
    $settings = app(Settings::class);
    $settings->set(Setting::MaxFileSizeMb, 0);
    $settings->set(Setting::UploadTypeRestriction, UploadTypeRestriction::All->value);
    $settings->set(Setting::AllowedUploadExtensions, ['pdf', 'txt', 'csv', 'md', 'png']);
});

/** A client whose quota is one megabyte, with $usedBytes of it taken. */
function mcpClientWithMegabyte(int $usedBytes): User
{
    $client = McpWorld::client();
    $client->forceFill(['storage_quota_mb' => 1])->save();

    if ($usedBytes > 0) {
        McpWorld::file($client, ['size' => $usedBytes]);
    }

    return $client;
}

/** The bytes of a part, stored as a PUT to its signed URL would store them. */
function mcpPutPart(string $uploadId, int $number, string $bytes): void
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $bytes);
    rewind($stream);

    Uploads::putPart(UploadSession::query()->findOrFail($uploadId), $number, $stream);
}

it('saves content written in the conversation as a file with its link, in one call', function (): void {
    $staff = McpWorld::staff();

    $answer = McpWorld::call(CreateFileFromContent::class, ['filename' => 'notes.txt', 'content' => "Notes\n"], $staff);

    expect(McpWorld::error($answer))->toBeNull(json_encode($answer))
        ->and($answer['file']['name'])->toBe('notes')
        ->and($answer['file']['size_bytes'])->toBe(6)
        ->and($answer['link']['url'])->toStartWith('https://acme.projectsend.site/s/')
        ->and($answer['next_steps'])->toContain('get_download_status')
        ->and(File::query()->count())->toBe(1)
        ->and(UploadSession::query()->count())->toBe(0);

    // Written as the assistant, with the connector's name beside it.
    $upload = ActivityLog::query()->where('action', 'file.uploaded')->first();
    expect($upload->origin->value)->toBe('mcp')
        ->and($upload->api_token_name)->toBe('Claude');
});

it('refuses a type the site does not accept before anything is written', function (): void {
    $staff = McpWorld::staff();

    $error = McpWorld::error(McpWorld::call(CreateFileFromContent::class, ['filename' => 'setup.exe', 'content' => 'MZ'], $staff));

    expect($error['code'])->toBe('file_type_not_allowed')
        ->and($error['extension'])->toBe('exe')
        ->and($error['allowed_extensions'])->toContain('pdf')
        ->and($error['action'])->toContain('Renaming does not help')
        ->and(UploadSession::query()->count())->toBe(0)
        ->and(File::query()->count())->toBe(0);
});

it('sends content over the inline cap to create_upload with the call already filled in', function (): void {
    config()->set('mcp-connector.inline_content_max_bytes', 100);
    $staff = McpWorld::staff();

    $error = McpWorld::error(McpWorld::call(CreateFileFromContent::class, ['filename' => 'big.txt', 'content' => str_repeat('x', 101)], $staff));

    expect($error['code'])->toBe('content_too_large_for_inline')
        ->and($error['next_call'])->toBe(['tool' => 'create_upload', 'arguments' => ['filename' => 'big.txt', 'size_bytes' => 101]])
        ->and($error['alternative_tool'])->toBe('get_web_upload_link');
});

it('tells a free account its space is full, with the free way out first and the sign-up link once', function (): void {
    $client = mcpClientWithMegabyte(1024 * 1024);

    $error = McpWorld::error(McpWorld::call(CreateFileFromContent::class, ['filename' => 'a.txt', 'content' => 'x'], $client));

    expect($error['code'])->toBe('storage_full')
        ->and($error['next_call']['tool'])->toBe('list_files')
        ->and($error['next_call']['arguments']['sort'])->toBe('size')
        ->and($error['alternative_tool'])->toBe('delete_file')
        ->and($error['upgrade_url'])->toContain('utm_content=create_file_from_content')
        ->and($error['upgrade_url'])->toContain('mcp_client=claude')
        ->and(UploadSession::query()->count())->toBe(0);
});

it('tells a free account when a file would not fit, with what is left', function (): void {
    $client = mcpClientWithMegabyte(1024 * 1024 - 100);

    $error = McpWorld::error(McpWorld::call(CreateUpload::class, ['filename' => 'a.txt', 'size_bytes' => 500], $client));

    expect($error['code'])->toBe('storage_would_exceed')
        ->and($error['remaining_bytes'])->toBe(100)
        ->and($error['upgrade_url'])->toContain('utm_content=create_upload')
        ->and(UploadSession::query()->count())->toBe(0);
});

it('leaves the sign-up link out of a storage error on a staff account', function (): void {
    $staff = McpWorld::staff();
    app(Settings::class)->set(Setting::MaxFileSizeMb, 1);

    $error = McpWorld::error(McpWorld::call(CreateUpload::class, ['filename' => 'a.txt', 'size_bytes' => 2 * 1024 * 1024], $staff));

    // The site's cap, named as such and asked before the controller is.
    expect($error['code'])->toBe('file_too_large')
        ->and($error)->not->toHaveKey('upgrade_url');
});

it('opens a resumable upload with signed part URLs, and resumes it under the same key', function (): void {
    $staff = McpWorld::staff();
    $size = 43;

    $opened = McpWorld::call(CreateUpload::class, ['filename' => 'subida.txt', 'size_bytes' => $size, 'idempotency_key' => 'up-1'], $staff);

    expect(McpWorld::error($opened))->toBeNull(json_encode($opened))
        ->and($opened['parts_total'])->toBe(1)
        ->and($opened['parts'][0]['put_url'])->toContain('/mcp/uploads/'.$opened['upload_id'].'/parts/1?')
        ->and($opened['parts'][0]['put_url'])->toContain('signature=')
        ->and($opened['received_parts'])->toBe([])
        ->and($opened['example'])->toContain('curl -X PUT');

    // Before any part arrives, completing says which are missing and how
    // to get fresh URLs for them.
    $incomplete = McpWorld::error(McpWorld::call(CompleteUpload::class, ['upload_id' => $opened['upload_id']], $staff));

    expect($incomplete['code'])->toBe('upload_incomplete')
        ->and($incomplete['missing_parts'])->toBe([1])
        ->and($incomplete['next_call']['arguments']['idempotency_key'])->toBe('up-1');

    // The part lands (as a PUT to the signed URL would store it).
    mcpPutPart($opened['upload_id'], 1, str_repeat('a', $size));

    $resumed = McpWorld::call(CreateUpload::class, ['filename' => 'subida.txt', 'size_bytes' => $size, 'idempotency_key' => 'up-1'], $staff);

    expect($resumed['upload_id'])->toBe($opened['upload_id'])
        ->and($resumed['received_parts'])->toBe([1])
        ->and($resumed['parts'])->toBe([])
        ->and(UploadSession::query()->count())->toBe(1);

    $done = McpWorld::call(CompleteUpload::class, ['upload_id' => $opened['upload_id'], 'share' => true], $staff);

    expect(McpWorld::error($done))->toBeNull(json_encode($done))
        ->and($done['file']['size_bytes'])->toBe($size)
        ->and($done['link'])->not->toBeNull()
        ->and(UploadSession::query()->count())->toBe(0);
});

it('says what a portal of their own adds when a free upload asks for a link that expires, instead of dropping it in silence', function (): void {
    $client = McpWorld::client();

    $opened = McpWorld::call(CreateUpload::class, ['filename' => 'quote.txt', 'size_bytes' => 10], $client);
    mcpPutPart($opened['upload_id'], 1, str_repeat('a', 10));

    $done = McpWorld::call(CompleteUpload::class, ['upload_id' => $opened['upload_id'], 'share' => true, 'link_expires_at' => now()->addWeek()->toDateString()], $client);

    expect(McpWorld::error($done))->toBeNull(json_encode($done))
        ->and($done['link']['expires_at'])->toBeNull()
        ->and($done['warnings'][0])->toStartWith('The expiry was ignored: links on the free plan stay until they are stopped.')
        ->and($done['upgrade_url'])->toContain('utm_content=complete_upload');
});

it('refuses the same idempotency key with different arguments', function (): void {
    $staff = McpWorld::staff();

    McpWorld::call(CreateUpload::class, ['filename' => 'a.txt', 'size_bytes' => 10, 'idempotency_key' => 'k'], $staff);
    $error = McpWorld::error(McpWorld::call(CreateUpload::class, ['filename' => 'b.txt', 'size_bytes' => 10, 'idempotency_key' => 'k'], $staff));

    expect($error['code'])->toBe('idempotency_conflict')
        ->and(UploadSession::query()->count())->toBe(1);
});

it('saves one file for one key, however many times the call is repeated', function (): void {
    $staff = McpWorld::staff();
    $arguments = ['filename' => 'notes.txt', 'content' => 'once', 'idempotency_key' => 'note-1'];

    $first = McpWorld::call(CreateFileFromContent::class, $arguments, $staff);
    $second = McpWorld::call(CreateFileFromContent::class, $arguments, $staff);

    expect($second['file']['id'])->toBe($first['file']['id'])
        ->and(File::query()->count())->toBe(1);

    $conflict = McpWorld::error(McpWorld::call(CreateFileFromContent::class, ['filename' => 'notes.txt', 'content' => 'twice', 'idempotency_key' => 'note-1'], $staff));

    expect($conflict['code'])->toBe('idempotency_conflict');
});

it('names the encoding when content cannot be decoded', function (): void {
    $staff = McpWorld::staff();

    $error = McpWorld::error(McpWorld::call(CreateFileFromContent::class, ['filename' => 'a.png', 'content' => 'not base64!!', 'encoding' => 'base64'], $staff));

    expect($error['code'])->toBe('invalid_encoding')
        ->and($error['action'])->toContain('base64');
});

it('does not delete an upload that expired or never existed twice: it says to start again', function (): void {
    $staff = McpWorld::staff();

    $error = McpWorld::error(McpWorld::call(CompleteUpload::class, ['upload_id' => '00000000-0000-0000-0000-000000000000'], $staff));

    expect($error['code'])->toBe('not_found')
        ->and($error['resource'])->toBe('upload')
        ->and($error['action'])->toContain('create_upload');
});
