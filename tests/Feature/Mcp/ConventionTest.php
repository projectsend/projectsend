<?php

declare(strict_types=1);

use App\Modules\Mcp\Instructions;
use App\Modules\Mcp\ProjectSendServer;
use App\Modules\Mcp\Prompts\OnboardClients;
use App\Modules\Mcp\Prompts\SendDeliverable;
use App\Modules\Mcp\Support\ToolError;
use Laravel\Mcp\Server\Tool;

/*
 * The tests that keep the specification from eroding (site
 * docs/mcp-harness-spec.md §10). They read what the server would send a
 * client — titles, annotations, descriptions, the error catalogue — and
 * hold it to the rules an assistant is written against.
 */

/**
 * @return list<Tool>
 */
function mcpTools(): array
{
    $classes = (new ReflectionClass(ProjectSendServer::class))->getDefaultProperties()['tools'];

    return array_map(fn (string $class): Tool => new $class, $classes);
}

/**
 * @return array<string, array<string, mixed>> the tools as `tools/list` sends them, by name
 */
function mcpToolsListed(): array
{
    $out = [];

    foreach (mcpTools() as $tool) {
        $array = $tool->toArray();
        $out[$array['name']] = $array;
    }

    return $out;
}

/**
 * The words that never appear in anything a customer reads (portal
 * docs/dormancy.md, spec §9). `upgrade now` as a phrase, the rest as
 * whole words.
 *
 * @return list<string>
 */
function mcpForbiddenWords(): array
{
    return ['unused', 'inactive', 'dormant', 'upgrade now', 'unlock', 'premium'];
}

function mcpAssertClean(string $label, string $text): void
{
    foreach (mcpForbiddenWords() as $word) {
        $pattern = str_contains($word, ' ') ? '/'.preg_quote($word, '/').'/i' : '/\b'.preg_quote($word, '/').'\b/i';

        expect(preg_match($pattern, $text))->toBe(0, "{$label} contains \"{$word}\"");
    }
}

beforeEach(function (): void {});

it('lists exactly the nineteen tools of the specification, in its order', function (): void {
    expect(array_keys(mcpToolsListed()))->toBe([
        'get_usage', 'list_files', 'get_file', 'create_upload', 'complete_upload',
        'create_file_from_content', 'get_web_upload_link', 'share_by_link', 'revoke_link',
        'get_download_status', 'delete_file', 'list_clients', 'get_client', 'create_client',
        'send_file_to_client', 'list_folders', 'create_folder', 'move_file',
        'share_folder_with_client',
    ]);
});

it('builds no zip archive, or asks for downloads.zip first', function (): void {
    // A hosted plan can withhold zip downloads (core Capability::ZipDownloads),
    // because building one holds a worker, the disk and a CPU on an
    // instance shared by thousands of accounts. Core gates its three web
    // routes; this is the same promise for the assistant's side door. No
    // tool builds a zip today. One that starts to has to check the
    // capability, or this fails.
    //
    // Every file of the module, not only the tools the server lists: a
    // tool that handed the work to a helper in Support/, or an HTTP
    // controller beside the server, would otherwise pass unread. Matched
    // on class and route names, which is why the prose in ToolError that
    // says "a zip archive" does not trip it.
    $root = dirname(__DIR__, 3).'/app/Modules/Mcp';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $read = 0;

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $read++;
        $source = (string) file_get_contents($file->getPathname());
        $buildsZips = preg_match('/ZipDownload|ZipArchive|zip-downloads/', $source) === 1;

        expect(! $buildsZips || str_contains($source, 'downloads.zip') || str_contains($source, 'ZipDownloads'))
            ->toBeTrue("{$file->getPathname()} builds zips without checking downloads.zip");
    }

    // A moved directory would otherwise leave this green over nothing.
    expect($read)->toBeGreaterThan(20);
});

it('gives every tool a title, a description an assistant can act on, and one hint', function (): void {
    // The two that create a file carry all three hints in `false`: not
    // read-only, not destructive, and not safe to repeat blindly.
    $threeFalse = ['complete_upload', 'create_file_from_content'];

    foreach (mcpToolsListed() as $name => $tool) {
        expect($tool['title'])->not->toBe('', "{$name} has no title");

        $words = str_word_count($tool['description']);
        expect($words)->toBeGreaterThanOrEqual(60, "{$name}: {$words} words")
            ->toBeLessThanOrEqual(140, "{$name}: {$words} words");

        $annotations = (array) $tool['annotations'];
        $true = array_keys(array_filter([
            'readOnlyHint' => $annotations['readOnlyHint'] ?? false,
            'destructiveHint' => $annotations['destructiveHint'] ?? false,
            'idempotentHint' => $annotations['idempotentHint'] ?? false,
        ]));

        if (in_array($name, $threeFalse, true)) {
            expect($true)->toBe([], "{$name} should carry no hint in true")
                ->and(array_keys($annotations))->toContain('readOnlyHint', 'destructiveHint', 'idempotentHint');
        } else {
            expect(count($true))->toBe(1, "{$name} carries ".count($true).' hints: '.implode(',', $true));
        }

        $properties = (array) ($tool['inputSchema']['properties'] ?? []);

        foreach ($properties as $field => $schema) {
            expect((string) ($schema['description'] ?? ''))->not->toBe('', "{$name}.{$field} has no description");
        }
    }
});

