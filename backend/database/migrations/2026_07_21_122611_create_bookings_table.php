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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('villa_id')->constrained()->cascadeOnDelete();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedInteger('guest_count');
            $table->unsignedBigInteger('total_price');
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('mitra_payout_amount');
            $table->enum('status', [
                'pending_payment',
                'menunggu_konfirmasi',
                'dikonfirmasi',
                'dibatalkan_mitra',
                'dibatalkan_user',
                'checked_in',
                'selesai',
            ])->default('pending_payment');
            $table->timestamp('mitra_confirmed_at')->nullable();
            $table->timestamp('mitra_confirmation_deadline')->nullable();
            $table->enum('cancellation_reason', [
                'user_cancel_pending',
                'user_cancel_confirmed',
                'mitra_reject',
                'mitra_timeout',
            ])->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('refund_amount')->nullable();
            $table->unsignedTinyInteger('refund_percentage')->nullable();
            $table->timestamps();

            $table->index(['villa_id', 'check_in_date', 'check_out_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
