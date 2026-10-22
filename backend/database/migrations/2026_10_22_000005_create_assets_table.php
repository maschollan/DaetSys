<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('asset_code')->unique();
            $table->string('qr_token', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('serial_number')->nullable()->unique();
            $table->enum('status', ['available', 'in_transfer', 'maintenance', 'missing'])->default('available');
            $table->date('acquisition_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('category_id');
            $table->index(['location_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
