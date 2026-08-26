<?php

declare(strict_types=1);

namespace App\Modules\Identity\TwoFactor;

use App\Models\User;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(
        private readonly Google2FA $engine,
        private readonly Settings $settings,
    ) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    /**
     * Strip every second-factor credential from an account, whether it
     * was fully enrolled or halfway through enrolling. Returns whether a
     * confirmed second factor was actually in force — the caller needs
     * that to decide if anything worth recording happened.
     */
    public function clear(User $user): bool
    {
        $wasEnabled = $user->hasTwoFactorEnabled();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $wasEnabled;
    }

    /**
     * Verify a TOTP code, refusing replays of a recently used code.
     */
    public function verify(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;

        if ($secret === null) {
            return false;
        }

        $replayKey = "two-factor.used.{$user->id}.".hash('sha256', $code);

        if (Cache::has($replayKey)) {
            return false;
        }

        if ($this->engine->verifyKey($secret, $code) === false) {
            return false;
        }

        // A TOTP code is valid for one window either side; block reuse
        // for slightly longer than that.
        Cache::put($replayKey, true, now()->addSeconds(90));

        return true;
    }

    /**
     * The otpauth:// provisioning URI encoded as an inline SVG QR code.
     */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $siteName = $this->settings->get(Setting::SiteName);
        $siteName = is_string($siteName) ? $siteName : 'ProjectSend';

        $uri = $this->engine->getQRCodeUrl($siteName, $user->email, $secret);

        $svg = (new Writer(
            new ImageRenderer(
                new RendererStyle(192, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(45, 55, 72))),
                new SvgImageBackEnd,
            ),
        ))->writeString($uri);

        return trim(substr($svg, strpos($svg, "\n") ?: 0));
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < 8; $i++) {
            $codes[] = Str::random(10).'-'.Str::random(10);
        }

        return $codes;
    }

    /**
     * Consume a recovery code; each code works exactly once.
     *
     * Read the list, filter it, write the whole list back is not once.
     * Two requests that both read before either writes each store their
     * own filtered copy, and the second write puts back the code the
     * first removed -- so a spent code is available again, and the same
     * code offered twice is accepted twice. Neither lets in anybody who
     * was not already holding a code, which is why this is a promise not
     * being kept rather than a door standing open. The promise is the
     * sentence above, and it is the reason recovery codes are printed
     * out and crossed off.
     *
     * Decide from the row as it stands, read back under a lock inside
     * the transaction that writes it -- the same shape
     * SendNotificationDigest uses to claim the rows it is about to
     * delete. The lock is what makes it atomic against a request
     * arriving at the same moment; the re-read is what makes the
     * decision right, and it is the half that can be demonstrated in a
     * test, since SQLite ignores lockForUpdate.
     *
     * The caller's own instance is what gets saved, so it does not walk
     * away holding a list the database no longer has.
     */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            /** @var list<string>|null $codes */
            $codes = $locked?->two_factor_recovery_codes;

            if ($codes === null || ! in_array($code, $codes, true)) {
                return false;
            }

            $user->forceFill([
                'two_factor_recovery_codes' => array_values(array_diff($codes, [$code])),
            ])->save();

            return true;
        });
    }
}
