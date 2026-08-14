<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // Null means "never expires" — same convention as
            // ShareLink::expires_at. Once past, the file is hidden from
            // clients and the public site but stays fully visible/editable
            // to staff.
            $table->timestamp('expires_at')->nullable()->after('public');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
