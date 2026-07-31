<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors the bookings polymorphism migration — reviews can now be
     * left for a Homestay stay too, not just a Villa.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('reviewable_type')->nullable()->after('user_id');
            $table->unsignedBigInteger('reviewable_id')->nullable()->after('reviewable_type');
        });

        DB::table('reviews')->whereNotNull('villa_id')->update([
            'reviewable_type' => 'App\\Models\\Villa',
            'reviewable_id' => DB::raw('villa_id'),
        ]);

        Schema::table('reviews', function (Blueprint $table) {
            $table->string('reviewable_type')->nullable(false)->change();
            $table->unsignedBigInteger('reviewable_id')->nullable(false)->change();

            $table->dropForeign(['villa_id']);
            $table->dropColumn('villa_id');

            $table->index(['reviewable_type', 'reviewable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('villa_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        DB::table('reviews')->where('reviewable_type', 'App\\Models\\Villa')->update([
            'villa_id' => DB::raw('reviewable_id'),
        ]);

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['reviewable_type', 'reviewable_id']);
            $table->dropColumn(['reviewable_type', 'reviewable_id']);
            $table->foreignId('villa_id')->nullable(false)->change();
        });
    }
};
