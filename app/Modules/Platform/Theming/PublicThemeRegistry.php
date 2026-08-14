<?php

declare(strict_types=1);

namespace App\Modules\Platform\Theming;

/**
 * The theme pool shared by the guest public pages and the client portal
 * (Setting::Theme) — one selection covers both, matching v1's single
 * selected_clients_template option.
 */
class PublicThemeRegistry extends ThemeRegistry {}
