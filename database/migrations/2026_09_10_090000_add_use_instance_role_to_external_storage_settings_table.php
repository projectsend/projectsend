<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_storage_settings', function (Blueprint $table) {
            // Every row that exists predates the choice, and every one of
            // them authenticates with a stored key and secret — so `false`
            // is what keeps this migration invisible to anyone already
            // using external storage.
            //
            // An explicit column rather than "the key field was left
            // blank": on this form a blank credential already means "keep
            // the one you have", because neither the secret nor the key
            // file is ever sent back to the browser. Blank cannot also
            // mean "authenticate a different way" without the two
            // meanings colliding on the first save.
            $table->boolean('use_instance_role')->default(false)->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('external_storage_settings', function (Blueprint $table) {
            $table->dropColumn('use_instance_role');
        });
    }
};
