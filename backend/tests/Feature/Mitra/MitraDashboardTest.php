<?php

namespace Tests\Feature\Mitra;

use App\Models\Booking;
use App\Models\GatheringVenue;
use App\Models\MitraProfile;
use App\Models\Transport;
use App\Models\User;
use App\Models\Villa;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MitraDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'mitra', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fromFrontend(): static
    {
        return $this->withHeaders(['Origin' => 'http://localhost:3000']);
    }

    private function approvedMitra(): User
    {
        $user = User::factory()->create();
        $user->assignRole('mitra');
        MitraProfile::create(['user_id' => $user->id, 'business_name' => 'Villa Co', 'status' => 'approved']);

        return $user;
    }

    private function villa(User $mitra, array $overrides = []): Villa
    {
        return $mitra->mitraProfile->villas()->create(array_merge([
            'name' => 'Villa Damai',
            'slug' => 'villa-damai-'.uniqid(),
            'city' => 'Yogyakarta',
            'capacity_guest' => 4,
            'base_price' => 1000000,
            'status' => 'published',
        ], $overrides));
    }

    private function booking(Villa $villa, string $status, string $checkIn, string $checkOut, int $totalPrice = 2000000): Booking
    {
        return Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Villa::class,
            'bookable_id' => $villa->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'subtotal' => $totalPrice,
            'total_price' => $totalPrice,
            'commission_amount' => (int) ($totalPrice * 0.1),
            'mitra_payout_amount' => (int) ($totalPrice * 0.9),
            'status' => $status,
        ]);
    }

    public function test_mitra_can_view_stats(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $this->villa($mitra);
        $this->booking($villa, 'selesai', '2026-06-01', '2026-06-03');
        $this->booking($villa, 'menunggu_konfirmasi', '2026-08-01', '2026-08-03');

        $response = $this->fromFrontend()->actingAs($mitra)->getJson('/api/mitra/stats');

        $response->assertOk();
        $response->assertJsonPath('data.total_villas', 1);
        $response->assertJsonPath('data.published_villas', 1);
        $response->assertJsonPath('data.booking_counts.selesai', 1);
        $response->assertJsonPath('data.booking_counts.menunggu_konfirmasi', 1);
    }

    public function test_stats_only_count_own_villas(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $this->villa($mitra);
        $this->booking($villa, 'selesai', '2026-06-01', '2026-06-03');

        $otherMitra = $this->approvedMitra();
        $otherVilla = $this->villa($otherMitra);
        $this->booking($otherVilla, 'selesai', '2026-06-01', '2026-06-03');

        $response = $this->fromFrontend()->actingAs($mitra)->getJson('/api/mitra/stats');

        $response->assertJsonPath('data.booking_counts.selesai', 1);
        $response->assertJsonPath('data.total_villas', 1);
    }

    public function test_pendapatan_only_counts_completed_bookings(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $this->villa($mitra);
        $this->booking($villa, 'selesai', '2026-06-01', '2026-06-03', totalPrice: 1000000); // payout 900,000
        $this->booking($villa, 'dikonfirmasi', '2026-08-01', '2026-08-03', totalPrice: 5000000); // not counted yet

        $response = $this->fromFrontend()->actingAs($mitra)->getJson('/api/mitra/stats');

        $response->assertJsonPath('data.total_pendapatan', 900000);
    }

    public function test_occupancy_rate_reflects_confirmed_bookings_this_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15'));

        $mitra = $this->approvedMitra();
        $villa = $this->villa($mitra); // 1 published villa, July has 31 days -> 31 villa-nights

        // 5 nights fully inside July: booked nights = 5
        $this->booking($villa, 'dikonfirmasi', '2026-07-10', '2026-07-15');
        // pending booking shouldn't count toward occupancy
        $this->booking($villa, 'menunggu_konfirmasi', '2026-07-20', '2026-07-25');

        $response = $this->fromFrontend()->actingAs($mitra)->getJson('/api/mitra/stats');

        // 5 / 31 * 100 = 16.1
        $response->assertJsonPath('data.occupancy_rate', 16.1);
    }

    public function test_occupancy_rate_clips_bookings_that_span_month_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15'));

        $mitra = $this->approvedMitra();
        $villa = $this->villa($mitra);

        // Spans June 28 -> July 3: only July 1, 2 count (2 nights within July)
        $this->booking($villa, 'checked_in', '2026-06-28', '2026-07-03');

        $response = $this->fromFrontend()->actingAs($mitra)->getJson('/api/mitra/stats');

        // 2 / 31 * 100 = 6.5
        $response->assertJsonPath('data.occupancy_rate', 6.5);
    }

    public function test_occupancy_rate_ignores_gathering_venue_bookings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15'));

        $mitra = $this->approvedMitra();
        $this->villa($mitra); // 1 published villa, no bookings -> 0 booked nights

        $venue = $mitra->mitraProfile->gatheringVenues()->create([
            'name' => 'Aula Serbaguna', 'slug' => 'aula-serbaguna-'.uniqid(),
            'city' => 'Yogyakarta', 'capacity' => 50, 'status' => 'published',
        ]);
        $slot = $venue->slots()->create([
            'name' => 'Sesi Pagi', 'start_time' => '08:00', 'end_time' => '12:00',
            'price' => 1000000, 'is_active' => true,
        ]);
        Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => GatheringVenue::class,
            'bookable_id' => $venue->id,
            'gathering_venue_slot_id' => $slot->id,
            'check_in_date' => '2026-07-20',
            'check_out_date' => '2026-07-20',
            'guest_count' => 20,
            'subtotal' => 1000000,
            'total_price' => 1000000,
            'commission_amount' => 100000,
            'mitra_payout_amount' => 900000,
            'status' => 'dikonfirmasi',
        ]);

        $response = $this->fromFrontend()->actingAs($mitra)->getJson('/api/mitra/stats');

        // A confirmed gathering venue booking must not count as "booked
        // nights" toward the villa/homestay occupancy rate.
        $response->assertJsonPath('data.occupancy_rate', 0);
        $response->assertJsonPath('data.total_gathering_venues', 1);
        $response->assertJsonPath('data.published_gathering_venues', 1);
    }

    public function test_occupancy_rate_includes_confirmed_transport_bookings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15'));

        $mitra = $this->approvedMitra();
        $transport = $mitra->mitraProfile->transports()->create([
            'name' => 'Hiace', 'slug' => 'hiace-'.uniqid(), 'vehicle_type' => 'Minibus',
            'city' => 'Yogyakarta', 'capacity' => 15, 'price_per_day_self_drive' => 500000,
            'status' => 'published',
        ]);
        // Only published listing is the transport -> 31 listing-days in July.
        Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Transport::class,
            'bookable_id' => $transport->id,
            'transport_with_driver' => false,
            'check_in_date' => '2026-07-10',
            'check_out_date' => '2026-07-15',
            'guest_count' => 4,
            'subtotal' => 2500000,
            'total_price' => 2500000,
            'commission_amount' => 250000,
            'mitra_payout_amount' => 2250000,
            'status' => 'dikonfirmasi',
        ]);

        $response = $this->fromFrontend()->actingAs($mitra)->getJson('/api/mitra/stats');

        // 5 / 31 * 100 = 16.1, same shape as the villa occupancy test.
        $response->assertJsonPath('data.occupancy_rate', 16.1);
        $response->assertJsonPath('data.total_transports', 1);
        $response->assertJsonPath('data.published_transports', 1);
    }

    public function test_non_mitra_cannot_view_mitra_stats(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->fromFrontend()->actingAs($user)
            ->getJson('/api/mitra/stats')
            ->assertForbidden();
    }
}
