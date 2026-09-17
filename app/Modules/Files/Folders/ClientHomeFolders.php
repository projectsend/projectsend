<?php

declare(strict_types=1);

namespace App\Modules\Files\Folders;

use App\Models\User;
use App\Modules\Identity\UserType;
use App\Modules\Files\Models\Folder;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\DB;

/**
 * A folder per client, named after them, standing in for the root.
 *
 * ## What it is for
 *
 * Without it, a client who may create folders creates them at the top of
 * the library, beside the ones staff made. Their uploads land at the root
 * too. An administrator opening /files sees one flat pile with no clue
 * which parts belong to whom. With it, each client gets one folder and
 * everything of theirs goes inside, so /files reads as a list of clients.
 *
 * ## What it is NOT
 *
 * It is not a boundary, and this is the important sentence in the file. A
 * folder staff shared with a client stays visible to that client, sitting
 * beside their own — Folder::scopeVisibleToClient is untouched by any of
 * this. Treating the home as a jail would silently revoke every share that
 * already exists, which is a data-access change wearing the clothes of a
 * tidying-up feature. "Root" here means *where new things go by default*,
 * nothing more.
 *
 * ## Why created_by is the client
 *
 * scopeVisibleToClient grants a client their own folders through
 * `created_by`. Creating the home as the client makes it theirs by the
 * rule that already exists, rather than needing an assignment row that
 * would then have to be kept in step with it. That is also why this writes
 * the row itself instead of calling FolderService::create(), which takes
 * `created_by` from `auth()->id()` — the creator here is whoever pressed a
 * button, and the owner has to be the client.
 */
class ClientHomeFolders
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get(Setting::ClientsHomeFolders);
    }

    /**
     * This client's home, or null if they have none.
     *
     * Asked of the column and not of the setting: a home that exists keeps
     * working after the switch is turned off again. The folder is real,
     * it holds real files, and pretending it is not there would strand
     * them somewhere no listing looks.
     */
    public function for(?User $client): ?Folder
    {
        if ($client === null || ! $client->isClient()) {
            return null;
        }

        return Folder::query()->where('home_for_user_id', $client->id)->first();
    }

    /**
     * Give this client a home if the installation wants them to have one.
     *
     * Idempotent, and safe to call on a client who already has one. Returns
     * the folder either way, or null when the feature is off.
     */
    public function ensureFor(User $client): ?Folder
    {
        if (! $client->isClient() || ! $this->enabled()) {
            return null;
        }

        return $this->create($client);
    }

    /**
     * Create the row, or hand back the one that is already there.
     *
     * The unique index on home_for_user_id is what actually guarantees one
     * home per client; this check only avoids raising on the ordinary
     * second call. Two administrators pressing the backfill button at the
     * same moment is exactly the race the index is there for.
     */
    private function create(User $client): Folder
    {
        return DB::transaction(function () use ($client): Folder {
            $existing = $this->for($client);

            if ($existing !== null) {
                return $existing;
            }

            return Folder::query()->create([
                'name' => $this->nameFor($client),
                'parent_id' => null,
                // Root, so an administrator sees it at the top of /files --
                // which is the whole point of the feature.
                'path' => '/',
                'created_by' => $client->id,
                'home_for_user_id' => $client->id,
            ]);
        });
    }

    /**
     * Keep the folder's name in step with the client's.
     *
     * Always, including over a name somebody typed by hand. That was the
     * product decision (2026-09-17) and it is the defensible one: the
     * folder exists to say whose things these are, so a folder still
     * called "Acme Ltd" after the account became "Acme Holdings" is
     * actively misleading to the administrator the feature is for. A
     * client cannot rename it anyway -- see FolderPolicy.
     */
    public function syncName(User $client): void
    {
        $home = $this->for($client);

        if ($home === null) {
            return;
        }

        $name = $this->nameFor($client);

        if ($home->name !== $name) {
            $home->update(['name' => $name]);
        }
    }

    /**
     * How many clients would get a folder if the button were pressed.
     *
     * A count and not the rows: the settings screen only needs the number,
     * and an installation with thousands of clients should not load them
     * all to render one sentence.
     */
    public function pendingCount(): int
    {
        return User::query()
            ->where('type', UserType::Client)
            ->whereNotExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('folders')
                ->whereColumn('folders.home_for_user_id', 'users.id')
                ->whereNull('folders.deleted_at'))
            ->count();
    }

    /**
     * Every client without a home gets one.
     *
     * Deliberately a button rather than something switching the setting on
     * does by itself: it writes a folder per client, and an administrator
     * trying the feature out should be able to turn it on, look, and change
     * their mind without having reorganised anything.
     *
     * Reports counts rather than staying quiet, because on an installation
     * with hundreds of clients "it worked" is not a useful answer -- the
     * administrator wants to know how many there were and how many are new.
     *
     * @return array{total: int, created: int, existing: int}
     */
    public function backfill(): array
    {
        $clients = User::query()->where('type', UserType::Client)->orderBy('id')->get();
        $created = 0;
        $existing = 0;

        foreach ($clients as $client) {
            if ($this->for($client) !== null) {
                $existing++;

                continue;
            }

            $this->create($client);
            $created++;
        }

        return [
            'total' => $clients->count(),
            'created' => $created,
            'existing' => $existing,
        ];
    }

    /**
     * A blank name would render as an unclickable sliver in the tree, so
     * the address stands in -- every account has one, and it identifies
     * the person as well as a name does.
     */
    private function nameFor(User $client): string
    {
        $name = trim($client->name);

        return $name !== '' ? $name : $client->email;
    }
}
