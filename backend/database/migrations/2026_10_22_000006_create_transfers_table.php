<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('from_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'handover', 'completed', 'cancelled'])->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            /**
             * Guard tingkat database untuk Skenario 1 (concurrent update).
             *
             * Kolom ini berisi `asset_id` selama transfer masih aktif
             * (pending / approved / handover) dan NULL saat transfer selesai.
             * UNIQUE index memastikan MySQL yang menolak permintaan kedua,
             * bukan sekadar pengecekan di level aplikasi.
             */
            $table->unsignedBigInteger('active_asset_id')->nullable()->unique();

            $table->index(['status', 'requested_at']);
            $table->index('requested_by');
            $table->index('asset_id');
            $table->index(['from_location_id', 'to_location_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
