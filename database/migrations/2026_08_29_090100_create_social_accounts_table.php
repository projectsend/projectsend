<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The table that removes v1's account takeover.
        //
        // v1 had no link at all: it matched whatever address the provider
        // returned against `email` OR `user` and signed you in as whoever
        // held it, verified or not. Any identity provider allowing self
        // registration was therefore enough to become an administrator.
        //
        // Here the identity is (provider, provider_user_id) — the OIDC
        // `sub`, which is stable and assigned by the provider. The email is
        // stored for display and is never a lookup key on its own; it is
        // only ever used, once, to *create* one of these rows, and only
        // when the provider says it verified the address.
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_user_id');
            $table->string('email')->nullable();
            $table->timestamps();

            // One identity, one account. Without this, the same directory
            // identity could be linked to two accounts and which one you
            // land on becomes a matter of row order.
            $table->unique(['provider', 'provider_user_id']);
            // An account may hold at most one link per provider, so
            // "disconnect Google" is unambiguous.
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
