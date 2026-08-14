<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per provider rather than one row with a column per
        // provider, which is what v1 did (facebook_client_id,
        // google_client_id, linkedin_client_id, ...). Adding or dropping a
        // provider is then data, not a migration — and every provider gets
        // the same policy fields instead of the settings drifting apart.
        //
        // As with ldap_settings, a table of its own: `client_secret` needs
        // a real Eloquent-encrypted column and the shared settings JSON
        // blob has no per-key encryption. v1 stored these in plain text.
        Schema::create('social_login_providers', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            // Generic OIDC only: the discovery document is fetched from
            // here, so it is the entire configuration of an unknown server.
            $table->string('issuer_url')->nullable();
            // Microsoft only, and required there. Entra's `email` claim is
            // user-mutable and unverified, so a pinned tenant is the only
            // thing that makes it trustworthy — see SocialIdentity.
            $table->string('tenant_id')->nullable();
            // Defaults on, because off is the v1 bug.
            $table->boolean('require_verified_email')->default(true);
            // Comma-separated, blank means any. Without it, a public Google
            // client plus auto-provisioning means the whole internet.
            $table->string('allowed_domains')->nullable();
            $table->boolean('auto_provision')->default(false);
            $table->boolean('auto_approve')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_login_providers');
    }
};