it('never says the words the product never uses', function (): void {
    mcpAssertClean('instructions', Instructions::BASE);
    mcpAssertClean('send-deliverable', SendDeliverable::TEXT);
    mcpAssertClean('onboard-clients', OnboardClients::TEXT);

    foreach (mcpToolsListed() as $name => $tool) {
        mcpAssertClean("{$name}.description", $tool['description']);

        foreach ((array) ($tool['inputSchema']['properties'] ?? []) as $field => $schema) {
            mcpAssertClean("{$name}.{$field}", (string) ($schema['description'] ?? ''));
        }
    }

    foreach (mcpErrorCatalogue() as $error) {
        mcpAssertClean($error->code, $error->action.' '.$error->message);
    }
});

it('gives every error an action and a next_call whose arguments the target tool knows', function (): void {
    $listed = mcpToolsListed();
    $codes = [];

    foreach (mcpErrorCatalogue() as $error) {
        $codes[] = $error->code;
        $array = $error->toArray()['error'];

        expect($array['action'])->not->toBe('', "{$error->code} has no action");

        if (! isset($array['next_call'])) {
            continue;
        }

        $target = $array['next_call']['tool'];
        expect(array_key_exists($target, $listed))->toBeTrue("{$error->code} points at unknown tool {$target}");

        $known = array_keys((array) ($listed[$target]['inputSchema']['properties'] ?? []));

        foreach (array_keys($array['next_call']['arguments']) as $argument) {
            expect(in_array($argument, $known, true))->toBeTrue("{$error->code}: {$target} has no argument {$argument}");
        }
    }

    // The catalogue of spec §6, every code at least once.
    expect(array_unique($codes))->toEqualCanonicalizing([
        'feature_requires_plan', 'scope_insufficient', 'permission_missing', 'not_found',
        'content_too_large_for_inline', 'file_too_large', 'file_type_not_allowed',
        'storage_would_exceed', 'storage_full', 'upload_incomplete', 'size_mismatch',
        'invalid_encoding', 'file_is_revision', 'client_email_taken', 'seats_full',
        'idempotency_conflict', 'validation_failed', 'rate_limited', 'instance_unavailable',
    ]);
});

/**
 * One of each error the catalogue can produce, with plausible arguments.
 *
 * @return list<ToolError>
 */
function mcpErrorCatalogue(): array
{
    $storage = ['used_bytes' => 2, 'used' => '2 GB', 'quota_bytes' => 2, 'quota' => '2 GB', 'percent' => 100];
    $plan = ['code' => 'pro', 'name' => 'Pro', 'price' => 'from $19/month'];

    return [
        ToolError::featureRequiresPlan('create_client', $plan, 'https://app.example/register', 'https://example/plans', ['tool' => 'share_by_link', 'arguments' => ['file_id' => 1]]),
        ToolError::featureRequiresPlan('create_folder', $plan, 'https://app.example/register', 'https://example/plans', null, 'folders'),
        ToolError::featureRequiresPlan('share_folder_with_client', $plan, 'https://app.example/register', 'https://example/plans', null, 'folders'),
        ToolError::scopeInsufficient('https://example/settings'),
        ToolError::permissionMissing('create_clients', 'https://example/files'),
        ToolError::notFound('file'),
        ToolError::notFound('folder', ['tool' => 'list_folders', 'arguments' => []]),
        ToolError::notFound('client', ['tool' => 'create_client', 'arguments' => ['email' => 'a@b.c']], 'No client has this address.'),
        ToolError::notFound('upload', ['tool' => 'create_upload', 'arguments' => ['filename' => 'a.pdf', 'size_bytes' => 10]]),
        ToolError::contentTooLargeForInline(3_000_000, 1_048_576, 'report.pdf'),
        ToolError::fileTooLarge(5, 4),
        ToolError::fileTypeNotAllowed('exe', ['pdf', 'txt']),
        ToolError::fileTypeNotAllowed('exe', null),
        ToolError::storageWouldExceed(10, 5, $storage, 'https://app.example/register'),
        ToolError::storageFull($storage, null),
        ToolError::uploadIncomplete([2, 3], [1], 3, ['tool' => 'create_upload', 'arguments' => ['filename' => 'a.pdf', 'size_bytes' => 10, 'idempotency_key' => 'k']]),
        ToolError::sizeMismatch(10, 9, 'a.pdf'),
        ToolError::invalidEncoding('base64'),
        ToolError::fileIsRevision('share_by_link', 7, 'root', []),
        ToolError::clientEmailTaken(42, 'Acme'),
        ToolError::seatsFull(25, 25, 'https://app.example'),
        ToolError::idempotencyConflict('2026-09-20T00:00:00+00:00'),
        ToolError::validationFailed(['limit' => ['too big']]),
        ToolError::rateLimited(30),
        ToolError::instanceUnavailable('https://app.example'),
    ];
}
