<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The three tables the AI connector's OAuth needs (site docs/mcp-oauth-design.md
 * §2.1). Access tokens are not here: they are the host's own personal
 * access tokens, so a token minted at the consent and one pasted from
 * Settings → API tokens are the same thing to `/mcp`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Who asks. A `cimd` row is a cache of the document the client
        // publishes at its own URL; a `dcr` row is what a client without
        // one declared when it registered.
        Schema::create('mcp_clients', function (Blueprint $table): void {
            $table->id();
            $table->text('client_id');
            // The identifier is a URL for a CIMD client, too long for an
            // index of its own; the hash is what a lookup goes by.
            $table->char('client_id_hash', 64)->unique();
            $table->string('kind', 8);
            $table->string('name');
            $table->text('client_uri')->nullable();
            $table->text('logo_uri')->nullable();
            $table->json('redirect_uris');
            $table->json('metadata')->nullable();
            $table->timestamp('metadata_fetched_at')->nullable();
            $table->timestamp('metadata_expires_at')->nullable();
            // Name plus redirect URIs, so a registration repeated finds
            // the row it already made instead of making another.
            $table->char('fingerprint', 64)->nullable()->index();
            $table->timestamps();
        });

        // One per authorization attempt: sixty seconds, one use.
        Schema::create('mcp_authorization_codes', function (Blueprint $table): void {
            $table->id();
            $table->char('code_hash', 64)->unique();
            $table->foreignId('mcp_client_id')->constrained('mcp_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('scopes');
            $table->string('code_challenge', 128);
            $table->text('redirect_uri');
            $table->text('resource');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('issued_token_id')->nullable();
            $table->timestamps();
        });

        // A live connection: the refresh token in force and the access
        // token it issued. What the AI connections page lists.
        Schema::create('mcp_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mcp_client_id')->constrained('mcp_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('scopes');
            $table->char('refresh_token_hash', 64)->nullable()->unique();
            // The one before the rotation, kept so that its reuse is
            // recognised as reuse and not mistaken for a random guess.
            $table->char('previous_refresh_token_hash', 64)->nullable()->index();
            $table->uuid('family')->index();
            $table->unsignedBigInteger('access_token_id')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('refresh_expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_connections');
        Schema::dropIfExists('mcp_authorization_codes');
        Schema::dropIfExists('mcp_clients');
    }
};
