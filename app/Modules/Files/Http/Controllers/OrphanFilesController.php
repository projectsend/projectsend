<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Jobs\ImportOrphanFilesJob;
use App\Modules\Files\Models\File;
use App\Modules\Files\OrphanFileImporter;
use App\Modules\Files\OrphanFileScanner;
use App\Modules\Files\OrphanImportProgress;
use App\Modules\Files\Scanning\ScanStatus;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        private readonly OrphanFileImporter $importer,
        private readonly OrphanImportProgress $progress,
        private readonly ActivityLogger $activity,
    ) {}

    private const PER_PAGE = 25;

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $validated = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = trim($validated['search'] ?? '');

        // The mirror image of this screen, on the same screen: bytes with
        // no row, and rows with no bytes. They are the same fault seen
        // from either end, and an administrator looking into one has
        // every reason to look at the other.
        if ($request->query('tab') === 'missing') {
            return $this->missing($request);
        }

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
            'tab' => 'orphans',
            'orphans' => $paginator->items(),
            'pagination' => Pagination::meta($paginator),
            'search' => $search,
            'scanned_disks' => $this->scanner->scannedDisks(),
            'missing_count' => File::query()->where('scan_status', ScanStatus::Missing)->count(),
            'import_run' => $this->progress->current(),
        ]);
    }

    /**
     * Files this installation lists and cannot produce.
     *
     * Read from the rows rather than from the disk: the daily check
     * (projectsend:check-missing-files) has already done the comparing,
     * and repeating a full disk listing on every page load would make
     * this screen slower the worse the problem is.
     */
    private function missing(Request $request): Response
    {
        $missing = File::query()
            ->where('scan_status', ScanStatus::Missing)
            ->with('uploader')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $missing->through(fn (File $file): array => [
            'id' => $file->id,
            'name' => $file->name,
            'original_name' => $file->original_name,
            'size' => $file->size,
            'disk' => $file->disk,
            'path' => $file->path,
            'uploader' => $file->uploader?->name,
            'created_at' => $file->created_at?->toIso8601String(),
        ]);

        return Inertia::render('files/orphans', [
            'tab' => 'missing',
            'orphans' => [],
            'pagination' => Pagination::meta($missing),
            'search' => '',
            'scanned_disks' => $this->scanner->scannedDisks(),
            'missing' => $missing->items(),
            'missing_count' => $missing->total(),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        if ($request->boolean('all')) {
            return $this->importAll($request, $user);
        }

        // While a background run is adopting files, a second importer
        // could adopt the same path twice.
        if ($this->progress->isActive()) {
            $this->alreadyRunning();
        }

        $imported = 0;
        $importedFile = null;

        foreach ($this->validateItems($request)['items'] as $item) {
            // Re-validate against a fresh scan — never trust a
            // client-supplied disk/path just because an earlier scan
            // listed it.
            if (! $this->scanner->isImportable($user, $item['disk'], $item['path'])) {
                continue;
            }

            $importedFile = $this->importer->import($user, $item['disk'], $item['path']);
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

    /**
     * Every orphan the search matches, on every page. Handed to a queued
     * job: thousands of files hashed in full take minutes, and a request
     * is cut off after 30s of CPU — part-way through, with an error page
     * for an import that was in fact half done. See ImportOrphanFilesJob.
     */
    private function importAll(Request $request, User $user): RedirectResponse
    {
        $search = trim($request->validate(['search' => ['nullable', 'string', 'max:255']])['search'] ?? '');
        $search = $search !== '' ? $search : null;

        $total = count($this->scanner->importable($user, $search));

        if ($total === 0) {
            return back()->with('success', trans_choice(':count file imported.|:count files imported.', 0, ['count' => '0']));
        }

        if (! $this->progress->tryStart($total)) {
            $this->alreadyRunning();
        }

        ImportOrphanFilesJob::dispatch($user->id, $search);

        return back()->with('success', trans_choice(
            'Importing :count file in the background.|Importing :count files in the background.',
            $total,
            ['count' => (string) $total],
        ));
    }

    private function alreadyRunning(): never
    {
        throw ValidationException::withMessages(['items' => __('An import is already running. Wait for it to finish.')]);
    }

    /**
     * Polled by the orphans screen while a background run is going.
     */
    public function importStatus(): JsonResponse
    {
        return response()->json($this->progress->current());
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
}
