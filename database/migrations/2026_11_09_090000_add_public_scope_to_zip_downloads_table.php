<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zip_downloads', function (Blueprint $table) {
            // A zip asked for by a visitor on a public page has no account
            // behind it, so the requester becomes optional. Such a row is
            // owned by the visitor's session instead (PublicZipDownloadsController),
            // and is authorised by the public page it came from, below.
            $table->foreignId('requested_by')->nullable()->change();

            // The public page the selection came from: 'index', 'group' or
            // 'folder', and the group's or folder's id. The build and the
            // download both re-read what is public *now* through it, the
            // way a signed-in requester's access is re-read through
            // requested_by. Null on every zip a signed-in user asked for.
            $table->string('public_scope_type', 16)->nullable()->after('requested_by');
            $table->unsignedBigInteger('public_scope_id')->nullable()->after('public_scope_type');
        });
    }

    public function down(): void
    {
        // Visitor rows cannot survive requested_by becoming required again.
        DB::table('zip_downloads')->whereNull('requested_by')->delete();

        Schema::table('zip_downloads', function (Blueprint $table) {
            $table->dropColumn(['public_scope_type', 'public_scope_id']);
            $table->foreignId('requested_by')->nullable(false)->change();
        });
    }
};
