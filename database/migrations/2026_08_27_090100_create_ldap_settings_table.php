<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A table of its own rather than the generic settings store, for the
        // same reason mail_provider_settings has one: `bind_password` needs a
        // real Eloquent-encrypted column, and the shared settings JSON blob
        // has no per-key encryption.
        //
        // Every column here is read by the code. v1's LDAP screen collected
        // sixteen settings and acted on eight of them — a port that was never
        // used, a search filter that was never applied, a `use_tls` flag with
        // no StartTLS call behind it — so an administrator could configure
        // something and be quietly wrong. Anything this app will not act on
        // does not get a column.
        Schema::create('ldap_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('active')->default(false);
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->default(389);
            $table->string('encryption')->default('tls');
            // For a directory presenting a certificate from a private CA —
            // the honest answer to the "just don't verify it" request.
            $table->string('ca_cert_path')->nullable();
            // Empty bind_dn means an anonymous bind, which some directories
            // allow for the search step.
            $table->string('bind_dn')->nullable();
            $table->text('bind_password')->nullable();
            $table->string('base_dn')->nullable();
            // Admin-supplied, ANDed with the email match. Never concatenated
            // with anything a visitor typed.
            $table->string('user_filter')->nullable();
            $table->string('email_attribute')->default('mail');
            $table->string('name_attribute')->default('cn');
            $table->boolean('auto_provision')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ldap_settings');
    }
};
