<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\OrphanFileScanner;
use App\Modules\Files\Uploads\StoreUploadedFile;
use App\Support\Pagination;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * v1-parity repair tool for the import_orphans permission: files sitting
 * on disk with no matching File row (interrupted upload, restore,
 * manual filesystem access) can be adopted in place or discarded.
 * Scans the local disk, plus external storage once it's active — see
 * OrphanFileScanner.
 */
class OrphanFilesController extends Controller
{
    public function __construct(
        private readonly OrphanFileScanner $scanner,
        private readonly StoreUploadedFile $storeFile,
        private readonly ActivityLogger $activity,
    ) {}

    private const PER_PAGE = 25;

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $validated = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = trim($validated['search'] ?? '');

        // A full disk scan (potentially thousands of entries, across
        // every scanned disk) happens once per request regardless of
        // page — Storage::allFiles() has no server-side paging of its
        // own — but only one page's worth of size()/lastModified()/
        // isAllowed() stat calls and JSON payload ever reaches the response.
        $matches = $this->scanner->scan($user, $search !== '' ? $search : null);

        $page = Paginator::resolveCurrentPage();
        $lastPage = (int) max(1, ceil(count($matches) / self::PER_PAGE));

        // A stale/guessed ?page= beyond what actually exists (e.g. after
        // importing/deleting enough rows to shrink the list, or just
        // typed by hand) would otherwise silently render an empty page
        // instead of the real last one.
        if ($page > $lastPage) {
            return redirect()->route('orphan-files.index', array_filter([
                'search' => $search !== '' ? $search : null,
                'page' => $lastPage > 1 ? $lastPage : null,
            ]));
        }

        $paginator = new LengthAwarePaginator(
            array_slice($matches, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            count($matches),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('files/orphans', [
            'orphans' => $paginator->items(),
            'pagination' => Pagination::meta($paginator),
            'search' => $search,
            'scanned_disks' => $this->scanner->scannedDisks(),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $validated = $this->validateItems($request);

        $imported = 0;
        $importedFile = null;

        foreach ($validated['items'] as $item) {
            $disk = Storage::disk($item['disk']);

            // Re-validate against a fresh scan — never trust a
            // client-supplied disk/path just because an earlier scan
            // listed it.
            if (! $this->scanner->isImportable($user, $item['disk'], $item['path'])) {
                continue;
            }

            $importedFile = $this->storeFile->create(
                uploader: $user,
                originalName: basename($item['path']),
                path: $item['path'],
                mimeType: $disk->mimeType($item['path']) ?: 'application/octet-stream',
                size: $disk->size($item['path']),
                checksum: $this->checksumOf($disk, $item['path']),
                folderId: null,
                disk: $item['disk'],
                action: Action::FileImported,
            );

            $imported++;
        }

        // A single-file import (the per-row "Import" button) goes straight
        // to the editor, same as a plain upload would — a bulk import has
        // no single file to land on, so it stays on the list.
        if ($imported === 1 && $importedFile !== null) {
            return redirect()->route('files.edit', $importedFile)->with('success', __('File imported.'));
        }

        return back()->with('success', trans_choice(
            ':count file imported.|:count files imported.',
            $imported,
            ['count' => (string) $imported],
        ));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $this->validateItems($request);

        $deleted = 0;

        foreach ($validated['items'] as $item) {
            if (! $this->scanner->isOrphan($item['disk'], $item['path'])) {
                continue;
            }

            Storage::disk($item['disk'])->delete($item['path']);

            $this->activity->log(Action::OrphanFileDeleted, $request->user(), context: ['name' => basename($item['path'])]);

            $deleted++;
        }

        return back()->with('success', trans_choice(
            ':count file deleted.|:count files deleted.',
            $deleted,
            ['count' => (string) $deleted],
        ));
    }

    /**
     * @return array{items: list<array{disk: string, path: string}>}
     */
    private function validateItems(Request $request): array
    {
        return $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.disk' => ['required', 'string', Rule::in(array_keys($this->scanner->scannedDisks()))],
            'items.*.path' => ['required', 'string'],
        ]);
    }

    /**
     * Streamed rather than hash_file() on a local path — the only way to
     * checksum a file that might live on a non-local disk (S3 has no
     * local filesystem path to hand hash_file()).
     */
    private function checksumOf(Filesystem $disk, string $path): string
    {
        $stream = $disk->readStream($path);

        if ($stream === null) {
            return '';
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }
}
