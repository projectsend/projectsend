<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which clients a client-scoped staff member manages. The scope of
        // what they can see and share in the library derives from this list.
        Schema::create('staff_client_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['staff_id', 'client_id'], 'staff_client_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_client_assignments');
    }
};
