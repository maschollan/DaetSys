<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_handover', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->unique()->constrained('transfers')->cascadeOnDelete();
            $table->foreignId('handed_over_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('handed_over_by');
            $table->index('received_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_handover');
    }
};
