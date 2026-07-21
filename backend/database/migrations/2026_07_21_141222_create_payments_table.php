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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method')->nullable();
            $table->string('xendit_invoice_id')->nullable()->unique();
            $table->string('xendit_payment_id')->nullable();
            $table->string('invoice_url')->nullable();
            $table->unsignedBigInteger('amount');
            $table->enum('status', ['pending', 'success', 'failed', 'refunded', 'partial_refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
