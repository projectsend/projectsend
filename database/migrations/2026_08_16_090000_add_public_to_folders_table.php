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
        Schema::table('folders', function (Blueprint $table) {
            // Mirrors files.public/files.slug exactly: independent of
            // sharing/assignment, a folder is only ever publicly reachable
            // when this is true, and every file in its live subtree
            // (however deep) inherits public status from it — see
            // Folder::scopePubliclyVisible / File::isEffectivelyPublic.
            $table->boolean('public')->default(false)->after('created_by');
            $table->string('slug')->nullable()->after('public');
            // Only meaningful (and only shown in the UI) once public is
            // true — a client uploading here still needs upload_to_public_
            // folders on top of this being on (see Folder::uploadableBy).
            $table->boolean('allow_client_uploads')->default(false)->after('slug');
        });

        $usedSlugs = [];

        foreach (DB::table('folders')->orderBy('id')->get(['id', 'name']) as $folder) {
            $base = Str::slug($folder->name) ?: 'folder';
            $slug = $base;
            $suffix = 2;

            while (in_array($slug, $usedSlugs, true)) {
                $slug = $base.'-'.$suffix;
                $suffix++;
            }

            $usedSlugs[] = $slug;

            DB::table('folders')->where('id', $folder->id)->update(['slug' => $slug]);
        }

        Schema::table('folders', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropColumn(['public', 'slug', 'allow_client_uploads']);
        });
    }
};
