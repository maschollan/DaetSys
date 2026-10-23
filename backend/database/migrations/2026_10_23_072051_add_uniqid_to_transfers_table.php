<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Kolom uniqid menjadi penanda detail transfer di URL (mengikuti pola
     * qr_token pada aset) sehingga id numerik tidak terekspos publik.
     */
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            // Nullable dulu supaya baris lama diisi ulang sebelum unik dipasang.
            $table->string('uniqid', 26)->nullable()->after('id');
        });

        DB::table('transfers')->whereNull('uniqid')->get()->each(function (object $row): void {
            DB::table('transfers')
                ->where('id', $row->id)
                ->update(['uniqid' => (string) Str::ulid()]);
        });

        // Baris pengisi `created_at` membuat event model tidak berjalan pada
        // migrasi, karenanya uniqid di-backfill manual di atas.
        Schema::table('transfers', function (Blueprint $table) {
            $table->unique('uniqid');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropUnique(['uniqid']);
            $table->dropColumn('uniqid');
        });
    }
};
