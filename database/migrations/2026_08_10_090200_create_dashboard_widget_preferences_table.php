<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widget_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('widget_key');
            $table->boolean('enabled')->default(true);
            $table->unsignedTinyInteger('column_index')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // An absent row means "use the documented default layout" —
            // a row only exists once a user has actually rearranged or
            // hidden something (see DashboardWidgetPreferences).
            $table->unique(['user_id', 'widget_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widget_preferences');
    }
};
