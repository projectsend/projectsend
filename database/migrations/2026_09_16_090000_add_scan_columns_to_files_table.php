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
            // Where this file stands with the virus scanner. Every rule
            // about who may see or download it reads this one column
            // through FileAvailability, and nothing else compares the
            // strings. Existing rows default to "not scanned": they were
            // uploaded before there was a scanner, which is a fact about
            // them rather than a verdict.
            $table->string('scan_status', 24)->default('not_scanned')->index()->after('checksum');

            // The threat name when infected, or why it was not scanned.
            // See ScanStatus and NotScannedReason for the two vocabularies
            // this column carries.
            $table->string('scan_note')->nullable()->after('scan_status');
            $table->timestamp('scanned_at')->nullable()->after('scan_note');

            // Engine and definitions, as the scanner reported them at the
            // time. Kept so a verdict can be read back against what knew
            // it — definitions change daily.
            $table->string('scan_engine')->nullable()->after('scanned_at');
            $table->unsignedInteger('scan_attempts')->default(0)->after('scan_engine');

            // Whether this file could be downloaded before it was
            // quarantined — true only for one that went out unscanned
            // while the scanner was unreachable and was caught later.
            // Recorded on the file because it changes what an
            // administrator has to do, and because reconstructing it from
            // the activity log afterwards means reading every entry.
            $table->boolean('scan_was_available')->default(false)->after('scan_attempts');

            // Who overruled a quarantine, and when. The reason they gave
            // is in the activity log; this is what the file itself shows.
            $table->foreignId('released_by')->nullable()->after('scan_attempts')->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable()->after('released_by');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('released_by');
            $table->dropIndex(['scan_status']);
            $table->dropColumn(['scan_status', 'scan_note', 'scanned_at', 'scan_engine', 'scan_attempts', 'scan_was_available', 'released_at']);
        });
    }
};
