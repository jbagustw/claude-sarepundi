<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\MitraProfile;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingModuleTest extends TestCase
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
        MitraProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Villa Approved Co',
            'status' => 'approved',
        ]);

        return $user;
    }

    private function regularUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    private function publishedVilla(array $overrides = []): Villa
    {
        $mitra = $overrides['mitra'] ?? $this->approvedMitra();
        unset($overrides['mitra']);

        return $mitra->mitraProfile->villas()->create(array_merge([
            'name' => 'Villa Damai Yogyakarta',
            'slug' => 'villa-damai-yogyakarta-'.uniqid(),
            'city' => 'Yogyakarta',
            'capacity_guest' => 8,
            'bedroom_count' => 3,
            'bathroom_count' => 2,
            'base_price' => 1000000,
            'status' => 'published',
        ], $overrides));
    }

    // --- Mitra availability management ---

    public function test_mitra_can_block_out_dates_for_own_villa(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $this->publishedVilla(['mitra' => $mitra]);

        $response = $this->fromFrontend()->actingAs($mitra)
            ->putJson("/api/mitra/villas/{$villa->id}/availability", [
                'dates' => ['2026-08-01', '2026-08-02'],
                'is_available' => false,
            ]);

        $response->assertOk();
        $this->assertTrue(
            $villa->availability()->whereDate('date', '2026-08-01')->where('is_available', false)->exists()
        );
    }

    public function test_mitra_cannot_manage_availability_of_another_mitras_villa(): void
    {
        $villa = $this->publishedVilla();
        $otherMitra = $this->approvedMitra();

        $this->fromFrontend()->actingAs($otherMitra)
            ->putJson("/api/mitra/villas/{$villa->id}/availability", [
                'dates' => ['2026-08-01'],
                'is_available' => false,
            ])
            ->assertForbidden();
    }

    // --- Public availability check ---

    public function test_availability_check_returns_correct_price_for_open_dates(): void
    {
        $villa = $this->publishedVilla(['base_price' => 1000000]);

        $response = $this->fromFrontend()->getJson(
            "/api/villas/{$villa->slug}/availability?check_in_date=2026-08-10&check_out_date=2026-08-13&guest_count=2"
        );

        $response->assertOk();
        $response->assertJsonPath('data.available', true);
        $response->assertJsonPath('data.nights', 3);
        $response->assertJsonPath('data.total_price', 3000000);
        $response->assertJsonPath('data.commission_amount', 300000);
        $response->assertJsonPath('data.mitra_payout_amount', 2700000);
    }

    public function test_availability_check_respects_custom_price_override(): void
    {
        $villa = $this->publishedVilla(['base_price' => 1000000]);
        $villa->availability()->create(['date' => '2026-08-10', 'is_available' => true, 'custom_price' => 1500000]);

        $response = $this->fromFrontend()->getJson(
            "/api/villas/{$villa->slug}/availability?check_in_date=2026-08-10&check_out_date=2026-08-12"
        );

        $response->assertJsonPath('data.total_price', 1500000 + 1000000);
    }

    public function test_availability_check_blocks_dates_marked_unavailable(): void
    {
        $villa = $this->publishedVilla();
        $villa->availability()->create(['date' => '2026-08-11', 'is_available' => false]);

        $response = $this->fromFrontend()->getJson(
            "/api/villas/{$villa->slug}/availability?check_in_date=2026-08-10&check_out_date=2026-08-13"
        );

        $response->assertJsonPath('data.available', false);
    }

    public function test_availability_check_enforces_min_stay(): void
    {
        $villa = $this->publishedVilla();
        $villa->availability()->create(['date' => '2026-08-10', 'is_available' => true, 'min_stay' => 3]);

        $response = $this->fromFrontend()->getJson(
            "/api/villas/{$villa->slug}/availability?check_in_date=2026-08-10&check_out_date=2026-08-11"
        );

        $response->assertJsonPath('data.available', false);
        $response->assertJsonPath('data.reason', 'Minimum menginap 3 malam untuk tanggal check-in ini.');
    }

    public function test_availability_check_enforces_guest_capacity(): void
    {
        $villa = $this->publishedVilla(['capacity_guest' => 4]);

        $response = $this->fromFrontend()->getJson(
            "/api/villas/{$villa->slug}/availability?check_in_date=2026-08-10&check_out_date=2026-08-12&guest_count=10"
        );

        $response->assertJsonPath('data.available', false);
    }

    // --- Booking creation ---

    public function test_user_can_create_booking_for_available_dates(): void
    {
        $user = $this->regularUser();
        $villa = $this->publishedVilla(['base_price' => 1000000]);

        $response = $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'villa_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-13',
            'guest_count' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending_payment');
        $response->assertJsonPath('data.total_price', 3000000);
        $response->assertJsonPath('data.commission_amount', 300000);
        $response->assertJsonPath('data.mitra_payout_amount', 2700000);
        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'villa_id' => $villa->id,
            'status' => 'pending_payment',
        ]);
    }

    public function test_booking_creation_rejects_overlapping_dates(): void
    {
        $user = $this->regularUser();
        $villa = $this->publishedVilla();

        Booking::create([
            'booking_code' => 'BK-EXISTING',
            'user_id' => $user->id,
            'villa_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-13',
            'guest_count' => 2,
            'total_price' => 3000000,
            'commission_amount' => 300000,
            'mitra_payout_amount' => 2700000,
            'status' => 'menunggu_konfirmasi',
        ]);

        $response = $this->fromFrontend()->actingAs($this->regularUser())->postJson('/api/bookings', [
            'villa_id' => $villa->id,
            'check_in_date' => '2026-08-12',
            'check_out_date' => '2026-08-15',
            'guest_count' => 2,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('check_in_date');
    }

    public function test_booking_creation_allows_dates_after_a_cancelled_booking(): void
    {
        $user = $this->regularUser();
        $villa = $this->publishedVilla();

        Booking::create([
            'booking_code' => 'BK-CANCELLED',
            'user_id' => $user->id,
            'villa_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-13',
            'guest_count' => 2,
            'total_price' => 3000000,
            'commission_amount' => 300000,
            'mitra_payout_amount' => 2700000,
            'status' => 'dibatalkan_user',
        ]);

        $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'villa_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-13',
            'guest_count' => 2,
        ])->assertCreated();
    }

    public function test_booking_creation_rejects_guest_count_over_capacity(): void
    {
        $user = $this->regularUser();
        $villa = $this->publishedVilla(['capacity_guest' => 2]);

        $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'villa_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12',
            'guest_count' => 5,
        ])->assertStatus(422);
    }

    public function test_mitra_cannot_create_a_booking(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $this->publishedVilla();

        $this->fromFrontend()->actingAs($mitra)->postJson('/api/bookings', [
            'villa_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12',
            'guest_count' => 2,
        ])->assertForbidden();
    }

    public function test_cannot_book_a_villa_that_is_not_published(): void
    {
        $user = $this->regularUser();
        $villa = $this->publishedVilla(['status' => 'draft']);

        $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'villa_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12',
            'guest_count' => 2,
        ])->assertNotFound();
    }

    // --- Booking history / ownership ---

    public function test_user_can_view_own_bookings_but_not_others(): void
    {
        $owner = $this->regularUser();
        $other = $this->regularUser();
        $villa = $this->publishedVilla();

        $booking = Booking::create([
            'booking_code' => 'BK-OWNED',
            'user_id' => $owner->id,
            'villa_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12',
            'guest_count' => 2,
            'total_price' => 2000000,
            'commission_amount' => 200000,
            'mitra_payout_amount' => 1800000,
            'status' => 'pending_payment',
        ]);

        $this->fromFrontend()->actingAs($owner)
            ->getJson("/api/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking_code', 'BK-OWNED');

        $this->fromFrontend()->actingAs($other)
            ->getJson("/api/bookings/{$booking->id}")
            ->assertForbidden();
    }

    public function test_booking_index_only_lists_own_bookings(): void
    {
        $user = $this->regularUser();
        $villa = $this->publishedVilla();

        Booking::create([
            'booking_code' => 'BK-MINE',
            'user_id' => $user->id,
            'villa_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12',
            'guest_count' => 2,
            'total_price' => 2000000,
            'commission_amount' => 200000,
            'mitra_payout_amount' => 1800000,
            'status' => 'pending_payment',
        ]);

        Booking::create([
            'booking_code' => 'BK-OTHERS',
            'user_id' => $this->regularUser()->id,
            'villa_id' => $villa->id,
            'check_in_date' => '2026-09-10',
            'check_out_date' => '2026-09-12',
            'guest_count' => 2,
            'total_price' => 2000000,
            'commission_amount' => 200000,
            'mitra_payout_amount' => 1800000,
            'status' => 'pending_payment',
        ]);

        $response = $this->fromFrontend()->actingAs($user)->getJson('/api/bookings');

        $codes = collect($response->json('data'))->pluck('booking_code');
        $this->assertTrue($codes->contains('BK-MINE'));
        $this->assertFalse($codes->contains('BK-OTHERS'));
    }
}
