<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branding\Watermark;

use claviska\SimpleImage;

/**
 * Draws the mark onto a canvas. The only place that decides how a
 * watermark is positioned, scaled and blended.
 *
 * Extracted so the settings screen's live sample and the real rendering
 * pipeline cannot drift: an administrator tuning the size slider against
 * a preview drawn by *different* code would be tuning against a lie, and
 * the lie would be discovered on a client's screen. The sample and the
 * thumbnail a client actually gets are the same function, called with
 * different arguments.
 *
 * Takes its settings as arguments rather than reading BrandingSetting,
 * for the same reason: the sample renders values that are still unsaved
 * in a form.
 */
class WatermarkPainter
{
    /**
     * The mark's clearance from the edge it is anchored to, as a fraction
     * of the canvas's shorter side. A fraction rather than a pixel count
     * so a 300px thumbnail and a 1600px preview look like the same
     * design. Not a setting: the difference between "flush against the
     * edge" and "a few pixels in" is the whole of the visual judgement,
     * and there is no useful second answer to offer an administrator.
     */
    private const EDGE_INSET_RATIO = 0.04;

    /**
     * @param  string  $markPath  an absolute local path to the artwork
     * @param  int  $size  percentage of the canvas the mark is fitted into
     * @param  int  $opacity  percentage
     */
    public function paint(
        SimpleImage $canvas,
        string $markPath,
        WatermarkPosition $position,
        int $size,
        int $opacity,
    ): void {
        $width = $canvas->getWidth();
        $height = $canvas->getHeight();

        // A box that is `size`% of *both* dimensions, so the setting reads
        // the same on a portrait and a landscape canvas and a wide mark
        // can never overflow a narrow one.
        $mark = new SimpleImage($markPath);

        $scale = min(
            $width * $size / 100 / $mark->getWidth(),
            $height * $size / 100 / $mark->getHeight(),
        );

        // Scaled by hand rather than with bestFit(), which returns early
        // when the image already fits: a small logo would then keep its
        // native size and the size setting would silently do nothing above
        // whatever percentage happened to match it. Enlarging a small mark
        // is soft, but it is what was asked for — a control that only works
        // in one direction is worse than a slightly blurry one.
        $mark->resize(
            max(1, (int) round($mark->getWidth() * $scale)),
            max(1, (int) round($mark->getHeight() * $scale)),
        );

        $inset = max(1, (int) round(min($width, $height) * self::EDGE_INSET_RATIO));

        $canvas->overlay(
            $mark,
            $position->anchor(),
            $opacity / 100,
            $inset,
            $inset,
            // Offsets measured inward from whichever edge the anchor names,
            // so one inset value works for all eight edge positions instead
            // of needing its sign flipped per corner. Centre ignores them.
            calculateOffsetFromEdge: true,
        );
    }
}
