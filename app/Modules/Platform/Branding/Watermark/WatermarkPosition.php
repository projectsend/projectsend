<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branding\Watermark;

/**
 * Where the watermark sits on a thumbnail: the four corners, the
 * midpoint of each of the four edges, and the centre.
 *
 * The stored values are this module's own vocabulary, deliberately not
 * SimpleImage's anchor strings — `anchor()` translates. SimpleImage
 * decides an anchor by substring-matching 'top'/'bottom'/'left'/'right',
 * so 'center' means "neither" on an axis and the two vocabularies happen
 * to overlap today; storing its spelling in our database would make that
 * coincidence a schema commitment.
 */
enum WatermarkPosition: string
{
    case TopLeft = 'top-left';
    case TopCenter = 'top-center';
    case TopRight = 'top-right';
    case MiddleLeft = 'middle-left';
    case Center = 'center';
    case MiddleRight = 'middle-right';
    case BottomLeft = 'bottom-left';
    case BottomCenter = 'bottom-center';
    case BottomRight = 'bottom-right';

    public function anchor(): string
    {
        return match ($this) {
            self::TopLeft => 'top left',
            self::TopCenter => 'top',
            self::TopRight => 'top right',
            self::MiddleLeft => 'left',
            self::Center => 'center',
            self::MiddleRight => 'right',
            self::BottomLeft => 'bottom left',
            self::BottomCenter => 'bottom',
            self::BottomRight => 'bottom right',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
