<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings\Console;

use App\Modules\Identity\TwoFactor\TwoFactorEnforcement;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Settings\StoredSetting;
use Illuminate\Console\Command;

/**
 * Seed a setting from the environment, once, at provision.
 *
 * Settings live in the database because they belong to whoever runs the
 * installation. A managed one has a moment before anybody runs it — the
 * first boot, where the entrypoint already creates the first administrator
 * from ADMIN_* — and a policy that has to exist before the account it
 * protects has to be written there or not at all.
 *
 * The case this exists for is two-factor enforcement. A platform wants it
 * on before the first seat, and the first seat is created in that same
 * boot. Left to a control plane calling in afterwards, there is a window
 * between the account existing and the policy covering it.
 *
 * ### Seeded, not overridden
 *
 * Writing only when nothing has been stored is the whole design. A value
 * that won on every boot would take the setting away from the
 * administrator it belongs to, and somebody who tightened it would find it
 * loosened again by a restart. Same shape as `projectsend:admin --if-none`,
 * which runs a line below this one in the entrypoint.
 *
 * ### Read through config, never env() directly
 *
 * `config:cache` stops `.env` being read at all, which is exactly how
 * TRUSTED_PROXIES came to have no effect on any web request while looking
 * correct in the file. Anything an operator sets has to arrive through a
 * config key or it works until somebody optimises the install.
 *
 * ### Deliberately not general
 *
 * No `PROJECTSEND_SETTING_<KEY>` mechanism. Every setting reachable from
 * outside is a setting whose value depends on where you look, and the
 * blast radius of getting that wrong is the whole settings table. One
 * named key per setting that needs it, added when it needs it.
 */
class SeedSettingsCommand extends Command
{
    protected $signature = 'projectsend:seed-settings';

    protected $description = 'Apply provisioning defaults from the environment to settings that have never been set';

    public function handle(Settings $settings): int
    {
        $enforcement = config('projectsend.platform.two_factor_enforcement');

        if (is_string($enforcement) && $enforcement !== '') {
            $this->seedTwoFactorEnforcement($settings, $enforcement);
        }

        return self::SUCCESS;
    }

    private function seedTwoFactorEnforcement(Settings $settings, string $value): void
    {
        if (TwoFactorEnforcement::tryFrom($value) === null) {
            // Named rather than ignored. A typo here means a tenant
            // provisioned without the policy it was meant to have, and
            // silence would make that indistinguishable from success.
            $this->warn("PROJECTSEND_TWO_FACTOR_ENFORCEMENT='{$value}' is not one of none, staff, clients, all — leaving the setting alone.");

            return;
        }

        // Asked of the table rather than of Settings::get(), which cannot
        // tell a stored value apart from the enum's own default — and
        // 'none' is that default, so get() would report the thing we are
        // trying to detect the absence of.
        if (StoredSetting::query()->where('key', Setting::TwoFactorEnforcement->value)->exists()) {
            return;
        }

        $settings->set(Setting::TwoFactorEnforcement, $value);

        $this->info("Two-factor enforcement seeded to '{$value}' (first boot).");
    }
}
