<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Materialized ancestor ids, e.g. "/3/7/" — O(1) subtree and
            // ancestor queries without recursive CTEs.
            $table->string('path')->default('/')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('folder_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            // A client (User) or a Group — sharing grants live access to
            // the whole subtree.
            $table->morphs('assignable');
            $table->timestamps();
            $table->unique(['folder_id', 'assignable_type', 'assignable_id'], 'folder_assignment_unique');
        });

        Schema::table('files', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('uploaded_by')->constrained('folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });

        Schema::dropIfExists('folder_assignments');
        Schema::dropIfExists('folders');
    }
};
