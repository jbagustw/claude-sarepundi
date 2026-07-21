<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\MitraProfile;
use App\Models\User;
use App\Models\Villa;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdvanceCompletedStaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'mitra', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        Carbon::setTestNow(Carbon::parse('2026-07-15'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function villa(): Villa
    {
        $mitra = User::factory()->create();
        $mitra->assignRole('mitra');
        MitraProfile::create(['user_id' => $mitra->id, 'business_name' => 'Villa Co', 'status' => 'approved']);

        return $mitra->mitraProfile->villas()->create([
            'name' => 'Villa Damai',
            'slug' => 'villa-damai-'.uniqid(),
            'city' => 'Yogyakarta',
            'capacity_guest' => 4,
            'base_price' => 1000000,
            'status' => 'published',
        ]);
    }

    private function booking(string $status, string $checkIn, string $checkOut): Booking
    {
        return Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'villa_id' => $this->villa()->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'total_price' => 2000000,
            'commission_amount' => 200000,
            'mitra_payout_amount' => 1800000,
            'status' => $status,
        ]);
    }

    public function test_advances_dikonfirmasi_to_checked_in_once_checkin_has_passed(): void
    {
        $booking = $this->booking('dikonfirmasi', '2026-07-10', '2026-07-20');

        Artisan::call('bookings:advance-completed-stays');

        $this->assertSame('checked_in', $booking->fresh()->status);
    }

    public function test_advances_checked_in_to_selesai_once_checkout_has_passed(): void
    {
        $booking = $this->booking('checked_in', '2026-07-05', '2026-07-10');

        Artisan::call('bookings:advance-completed-stays');

        $this->assertSame('selesai', $booking->fresh()->status);
    }

    public function test_jumps_dikonfirmasi_straight_to_selesai_if_checkout_already_passed(): void
    {
        $booking = $this->booking('dikonfirmasi', '2026-07-01', '2026-07-05');

        Artisan::call('bookings:advance-completed-stays');

        $this->assertSame('selesai', $booking->fresh()->status);
    }

    public function test_leaves_future_bookings_untouched(): void
    {
        $booking = $this->booking('dikonfirmasi', '2026-08-01', '2026-08-05');

        Artisan::call('bookings:advance-completed-stays');

        $this->assertSame('dikonfirmasi', $booking->fresh()->status);
    }

    public function test_leaves_cancelled_bookings_untouched(): void
    {
        $booking = $this->booking('dibatalkan_user', '2026-07-01', '2026-07-05');

        Artisan::call('bookings:advance-completed-stays');

        $this->assertSame('dibatalkan_user', $booking->fresh()->status);
    }
}
