<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('public');
        });

        $usedSlugs = [];

        foreach (DB::table('files')->orderBy('id')->get(['id', 'name']) as $file) {
            $base = Str::slug($file->name) ?: 'file';
            $slug = $base;
            $suffix = 2;

            while (in_array($slug, $usedSlugs, true)) {
                $slug = $base.'-'.$suffix;
                $suffix++;
            }

            $usedSlugs[] = $slug;

            DB::table('files')->where('id', $file->id)->update(['slug' => $slug]);
        }

        Schema::table('files', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
