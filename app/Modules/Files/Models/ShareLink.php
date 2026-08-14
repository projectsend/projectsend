<?php

declare(strict_types=1);

namespace App\Modules\Files\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A revocable, tokenized public URL to a File (Folder support can reuse
 * this same table later, hence the polymorphic shareable). Anyone with
 * the token can download the file with no account, until it expires or
 * hits its download limit.
 *
 * @property int $id
 * @property string $token
 * @property int|null $created_by
 * @property Carbon|null $expires_at
 * @property int|null $max_downloads
 * @property int $downloads_count
 */
class ShareLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasReachedLimit(): bool
    {
        return $this->max_downloads !== null && $this->downloads_count >= $this->max_downloads;
    }

    public function isActive(): bool
    {
        return ! $this->isExpired() && ! $this->hasReachedLimit();
    }
}
