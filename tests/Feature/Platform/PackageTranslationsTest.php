<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\File;

/**
 * A companion package owns whole screens — Branding, Custom Assets, the
 * v1 import — and every string on them was rendering in English in all
 * sixteen languages, because the frontend read lang/{locale}.json
 * directly and a package's catalogue is somewhere else entirely.
 */
beforeEach(function () {
    // SetLocale honours the account's own language only while that
    // language is switched on for the installation, and the Settings cache
    // outlives RefreshDatabase — so say it rather than assume it.
    app(Settings::class)->set(Setting::EnabledLocales, ['en', 'es']);

    $this->admin = User::factory()->create(['locale' => 'es']);

    $this->packageLang = sys_get_temp_dir().'/package-lang-'.bin2hex(random_bytes(6));
    File::makeDirectory($this->packageLang);
});

afterEach(function () {
    File::deleteDirectory($this->packageLang);
});

function packageCatalogue(string $dir, array $entries): void
{
    File::put($dir.'/es.json', json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    app('translator')->getLoader()->addJsonPath($dir);
}

function sharedTranslations(User $user): array
{
    return test()->actingAs($user)->get('/dashboard')->viewData('page')['props']['translations'];
}

test('a package catalogue reaches the frontend', function () {
    packageCatalogue($this->packageLang, ['Attribution settings saved.' => 'Ajustes de atribución guardados.']);

    expect(sharedTranslations($this->admin))
        ->toHaveKey('Attribution settings saved.', 'Ajustes de atribución guardados.');
});

test('the application keeps its own strings', function () {
    packageCatalogue($this->packageLang, ['Something from a package' => 'Algo de un paquete']);

    $translations = sharedTranslations($this->admin);

    expect($translations)->toHaveKey('Something from a package')
        // Any string this repository owns, still translated as before.
        ->and($translations['Dashboard'] ?? null)->toBe('Panel de control');
});

// The useful way round: an installation can override a package's wording
// in its own catalogue without editing the package.
test('the installation wins when both define the same key', function () {
    packageCatalogue($this->packageLang, ['Dashboard' => 'From the package']);

    expect(sharedTranslations($this->admin)['Dashboard'])->toBe('Panel de control');
});

// English is the key, so there is nothing to send and nothing to merge.
test('english still ships no catalogue at all', function () {
    packageCatalogue($this->packageLang, ['Attribution' => 'Should never be sent']);

    $english = User::factory()->create(['locale' => 'en']);

    expect(sharedTranslations($english))->toBe([]);
});
