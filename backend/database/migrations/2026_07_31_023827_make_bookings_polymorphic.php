<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bookings now cover Villa AND Homestay (and, eventually, Gathering
     * Venue / Transport), so the single `villa_id` FK is replaced with a
     * polymorphic `bookable_type` + `bookable_id` pair. Existing rows are
     * backfilled to point at Villa before the old column is dropped, so no
     * booking history is lost.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('bookable_type')->nullable()->after('user_id');
            $table->unsignedBigInteger('bookable_id')->nullable()->after('bookable_type');
        });

        DB::table('bookings')->whereNotNull('villa_id')->update([
            'bookable_type' => 'App\\Models\\Villa',
            'bookable_id' => DB::raw('villa_id'),
        ]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('bookable_type')->nullable(false)->change();
            $table->unsignedBigInteger('bookable_id')->nullable(false)->change();

            $table->dropForeign(['villa_id']);
            $table->dropIndex(['villa_id', 'check_in_date', 'check_out_date']);
            $table->dropColumn('villa_id');

            $table->index(['bookable_type', 'bookable_id', 'check_in_date', 'check_out_date'], 'bookings_bookable_dates_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('villa_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        DB::table('bookings')->where('bookable_type', 'App\\Models\\Villa')->update([
            'villa_id' => DB::raw('bookable_id'),
        ]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_bookable_dates_index');
            $table->dropColumn(['bookable_type', 'bookable_id']);
            $table->foreignId('villa_id')->nullable(false)->change();
            $table->index(['villa_id', 'check_in_date', 'check_out_date']);
        });
    }
};
