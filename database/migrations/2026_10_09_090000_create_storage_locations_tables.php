<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where uploaded bytes can be kept: the server's own disk, plus any
        // number of buckets. One row is the default for new uploads.
        Schema::create('storage_locations', function (Blueprint $table) {
            $table->id();
            // Shown to clients choosing where to upload, with the note
            // below. Never the bucket.
            $table->string('name');
            $table->string('client_note')->nullable();
            // The Laravel disk this location's files are stamped with
            // (files.disk). Fixed once created: changing it would orphan
            // every file already stored here.
            $table->string('disk')->unique();
            $table->string('provider');
            $table->boolean('accepts_uploads')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('use_instance_role')->default(false);
            $table->string('key')->nullable();
            $table->text('secret')->nullable();
            $table->text('key_file')->nullable();
            $table->string('bucket')->nullable();
            $table->string('region')->nullable();
            $table->string('endpoint')->nullable();
            $table->boolean('use_path_style')->default(false);
            $table->string('root')->nullable();
            $table->timestamps();
        });

        // A client's locations, in the order their uploads prefer them.
        Schema::create('client_storage_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storage_location_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('priority');
            $table->unique(['user_id', 'storage_location_id']);
        });

        Schema::table('upload_sessions', function (Blueprint $table) {
            // The location the uploader chose when the session was created.
            // Null means "their first", resolved when the bytes are written.
            $table->foreignId('storage_location_id')->nullable()->after('previous_file_id')
                ->constrained('storage_locations')->nullOnDelete();
        });

        $now = now();
        $legacy = DB::table('external_storage_settings')->orderBy('id')->first();
        $legacyHasBucket = $legacy !== null && is_string($legacy->bucket) && $legacy->bucket !== '';
        // The old screen's "Use external storage for new uploads" made the
        // bucket where every new upload went: that is the default now.
        $legacyWasDefault = $legacyHasBucket && (bool) $legacy->active;

        DB::table('storage_locations')->insert([
            'name' => 'Server storage',
            'disk' => 'files',
            'provider' => 'local',
            'accepts_uploads' => true,
            'is_default' => ! $legacyWasDefault,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Kept on its old disk name, so every file already stamped
        // 'files_external' keeps resolving. Copied whether or not it was
        // switched on: files can be there from when it last was. The
        // secret and key file are copied as stored; the new model decrypts
        // them with the same key.
        if ($legacyHasBucket) {
            DB::table('storage_locations')->insert([
                'name' => 'External storage',
                'disk' => 'files_external',
                'provider' => $legacy->provider,
                'accepts_uploads' => true,
                'is_default' => $legacyWasDefault,
                'use_instance_role' => (bool) $legacy->use_instance_role,
                'key' => $legacy->key,
                'secret' => $legacy->secret,
                'key_file' => $legacy->key_file,
                'bucket' => $legacy->bucket,
                'region' => $legacy->region,
                'endpoint' => $legacy->endpoint,
                'use_path_style' => (bool) $legacy->use_path_style,
                'root' => $legacy->root,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // external_storage_settings stays: an install rolled back to the
        // previous release reads it, and nothing writes it any more.

        // This process booted before the tables above existed and cached
        // the old row's picture under the storage locations key. Drop it so
        // the next boot reads the rows just written.
        Cache::forget('platform.storage_locations.v5');
    }

    public function down(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storage_location_id');
        });
        Schema::dropIfExists('client_storage_location');
        Schema::dropIfExists('storage_locations');
    }
};
