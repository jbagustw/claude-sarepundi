<?php

namespace Tests\Feature\GatheringVenue;

use App\Models\Booking;
use App\Models\GatheringVenue;
use App\Models\GatheringVenueSlot;
use App\Models\MitraProfile;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GatheringVenueBookingModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'mitra', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        config(['services.xendit.secret_key' => 'test-secret-key']);
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
            'business_name' => 'Gathering E2E Co',
            'status' => 'approved',
            'bank_name' => 'BCA',
            'bank_account' => '1234567890',
        ]);

        return $user;
    }

    private function regularUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function publishedVenue(User $mitra, array $overrides = []): GatheringVenue
    {
        return $mitra->mitraProfile->gatheringVenues()->create(array_merge([
            'name' => 'Aula Serbaguna E2E',
            'slug' => 'aula-serbaguna-e2e-'.uniqid(),
            'city' => 'Jakarta',
            'capacity' => 50,
            'status' => 'published',
        ], $overrides));
    }

    private function slot(GatheringVenue $venue, array $overrides = []): GatheringVenueSlot
    {
        return $venue->slots()->create(array_merge([
            'name' => 'Sesi Pagi',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'price' => 800000,
            'is_active' => true,
        ], $overrides));
    }

    private function paidBooking(User $user, GatheringVenue $venue, GatheringVenueSlot $slot, string $status, \DateTimeInterface $eventDate): Booking
    {
        $booking = Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => GatheringVenue::class,
            'bookable_id' => $venue->id,
            'gathering_venue_slot_id' => $slot->id,
            'check_in_date' => $eventDate->format('Y-m-d'),
            'check_out_date' => $eventDate->format('Y-m-d'),
            'guest_count' => 20,
            'total_price' => $slot->price,
            'commission_amount' => (int) round($slot->price * 0.1),
            'mitra_payout_amount' => $slot->price - (int) round($slot->price * 0.1),
            'status' => $status,
            'mitra_confirmation_deadline' => $status === 'menunggu_konfirmasi' ? now()->addHours(24) : null,
            'mitra_confirmed_at' => $status === 'dikonfirmasi' ? now() : null,
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'xendit_invoice_id' => 'inv_'.uniqid(),
            'amount' => $booking->total_price,
            'status' => 'success',
            'paid_at' => now(),
        ]);

        return $booking;
    }

    // --- Availability ---

    public function test_availability_lists_all_active_slots_for_a_date(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $this->publishedVenue($mitra);
        $this->slot($venue, ['name' => 'Sesi Pagi', 'start_time' => '08:00', 'end_time' => '12:00', 'price' => 800000]);
        $this->slot($venue, ['name' => 'Sesi Sore', 'start_time' => '13:00', 'end_time' => '17:00', 'price' => 900000]);
        $this->slot($venue, ['name' => 'Sesi Nonaktif', 'is_active' => false]);

        $response = $this->fromFrontend()->getJson(
            "/api/gathering-venues/{$venue->slug}/availability?date=2026-09-01"
        );

        $response->assertOk();
        $response->assertJsonPath('data.date', '2026-09-01');
        $response->assertJsonCount(2, 'data.slots');
        $response->assertJsonFragment(['name' => 'Sesi Pagi', 'price' => 800000, 'available' => true]);
    }

    public function test_availability_marks_slot_unavailable_once_booked_for_that_date(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $this->publishedVenue($mitra);
        $slot = $this->slot($venue);
        $this->paidBooking($this->regularUser(), $venue, $slot, 'dikonfirmasi', now()->addDays(10));

        $response = $this->fromFrontend()->getJson(
            "/api/gathering-venues/{$venue->slug}/availability?date=".now()->addDays(10)->format('Y-m-d')
        );

        $response->assertJsonPath('data.slots.0.available', false);
    }

    // --- Booking creation ---

    public function test_user_can_create_booking_for_a_gathering_venue_slot(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $this->publishedVenue($mitra);
        $slot = $this->slot($venue, ['price' => 800000]);
        $user = $this->regularUser();

        $response = $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'bookable_type' => 'gathering_venue',
            'bookable_id' => $venue->id,
            'gathering_venue_slot_id' => $slot->id,
            'check_in_date' => '2026-09-01',
            'guest_count' => 20,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending_payment');
        $response->assertJsonPath('data.total_price', 800000);
        $response->assertJsonPath('data.bookable.type', 'gathering_venue');
        $response->assertJsonPath('data.slot.name', 'Sesi Pagi');
        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'bookable_type' => GatheringVenue::class,
            'bookable_id' => $venue->id,
            'gathering_venue_slot_id' => $slot->id,
            'status' => 'pending_payment',
        ]);
        $created = Booking::where('bookable_type', GatheringVenue::class)->where('bookable_id', $venue->id)->firstOrFail();
        $this->assertSame('2026-09-01', $created->check_in_date->toDateString());
        $this->assertSame('2026-09-01', $created->check_out_date->toDateString());
    }

    public function test_cannot_book_the_same_slot_twice_for_the_same_date(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $this->publishedVenue($mitra);
        $slot = $this->slot($venue);
        $this->paidBooking($this->regularUser(), $venue, $slot, 'menunggu_konfirmasi', now()->addDays(10));

        $response = $this->fromFrontend()->actingAs($this->regularUser())->postJson('/api/bookings', [
            'bookable_type' => 'gathering_venue',
            'bookable_id' => $venue->id,
            'gathering_venue_slot_id' => $slot->id,
            'check_in_date' => now()->addDays(10)->format('Y-m-d'),
            'guest_count' => 10,
        ]);

        $response->assertStatus(422);
    }

    public function test_booking_rejects_guest_count_over_venue_capacity(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $this->publishedVenue($mitra, ['capacity' => 10]);
        $slot = $this->slot($venue);

        $this->fromFrontend()->actingAs($this->regularUser())->postJson('/api/bookings', [
            'bookable_type' => 'gathering_venue',
            'bookable_id' => $venue->id,
            'gathering_venue_slot_id' => $slot->id,
            'check_in_date' => '2026-09-01',
            'guest_count' => 25,
        ])->assertStatus(422);
    }

    public function test_cannot_book_an_inactive_slot(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $this->publishedVenue($mitra);
        $slot = $this->slot($venue, ['is_active' => false]);

        $this->fromFrontend()->actingAs($this->regularUser())->postJson('/api/bookings', [
            'bookable_type' => 'gathering_venue',
            'bookable_id' => $venue->id,
            'gathering_venue_slot_id' => $slot->id,
            'check_in_date' => '2026-09-01',
            'guest_count' => 5,
        ])->assertStatus(422);
    }

    // --- Mitra confirmation & cancellation (reusing the shared pipeline) ---

    public function test_mitra_can_accept_a_gathering_venue_booking(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $this->publishedVenue($mitra);
        $slot = $this->slot($venue);
        $booking = $this->paidBooking($this->regularUser(), $venue, $slot, 'menunggu_konfirmasi', now()->addDays(10));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/bookings/{$booking->id}/accept")
            ->assertOk();

        $this->assertSame('dikonfirmasi', $booking->fresh()->status);
    }

    public function test_mitra_reject_triggers_full_refund_for_gathering_venue_booking(): void
    {
        Http::fake([
            'api.xendit.co/refunds' => Http::response(['id' => 'rfd_gv_1', 'status' => 'SUCCEEDED'], 200),
        ]);
        $mitra = $this->approvedMitra();
        $venue = $this->publishedVenue($mitra);
        $slot = $this->slot($venue);
        $booking = $this->paidBooking($this->regularUser(), $venue, $slot, 'menunggu_konfirmasi', now()->addDays(10));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/bookings/{$booking->id}/reject")
            ->assertOk();

        $booking->refresh();
        $this->assertSame('dibatalkan_mitra', $booking->status);
        $this->assertSame(100, $booking->refund_percentage);
    }

    // --- Review ---

    public function test_user_can_review_a_completed_gathering_venue_booking(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $this->publishedVenue($mitra);
        $slot = $this->slot($venue);
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, $venue, $slot, 'selesai', now()->subDays(10));

        $response = $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/review", ['rating' => 5, 'comment' => 'Acaranya lancar']);

        $response->assertCreated();
        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'reviewable_type' => GatheringVenue::class,
            'reviewable_id' => $venue->id,
            'rating' => 5,
        ]);
    }

    public function test_gathering_venue_listing_shows_review_aggregates(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $this->publishedVenue($mitra);
        $slot = $this->slot($venue);
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, $venue, $slot, 'selesai', now()->subDays(10));
        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/review", ['rating' => 4])
            ->assertCreated();

        $response = $this->fromFrontend()->getJson("/api/gathering-venues/{$venue->slug}");

        $response->assertJsonPath('data.reviews_count', 1);
        $response->assertJsonPath('data.reviews_avg_rating', 4);
    }

    // --- Slot deletion guard ---

    public function test_mitra_cannot_delete_a_slot_that_already_has_a_booking(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $this->publishedVenue($mitra);
        $slot = $this->slot($venue);
        $this->paidBooking($this->regularUser(), $venue, $slot, 'menunggu_konfirmasi', now()->addDays(10));

        $this->fromFrontend()->actingAs($mitra)
            ->deleteJson("/api/mitra/gathering-venues/{$venue->id}/slots/{$slot->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('gathering_venue_slots', ['id' => $slot->id]);
    }

    public function test_mitra_can_delete_a_slot_with_no_bookings(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $this->publishedVenue($mitra);
        $slot = $this->slot($venue);

        $this->fromFrontend()->actingAs($mitra)
            ->deleteJson("/api/mitra/gathering-venues/{$venue->id}/slots/{$slot->id}")
            ->assertOk();

        $this->assertDatabaseMissing('gathering_venue_slots', ['id' => $slot->id]);
    }

    // --- Payout & mitra dashboard: cross-category correctness ---

    public function test_payout_batches_gathering_venue_bookings_alongside_villa(): void
    {
        Http::fake([
            'api.xendit.co/disbursements' => Http::response(['id' => 'disb_gv_1', 'status' => 'COMPLETED'], 200),
        ]);
        $mitra = $this->approvedMitra();
        $venue = $this->publishedVenue($mitra);
        $slot = $this->slot($venue, ['price' => 1000000]);
        $villa = $mitra->mitraProfile->villas()->create([
            'name' => 'Villa GV Mixed', 'slug' => 'villa-gv-mixed-'.uniqid(), 'city' => 'Bali',
            'capacity_guest' => 4, 'base_price' => 1000000, 'status' => 'published',
        ]);

        $this->paidBooking($this->regularUser(), $venue, $slot, 'selesai', now()->subDays(20));
        Booking::create([
            'booking_code' => 'BK'.uniqid(), 'user_id' => $this->regularUser()->id,
            'bookable_type' => \App\Models\Villa::class, 'bookable_id' => $villa->id,
            'check_in_date' => '2026-06-01', 'check_out_date' => '2026-06-03', 'guest_count' => 2,
            'total_price' => 2000000, 'commission_amount' => 200000, 'mitra_payout_amount' => 1800000,
            'status' => 'selesai',
        ]);

        $response = $this->fromFrontend()->actingAs($this->admin())->postJson('/api/admin/payouts/run');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.booking_count', 2);
        $this->assertSame(1, Payout::count());
    }
}
