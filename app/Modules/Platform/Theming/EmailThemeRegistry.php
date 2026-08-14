<?php

declare(strict_types=1);

namespace App\Modules\Platform\Theming;

/**
 * The theme pool for outgoing notification emails (Setting::EmailTheme) —
 * a header/footer skin, independent of the per-notification subject/body
 * editor (EmailTemplate/EmailTemplateResolver).
 */
class EmailThemeRegistry extends ThemeRegistry {}
