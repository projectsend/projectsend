<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per CAPTCHA provider, not one row for "the" provider.
 *
 * An administrator comparing Turnstile against reCAPTCHA switches back and
 * forth; with a single generic row that costs them their keys every time.
 * It also gives the v1 import somewhere to put all three of the key pairs
 * v1 could hold at once. Which provider is *active* is a separate,
 * non-secret question, and lives in the settings store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captcha_providers', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('site_key')->nullable();
            // text, not string: this column holds ciphertext (see the
            // 'encrypted' cast on CaptchaSettings), which is several times
            // longer than the secret an administrator pasted in.
            $table->text('secret_key')->nullable();
            // reCAPTCHA v3 only. Null for the providers that decide rather
            // than score, so an unused column is empty instead of holding
            // a number that means nothing.
            $table->decimal('score_threshold', 3, 2)->nullable()->default(0.5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captcha_providers');
    }
};
