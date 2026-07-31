<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra_profiles')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('vehicle_type');
            $table->text('description')->nullable();
            $table->unsignedInteger('capacity');
            $table->string('city');
            $table->string('province')->nullable();
            $table->unsignedBigInteger('price_per_day_self_drive')->nullable();
            $table->unsignedBigInteger('price_per_day_with_driver')->nullable();
            $table->enum('status', ['draft', 'pending_review', 'published', 'rejected', 'inactive'])->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transports');
    }
};
