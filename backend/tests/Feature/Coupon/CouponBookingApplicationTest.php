<?php

namespace Tests\Feature\Coupon;

use App\Models\Coupon;
use App\Models\GatheringVenue;
use App\Models\Homestay;
use App\Models\MitraProfile;
use App\Models\Transport;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CouponBookingApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'mitra', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function fromFrontend(): static
    {
        return $this->withHeaders(['Origin' => 'http://localhost:3000']);
    }

    private function approvedMitra(): User
    {
        $user = User::factory()->create();
        $user->assignRole('mitra');
        MitraProfile::create(['user_id' => $user->id, 'business_name' => 'Coupon Co', 'status' => 'approved']);

        return $user;
    }

    private function regularUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    private function publishedVilla(User $mitra, array $overrides = []): Villa
    {
        return $mitra->mitraProfile->villas()->create(array_merge([
            'name' => 'Villa Kupon', 'slug' => 'villa-kupon-'.uniqid(), 'city' => 'Bali',
            'capacity_guest' => 6, 'base_price' => 1000000, 'status' => 'published',
        ], $overrides));
    }

    private function coupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'TESTCODE'.uniqid(),
            'title' => 'Diskon Uji Coba',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ], $overrides));
    }

    // --- Villa booking with coupon ---

    public function test_percentage_coupon_reduces_total_price_on_villa_booking(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $this->publishedVilla($mitra, ['base_price' => 1000000]);
        $coupon = $this->coupon(['code' => 'HEMAT20', 'discount_type' => 'percentage', 'discount_value' => 20]);
        $user = $this->regularUser();

        $response = $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'bookable_type' => 'villa',
            'bookable_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-13', // 3 nights x 1,000,000 = 3,000,000
            'guest_count' => 2,
            'coupon_code' => 'hemat20', // lowercase on purpose — must match case-insensitively
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.subtotal', 3000000);
        $response->assertJsonPath('data.discount_amount', 600000); // 20% of 3,000,000
        $response->assertJsonPath('data.total_price', 2400000);
        $response->assertJsonPath('data.coupon_code', 'HEMAT20');

        $booking = \App\Models\Booking::where('bookable_id', $villa->id)->firstOrFail();
        $this->assertSame($coupon->id, $booking->coupon_id);
        // Commission + payout must still reconcile to the discounted total.
        $this->assertSame($booking->total_price, $booking->commission_amount + $booking->mitra_payout_amount);
    }

    public function test_fixed_coupon_is_capped_at_subtotal_so_total_never_goes_negative(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $this->publishedVilla($mitra, ['base_price' => 100000]);
        $this->coupon(['code' => 'BESAR', 'discount_type' => 'fixed', 'discount_value' => 5000000]);
        $user = $this->regularUser();

        $response = $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'bookable_type' => 'villa',
            'bookable_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-11', // 1 night x 100,000 = 100,000
            'guest_count' => 2,
            'coupon_code' => 'BESAR',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.subtotal', 100000);
        $response->assertJsonPath('data.discount_amount', 100000); // capped, not 5,000,000
        $response->assertJsonPath('data.total_price', 0);
    }

    public function test_invalid_coupon_code_is_rejected(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $this->publishedVilla($mitra);
        $user = $this->regularUser();

        $response = $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'bookable_type' => 'villa',
            'bookable_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12',
            'guest_count' => 2,
            'coupon_code' => 'TIDAKADA',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('coupon_code');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_expired_coupon_is_rejected(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $this->publishedVilla($mitra);
        $this->coupon(['code' => 'EXPIRED', 'valid_until' => now()->subDay()->toDateString()]);
        $user = $this->regularUser();

        $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'bookable_type' => 'villa',
            'bookable_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12',
            'guest_count' => 2,
            'coupon_code' => 'EXPIRED',
        ])->assertStatus(422);
    }

    public function test_inactive_coupon_is_rejected(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $this->publishedVilla($mitra);
        $this->coupon(['code' => 'NONAKTIF', 'is_active' => false]);
        $user = $this->regularUser();

        $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'bookable_type' => 'villa',
            'bookable_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12',
            'guest_count' => 2,
            'coupon_code' => 'NONAKTIF',
        ])->assertStatus(422);
    }

    public function test_booking_without_coupon_code_has_zero_discount(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $this->publishedVilla($mitra, ['base_price' => 1000000]);
        $user = $this->regularUser();

        $response = $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'bookable_type' => 'villa',
            'bookable_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12',
            'guest_count' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.subtotal', 2000000);
        $response->assertJsonPath('data.discount_amount', 0);
        $response->assertJsonPath('data.total_price', 2000000);
        $response->assertJsonPath('data.coupon_code', null);
    }

    public function test_availability_check_preview_reflects_coupon_discount(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $this->publishedVilla($mitra, ['base_price' => 1000000]);
        $this->coupon(['code' => 'PREVIEW15', 'discount_type' => 'percentage', 'discount_value' => 15]);

        $response = $this->fromFrontend()->getJson(
            "/api/villas/{$villa->slug}/availability?check_in_date=2026-08-10&check_out_date=2026-08-12&coupon_code=PREVIEW15"
        );

        $response->assertOk();
        $response->assertJsonPath('data.subtotal', 2000000);
        $response->assertJsonPath('data.discount_amount', 300000);
        $response->assertJsonPath('data.total_price', 1700000);
    }

    // --- Parity across the other 3 categories ---

    public function test_coupon_applies_to_homestay_booking(): void
    {
        $mitra = $this->approvedMitra();
        $homestay = $mitra->mitraProfile->homestays()->create([
            'name' => 'Homestay Kupon', 'slug' => 'homestay-kupon-'.uniqid(), 'city' => 'Yogyakarta',
            'capacity_guest' => 4, 'bedroom_count' => 2, 'bathroom_count' => 1, 'base_price' => 500000, 'status' => 'published',
        ]);
        $this->coupon(['code' => 'HOMESTAY10', 'discount_type' => 'percentage', 'discount_value' => 10]);
        $user = $this->regularUser();

        $response = $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'bookable_type' => 'homestay',
            'bookable_id' => $homestay->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12', // 2 nights x 500,000 = 1,000,000
            'guest_count' => 2,
            'coupon_code' => 'HOMESTAY10',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.discount_amount', 100000);
        $response->assertJsonPath('data.total_price', 900000);
    }

    public function test_coupon_applies_to_gathering_venue_booking(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $mitra->mitraProfile->gatheringVenues()->create([
            'name' => 'Aula Kupon', 'slug' => 'aula-kupon-'.uniqid(), 'city' => 'Jakarta',
            'capacity' => 100, 'status' => 'published',
        ]);
        $slot = $venue->slots()->create([
            'name' => 'Sesi Pagi', 'start_time' => '08:00', 'end_time' => '12:00', 'price' => 1000000, 'is_active' => true,
        ]);
        $this->coupon(['code' => 'GATHER25', 'discount_type' => 'fixed', 'discount_value' => 250000]);
        $user = $this->regularUser();

        $response = $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'bookable_type' => 'gathering_venue',
            'bookable_id' => $venue->id,
            'gathering_venue_slot_id' => $slot->id,
            'check_in_date' => '2026-08-10',
            'guest_count' => 50,
            'coupon_code' => 'GATHER25',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.subtotal', 1000000);
        $response->assertJsonPath('data.discount_amount', 250000);
        $response->assertJsonPath('data.total_price', 750000);
    }

    public function test_coupon_applies_to_transport_booking(): void
    {
        $mitra = $this->approvedMitra();
        $transport = $mitra->mitraProfile->transports()->create([
            'name' => 'Hiace Kupon', 'slug' => 'hiace-kupon-'.uniqid(), 'vehicle_type' => 'Minibus',
            'city' => 'Surabaya', 'capacity' => 15, 'price_per_day_self_drive' => 600000, 'status' => 'published',
        ]);
        $this->coupon(['code' => 'TRANS10', 'discount_type' => 'percentage', 'discount_value' => 10]);
        $user = $this->regularUser();

        $response = $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'bookable_type' => 'transport',
            'bookable_id' => $transport->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12', // 2 days x 600,000 = 1,200,000
            'guest_count' => 4,
            'with_driver' => false,
            'coupon_code' => 'TRANS10',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.discount_amount', 120000);
        $response->assertJsonPath('data.total_price', 1080000);
    }
}
