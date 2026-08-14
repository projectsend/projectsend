<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Comments\CommentVisibility;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A comment written straight to the database, bypassing FileComments —
 * for tests that need one to exist in a given state. Anything asserting
 * what *happens* when a comment is posted (notifications, activity, the
 * approval decision) should go through the service instead.
 *
 * The default is the safest shape to reason about: an approved staff
 * internal note, which belongs to no client thread. States below build the
 * thread-scoped and anonymous variants that carry the privacy model.
 *
 * @extends Factory<FileComment>
 */
class FileCommentFactory extends Factory
{
    /** @var class-string<FileComment> */
    protected $model = FileComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_id' => File::factory(),
            'author_id' => User::factory(),
            'client_context_id' => null,
            'visibility' => CommentVisibility::StaffOnly,
            'body' => fake()->sentence(),
            'approved_at' => now(),
        ];
    }

    /**
     * A comment inside one client's conversation — the shape the
     * cross-client isolation rules apply to.
     */
    public function inThreadOf(User $client): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => CommentVisibility::Clients,
            'client_context_id' => $client->id,
        ]);
    }

    /**
     * Staff addressing every client on the file: no conversation of its
     * own, so every client with access reads it.
     */
    public function toAllClients(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => CommentVisibility::Clients,
            'client_context_id' => null,
        ]);
    }

    /** A staff note no client ever reads. */
    public function staffOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => CommentVisibility::StaffOnly,
            'client_context_id' => null,
        ]);
    }

    public function onlyMe(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => CommentVisibility::OnlyMe,
            'client_context_id' => null,
        ]);
    }

    public function everyone(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => CommentVisibility::Everyone,
            'client_context_id' => null,
        ]);
    }

    /**
     * An anonymous visitor's comment: no account behind it, and the only
     * visibility such an author can ever produce.
     */
    public function fromGuest(string $name = 'A visitor'): static
    {
        return $this->state(fn (array $attributes) => [
            'author_id' => null,
            'guest_name' => $name,
            'ip_address' => '203.0.113.10',
            'visibility' => CommentVisibility::Everyone,
            'client_context_id' => null,
        ]);
    }

    /**
     * Awaiting moderation — invisible to everyone but a moderator.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'approved_at' => null,
        ]);
    }
}
