<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Files\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A File row written straight to the database, bypassing the upload
 * endpoint — for the many tests that need a file to exist but do not care
 * how it got there. Use the uploadFileAs-style helpers in tests/Helpers.php
 * instead whenever the intake path itself is part of what's under test.
 *
 * Every column the table requires has a default here, so a caller only names
 * the attributes its assertions actually depend on.
 *
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /** @var class-string<File> */
    protected $model = File::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uploaded_by' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            // Mirrors the display name unless a test overrides it — the two
            // differ only when the original filename is itself the subject
            // (Content-Disposition escaping, for instance).
            'original_name' => fn (array $attributes): string => $attributes['name'].'.pdf',
            // Unique per row: the disk path is a real uniqueness constraint
            // in practice, and reusing one across rows makes deletion tests
            // clean up each other's bytes.
            'path' => '2026/08/'.Str::uuid()->toString().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'checksum' => str_repeat('a', 64),
            // Spelled out even though the column defaults to the same value:
            // a database default is applied on insert but not reflected back
            // onto the in-memory model, so leaving it out means $file->disk
            // reads NULL until the row is refreshed — and anything resolving
            // Storage::disk($file->disk) then silently gets the app's default
            // disk instead of this one.
            'disk' => 'files',
        ];
    }

    /**
     * Visible on the public listing.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes): array => ['public' => true]);
    }

    /**
     * A real image, for the thumbnail and preview paths.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes): array => [
            // Lazy, like the one in definition(): a state closure runs before
            // create()'s own overrides are merged, so reading $attributes['name']
            // directly here would pair a caller's name with the default's.
            'original_name' => fn (array $attributes): string => $attributes['name'].'.jpg',
            'path' => '2026/08/'.Str::uuid()->toString().'.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }
}
