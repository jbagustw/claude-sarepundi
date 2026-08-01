<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('bookable_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('subtotal')->nullable()->after('coupon_id');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('subtotal');
        });

        // Existing bookings predate coupons entirely — their subtotal is
        // just their (undiscounted) total_price.
        DB::table('bookings')->update(['subtotal' => DB::raw('total_price')]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('subtotal')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn(['coupon_id', 'subtotal', 'discount_amount']);
        });
    }
};
