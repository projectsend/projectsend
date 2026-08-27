<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Installation\Installation;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\ExternalStorageSettings;
use App\Modules\Platform\Storage\StorageDurability;
use App\Modules\Platform\Storage\StorageDurabilityLevel;
use Illuminate\Support\Facades\File;

/**
 * The classification itself is exercised against a real /proc/self/mountinfo
 * — a fixture would only prove the parser agrees with whoever wrote the
 * fixture. Instead these drive the private parts through a subclass that
 * substitutes the two inputs the class cannot control in a test: whether we
 * are in a container, and what the mount table says.
 */
class FakeMounts extends StorageDurability
{
    public string $mountinfo = '';

    public bool $container = true;

    protected function readMountInfo(): ?array
    {
        return $this->mountinfo === '' ? null : explode("\n", trim($this->mountinfo));
    }

    protected function inContainer(): bool
    {
        return $this->container;
    }
}

function durability(string $mountinfo, bool $container = true): FakeMounts
{
    $fake = new FakeMounts(app(ExternalStorageConfigApplier::class), app(Installation::class));
    $fake->mountinfo = $mountinfo;
    $fake->container = $container;

    return $fake;
}

/** The container root and nothing else: files are on the overlay. */
const MOUNTS_EPHEMERAL = <<<'TXT'
1 0 0:1 / / rw,relatime - overlay overlay rw
2 1 0:2 / /proc rw,relatime - proc proc rw
TXT;

/**
 * A mount table with `$source` mounted over `$target`, plus the container
 * root underneath it so there is always something for the miss case to
 * fall back to.
 *
 * The target is passed in rather than written out because the class asks
 * `storage_path()` where the uploads live, and that answer is the checkout
 * directory — `/var/www/html` only inside the image, somewhere under
 * /home/runner on CI. Hardcoding the container's path made every mount miss
 * off the image, which reads as "ephemeral" and quietly passed the one test
 * expecting exactly that.
 */
function mounts(string $source, string $target): string
{
    return "1 0 0:1 / / rw,relatime - overlay overlay rw\n"
        ."3 1 8:1 {$source} {$target} rw,relatime - ext4 /dev/sda1 rw";
}

beforeEach(function () {
    // The class resolves the uploads path to its deepest *existing* ancestor
    // before matching it against the mount table, so a checkout without
    // storage/app/files (it carries no .gitkeep, so every fresh clone) would
    // be compared one directory higher than the mount these tests install.
    // Uploading anything creates it; make that true here too.
    File::ensureDirectoryExists(storage_path('app/files'));
});

test('files on the container filesystem are reported as ephemeral', function () {
    expect(durability(MOUNTS_EPHEMERAL)->inspect())
        ->toMatchArray(['level' => StorageDurabilityLevel::Ephemeral->value, 'volume' => null]);
});

test('a docker named volume is recognised, and its name is reported', function () {
    $mounts = mounts('/docker/volumes/projectsend_files/_data', storage_path('app/files'));

    expect(durability($mounts)->inspect())
        ->toMatchArray([
            'level' => StorageDurabilityLevel::DockerVolume->value,
            'volume' => 'projectsend_files',
        ]);
});

test('a host bind mount is durable, and reports where it came from', function () {
    $mounts = mounts('/srv/projectsend/files', storage_path('app/files'));

    expect(durability($mounts)->inspect())
        ->toMatchArray([
            'level' => StorageDurabilityLevel::Durable->value,
            'source' => '/srv/projectsend/files',
        ]);
});

test('a mount over a parent directory still counts — the longest prefix wins', function () {
    // The whole application directory bind-mounted, which is what
    // compose.yaml does today: the uploads are inside it and just as safe.
    $mounts = mounts('/srv/app', base_path());

    expect(durability($mounts)->inspect())
        ->toMatchArray(['level' => StorageDurabilityLevel::Durable->value, 'source' => '/srv/app']);
});

test('an unreadable mount table says so rather than guessing', function () {
    expect(durability('')->inspect())
        ->toMatchArray(['level' => StorageDurabilityLevel::Unknown->value]);
});

test('nothing is reported when not running in a container', function () {
    expect(durability(MOUNTS_EPHEMERAL, container: false)->inspect())->toBeNull();
});

test('nothing is reported when uploads go to external storage instead', function () {
    ExternalStorageSettings::current()->fill([
        'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'b', 'active' => true,
    ])->save();
    app(ExternalStorageConfigApplier::class)->flush();

    expect(durability(MOUNTS_EPHEMERAL)->inspect())->toBeNull();
})->skip(
    fn () => ! app(CapabilityRegistry::class)->has(Capability::StorageConfigure),
    'external storage is a community-only capability',
);

test('the dashboard carries the verdict to the system widget', function () {
    $admin = User::factory()->create();

    // Substituted rather than read live, for the same reason the rest of
    // this file substitutes it: the real answer depends on whatever machine
    // the suite runs on. What is under test here is that the verdict this
    // class produced reaches the widget whole — the level, and the volume
    // name the card prints alongside it.
    app()->instance(
        StorageDurability::class,
        durability(mounts('/docker/volumes/projectsend_files/_data', storage_path('app/files'))),
    );

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('system.storage_durability', [
            'level' => StorageDurabilityLevel::DockerVolume->value,
            'volume' => 'projectsend_files',
            'source' => null,
        ]));
});
