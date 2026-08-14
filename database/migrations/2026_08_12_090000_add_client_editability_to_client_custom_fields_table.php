<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_custom_fields', function (Blueprint $table) {
            $table->string('client_editability')->default('hidden')->after('required');
            $table->json('client_contexts')->nullable()->after('client_editability');
        });
    }

    public function down(): void
    {
        Schema::table('client_custom_fields', function (Blueprint $table) {
            $table->dropColumn(['client_editability', 'client_contexts']);
        });
    }
};
