<?php

declare(strict_types=1);

namespace App\Modules\Platform\Theming\Console;

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Files\Thumbnails\ThumbnailGenerator;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\UserType;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates (or refreshes) a single stable client account with a handful of
 * groups, a shared folder, and files — real decodable images among them, so
 * every theme's thumbnail rendering has something genuine to show — solely
 * so `theming:screenshot-themes` (see docs/theming-files-checklist.md) has
 * non-empty content to photograph. An empty "My files" renders nearly
 * identically across every theme (only the outer container width differs),
 * which is exactly what caused a false "themes all look the same" report.
 *
 * Idempotent: reruns update the same client/groups/folder/files rather than
 * duplicating them, so this is safe to run again after adding a theme.
 */
class GenerateThemePreviewDataCommand extends Command
{
    protected $signature = 'theming:preview-data
        {--password= : Client password (defaults to the demo account\'s own email address)}
        {--photos=24 : How many photographs the demo library should hold}
        {--refresh-photos : Download every photograph again, even ones already on disk}
        {--offline : Skip downloading and draw placeholders instead}
        {--force : Allow running in a production environment}';

    protected $description = 'Create/refresh the demo client used to generate theme preview screenshots';

    private const EMAIL = 'theme-preview@projectsend.test';

    /**
     * Real photographs, by stable id so a given name always yields the same
     * picture — reruns then leave the committed preview PNGs alone instead
     * of churning them for no reason.
     *
     * Picsum serves Unsplash-licensed photographs: free for commercial use
     * with no attribution required, which matters because these end up
     * inside screenshots shipped to every installation. LoremFlickr was the
     * other candidate and was rejected for exactly that reason — its Flickr
     * images are mostly CC-BY, and an attribution requirement is not
     * something a preview thumbnail can satisfy.
     *
     * Ids rather than random seeds because a seed's subject cannot be
     * predicted: seeding produced a perfectly real but incoherent library —
     * portraits of strangers among the scenery — which is not what a file
     * library looks like in a product screenshot. These ids were picked by
     * eye from Picsum's catalogue and are all landscapes.
     */
    private const PHOTO_URL = 'https://picsum.photos/id/%d/1200/800';

    /**
     * Named rather than numbered because the names are visible in every
     * screenshot: "Harbor View" reads as somebody's real library, "Image 7"
     * reads as test data, and these previews are the first thing an
     * administrator sees of a theme. Each name is paired with the photograph
     * it actually describes — a beach filed under "Desert Dunes" looks like
     * exactly the carelessness these screenshots are meant to disprove.
     *
     * @var array<string, int>
     */
    private const PHOTOS = [
        'Harbor View' => 77,
        'Pier At Dusk' => 100,
        'Glacier Edge' => 79,
        'Redwood Grove' => 81,
        'Coastal Path' => 10,
        'Meadow Light' => 93,
        'Volcanic Ridge' => 66,
        'Northern Reach' => 67,
        'Prairie Fields' => 85,
        'Canyon Road' => 118,
        'River Bend' => 11,
        'Highland Pass' => 121,
        'Sunset Fields' => 110,
        'Rolling Hills' => 62,
        'Misty Avenue' => 70,
        'Forest Line' => 83,
        'Old Town' => 61,
        'Harbour Bridge' => 84,
        'Wheat Field' => 107,
        'Palm Coast' => 108,
        'Reed Beds' => 112,
        'Cypress Hills' => 116,
        'City Reflections' => 122,
        'Bay Skyline' => 74,
        'Winter Oak' => 95,
        'Open Grassland' => 98,
        'Railway Cut' => 69,
        'Autumn Trail' => 103,
    ];

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to create demo data in production without --force.');

            return self::FAILURE;
        }

        // Defaults to the account's own email rather than a fresh random
        // string: this is a throwaway demo client on a dev install, and a
        // rerun that silently changed the password meant every screenshot or
        // browser check afterwards had to be handed the new one.
        $password = $this->option('password') ?? self::EMAIL;

        $clientRoleId = Role::query()->where('name', SystemRole::Client->value)->value('id');

        $client = User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'type' => UserType::Client,
                'name' => 'Theme Preview Client',
                'role_id' => $clientRoleId,
                'password' => $password,
                'active' => true,
                'email_verified_at' => now(),
            ],
        );

        $groups = collect([
            'Marketing Team' => true,
            'Product Design' => true,
            'Executive Reports' => false,
        ])->map(function (bool $isMember, string $name) use ($client): Group {
            $group = Group::query()->firstOrCreate(['name' => $name], ['description' => "Demo group: {$name}"]);
            if ($isMember) {
                $group->members()->syncWithoutDetaching([$client->id => ['created_at' => now(), 'updated_at' => now()]]);
            }

            return $group;
        });

        $folders = app(FolderService::class);
        $brandKit = Folder::query()->where('name', 'Brand Kit')->whereNull('parent_id')->first()
            ?? tap($folders->create('Brand Kit', null), fn (Folder $f) => $f->forceFill(['created_by' => $client->id])->save());

        FolderAssignment::query()->firstOrCreate([
            'folder_id' => $brandKit->id,
            'assignable_type' => (new User)->getMorphClass(),
            'assignable_id' => $client->id,
        ]);

        $categories = collect(['Marketing', 'Finance', 'Design'])
            ->map(fn (string $name): Category => Category::query()->firstOrCreate(['name' => $name]));

        $disk = Storage::disk('files');

        $wanted = max(1, (int) $this->option('photos'));
        $images = array_slice(self::PHOTOS, 0, min($wanted, count(self::PHOTOS)), true);

        if ($wanted > count(self::PHOTOS)) {
            $this->warn(sprintf(
                'Only %d photographs are curated, so that is how many you get. Add more to PHOTOS.',
                count(self::PHOTOS),
            ));
        }

        $documents = ['Q3 Report', 'Contract Draft', 'Brand Guidelines', 'Invoice 1042'];

        $i = 0;
        $drawn = 0;
        $bar = $this->output->createProgressBar(count($images));
        $bar->start();

        foreach ($images as $name => $photoId) {
            $category = $categories[$i % $categories->count()];
            assert($category instanceof Category);

            $relativePath = $this->pathFor($name, 'jpg');
            $bytes = $this->photoBytes($disk, $relativePath, $photoId);

            if ($bytes === null) {
                // One placeholder is better than one missing file, but it is
                // still the thing this command exists to avoid, so it is
                // counted and reported rather than passed over quietly.
                $bytes = $this->generatePng($name, [90 + ($i * 37) % 140, 110 + ($i * 61) % 120, 140 + ($i * 23) % 100]);
                $drawn++;
            }

            $this->upsertDemoFile(
                client: $client,
                name: $name,
                originalName: Str::slug($name).'.jpg',
                mimeType: 'image/jpeg',
                bytes: $bytes,
                disk: $disk,
                folderId: $i % 4 === 0 ? $brandKit->id : null,
                category: $category,
            );
            $i++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        foreach ($documents as $name) {
            $category = $categories[$i % $categories->count()];
            assert($category instanceof Category);

            $this->upsertDemoFile(
                client: $client,
                name: $name,
                originalName: Str::slug($name).'.pdf',
                mimeType: 'application/pdf',
                bytes: "%PDF-1.4\n% Demo placeholder for theme preview screenshots, not a real PDF.\n",
                disk: $disk,
                folderId: null,
                category: $category,
            );
            $i++;
        }

        $this->pruneStaleDemoFiles($disk, array_merge(
            array_map(fn (string $n): string => $this->pathFor($n, 'jpg'), array_keys($images)),
            array_map(fn (string $n): string => $this->pathFor($n, 'pdf'), $documents),
        ));

        $detached = $this->hideUnreadableFiles($disk, $client, $brandKit);

        $this->info('Demo client ready for theme screenshots:');
        $this->line('  Email:    '.self::EMAIL);
        $this->line('  Password: '.$password);
        $this->line('  Groups:   '.$groups->keys()->implode(', '));
        $this->line('  Files:    '.(count($images) + count($documents)).' (in "Brand Kit" folder + loose)');
        $this->line('  Photos:   '.(count($images) - $drawn).' real, '.$drawn.' drawn');

        if ($detached > 0) {
            $this->line('  Hidden:   '.$detached.' file(s) whose bytes are missing (left in place, just not shown here)');
        }

        if ($drawn > 0) {
            // Loud, because the screenshots will still be produced and will
            // still look plausible at a glance — a drawn rectangle only
            // announces itself once it is on the settings page of every
            // installation.
            $this->warn(sprintf(
                '%d photograph(s) could not be downloaded, so placeholders were drawn instead. '
                .'Do not commit screenshots taken from these — rerun with a working connection.',
                $drawn,
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function upsertDemoFile(
        User $client,
        string $name,
        string $originalName,
        string $mimeType,
        string $bytes,
        Filesystem $disk,
        ?int $folderId,
        Category $category,
    ): void {
        $relativePath = $this->pathFor($name, pathinfo($originalName, PATHINFO_EXTENSION));
        $disk->put($relativePath, $bytes);

        $file = File::query()->updateOrCreate(
            ['path' => $relativePath],
            [
                'uploaded_by' => $client->id,
                'folder_id' => $folderId,
                'name' => $name,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size' => strlen($bytes),
                'checksum' => hash('sha256', $bytes),
                'description' => null,
                'public' => false,
            ],
        );

        // Thumbnails are cached per file id, and this command is the only
        // thing in the application that replaces a file's bytes underneath
        // an existing row. Without this the cache keeps serving the picture
        // that used to be there — which produced a screenshot showing a
        // portrait of a stranger under the name "Highland Pass".
        foreach (ThumbnailGenerator::pathsFor($file->id, $mimeType) as $cached) {
            $disk->delete($cached);
        }

        $file->categories()->syncWithoutDetaching([$category->id]);

        if ($folderId === null) {
            FileAssignment::query()->firstOrCreate([
                'file_id' => $file->id,
                'assignable_type' => (new User)->getMorphClass(),
                'assignable_id' => $client->id,
            ]);
        }
    }

    /**
     * Take anything the demo client cannot actually render out of its view.
     *
     * A file row whose bytes have gone missing is invisible to every check
     * except the one that matters: it renders as a broken-image icon, and a
     * screenshot full of those looks plausible enough in a terminal to get
     * committed. This happened — 22 of the 52 files this account could see
     * had lost their bytes, and the capture before this check existed
     * looked fine only because the good ones happened to sort first.
     *
     * Unassigned rather than deleted. These rows are not this command's to
     * destroy: they may be shared with other accounts, and something that
     * silently deletes files is a much worse thing to have in a codebase
     * than a demo library with a gap in it.
     */
    private function hideUnreadableFiles(Filesystem $disk, User $client, Folder $brandKit): int
    {
        $visible = File::query()
            ->where('disk', 'files')
            ->where(function ($query) use ($client, $brandKit): void {
                $query->where('folder_id', $brandKit->id)
                    ->orWhereIn('id', FileAssignment::query()
                        ->where('assignable_type', (new User)->getMorphClass())
                        ->where('assignable_id', $client->id)
                        ->select('file_id'));
            })
            ->get();

        $detached = 0;

        foreach ($visible as $file) {
            if ($disk->exists($file->path)) {
                continue;
            }

            FileAssignment::query()
                ->where('file_id', $file->id)
                ->where('assignable_type', (new User)->getMorphClass())
                ->where('assignable_id', $client->id)
                ->delete();

            if ($file->folder_id === $brandKit->id) {
                $file->forceFill(['folder_id' => null])->save();
            }

            $detached++;
        }

        return $detached;
    }

    /**
     * Drop demo files this run no longer wants.
     *
     * Renaming an entry in PHOTOS otherwise leaves the old one behind
     * forever, and the library slowly fills with things nobody chose —
     * which is the same failure as leaving broken files in it, just
     * slower. Scoped to the demo prefix, so nothing an operator uploaded
     * themselves is ever touched.
     *
     * @param  list<string>  $keep
     */
    private function pruneStaleDemoFiles(Filesystem $disk, array $keep): void
    {
        $stale = File::query()
            ->where('path', 'like', 'theme-preview-demo/%')
            ->whereNotIn('path', $keep)
            ->get();

        foreach ($stale as $file) {
            $disk->delete($file->path);
            $file->categories()->detach();
            FileAssignment::query()->where('file_id', $file->id)->delete();
            $file->forceDelete();
        }

        if ($stale->isNotEmpty()) {
            $this->line('  Pruned:   '.$stale->count().' demo file(s) this run no longer wants');
        }
    }

    private function pathFor(string $name, string $extension): string
    {
        return 'theme-preview-demo/'.Str::slug($name).'.'.$extension;
    }

    /**
     * A real photograph for this name, or null if one could not be had.
     *
     * Keeps what is already on disk unless asked to refresh, so a rerun
     * costs nothing and the committed preview PNGs do not churn. The
     * readability check is the part that matters: a truncated or
     * half-written file is worse than an absent one, because it survives
     * every "does the file exist" test and only fails later, in the
     * thumbnail generator, as `Could not read image dimensions`.
     */
    private function photoBytes(Filesystem $disk, string $relativePath, int $photoId): ?string
    {
        if (! $this->option('refresh-photos') && $disk->exists($relativePath)) {
            $existing = (string) $disk->get($relativePath);

            if (@getimagesizefromstring($existing) !== false) {
                return $existing;
            }
        }

        if ($this->option('offline')) {
            return null;
        }

        $url = sprintf(self::PHOTO_URL, $photoId);

        // Twice, because a single flaky response should not put a drawn
        // rectangle into a screenshot that ships.
        foreach ([1, 2] as $attempt) {
            try {
                $response = Http::timeout(20)->connectTimeout(10)->get($url);

                if ($response->successful()) {
                    $bytes = $response->body();

                    if (@getimagesizefromstring($bytes) !== false) {
                        return $bytes;
                    }
                }
            } catch (Throwable) {
                // Falls through to the next attempt, then to the placeholder.
            }
        }

        return null;
    }

    /**
     * A drawn stand-in, used only when a real photograph could not be
     * fetched. JPEG rather than PNG so it matches the extension and mime
     * type its neighbours carry — a `.jpg` holding PNG bytes passes an
     * existence check and then fails thumbnail generation.
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function generatePng(string $label, array $rgb): string
    {
        [$r, $g, $b] = $rgb;
        $r = max(0, min(255, $r));
        $g = max(0, min(255, $g));
        $b = max(0, min(255, $b));

        $image = imagecreatetruecolor(640, 480);

        $background = imagecolorallocate($image, $r, $g, $b);
        assert($background !== false);
        imagefilledrectangle($image, 0, 0, 640, 480, $background);

        $white = imagecolorallocate($image, 255, 255, 255);
        assert($white !== false);
        imagestring($image, 5, 20, 20, $label, $white);

        ob_start();
        imagejpeg($image, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
