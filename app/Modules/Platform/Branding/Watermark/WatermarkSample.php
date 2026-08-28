<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branding\Watermark;

use claviska\SimpleImage;

/**
 * The stand-in photograph the settings screen draws the mark on, so an
 * administrator can judge placement, scale and opacity without going to
 * find a client account and a real file.
 *
 * Drawn rather than shipped as an asset: a stock photograph would be a
 * licensing question and a binary in a git repository, and would only
 * ever exercise whatever tones that one picture happens to contain. This
 * is built to answer the question the sample exists for — "will my mark
 * still read?" — with a full dark-to-light ramp under it, plus a couple
 * of hard edges, so a too-transparent or too-small mark is obvious
 * against at least one part of it.
 */
class WatermarkSample
{
    /**
     * Roughly the proportions of a landscape photograph, and about the
     * size the settings screen shows it at — big enough to judge, small
     * enough to re-render on every keystroke.
     */
    private const WIDTH = 480;

    private const HEIGHT = 300;

    public function __construct(
        private readonly WatermarkPainter $painter,
    ) {}

    /**
     * @param  string  $markPath  an absolute local path to the artwork
     */
    public function render(string $markPath, WatermarkPosition $position, int $size, int $opacity): SimpleImage
    {
        $canvas = $this->backdrop();

        $this->painter->paint($canvas, $markPath, $position, $size, $opacity);

        return $canvas;
    }

    private function backdrop(): SimpleImage
    {
        $canvas = (new SimpleImage())->fromNew(self::WIDTH, self::HEIGHT, '#1f2937');

        // A left-to-right ramp, one column at a time — GD has no gradient
        // primitive, and 480 lines is imperceptible next to the encode
        // that follows.
        for ($x = 0; $x < self::WIDTH; $x++) {
            $shade = (int) round(24 + ($x / self::WIDTH) * 210);

            // alpha 1 is *opaque* in SimpleImage's vocabulary — 0 is the
            // fully transparent one ('transparent' normalizes to alpha 0).
            // Getting that backwards draws the whole ramp invisibly, which
            // no assertion about the mark itself would ever have caught.
            $canvas->line($x, 0, $x, self::HEIGHT, [
                'red' => $shade, 'green' => $shade, 'blue' => $shade, 'alpha' => 1,
            ]);
        }

        // Two blocks at the extremes of the ramp, so every corner and edge
        // the position picker offers has both a light and a dark
        // neighbourhood somewhere near it.
        $this->fill($canvas, 0, 0, (int) (self::WIDTH * 0.28), (int) (self::HEIGHT * 0.34), '#f8fafc');
        $this->fill($canvas, (int) (self::WIDTH * 0.68), (int) (self::HEIGHT * 0.62), self::WIDTH, self::HEIGHT, '#0b1120');

        return $canvas;
    }

    /**
     * A filled rectangle, drawn as a run of vertical lines.
     *
     * `rectangle(..., 'filled')` does exist and would be the obvious call,
     * but SimpleImage's own docblock types that parameter `integer|array`,
     * so passing its documented magic string fails static analysis. Lines
     * cost nothing here and keep the analyser honest instead of teaching
     * it to ignore a whole category of argument-type error in this file.
     *
     * @param  string|array<string, int>  $color
     */
    private function fill(SimpleImage $canvas, int $x1, int $y1, int $x2, int $y2, string|array $color): void
    {
        for ($x = $x1; $x <= $x2; $x++) {
            $canvas->line($x, $y1, $x, $y2, $color);
        }
    }
}
