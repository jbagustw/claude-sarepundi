<?php

namespace Tests\Feature\Transport;

use App\Models\Booking;
use App\Models\MitraProfile;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransportBookingModuleTest extends TestCase
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
            'business_name' => 'Transport E2E Co',
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

    private function publishedTransport(User $mitra, array $overrides = []): Transport
    {
        return $mitra->mitraProfile->transports()->create(array_merge([
            'name' => 'Hiace Commuter E2E',
            'slug' => 'hiace-commuter-e2e-'.uniqid(),
            'vehicle_type' => 'Minibus',
            'city' => 'Yogyakarta',
            'capacity' => 15,
            'price_per_day_self_drive' => 600000,
            'price_per_day_with_driver' => 900000,
            'status' => 'published',
        ], $overrides));
    }

    private function paidBooking(User $user, Transport $transport, string $status, \DateTimeInterface $checkIn, bool $withDriver = false): Booking
    {
        $booking = Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => Transport::class,
            'bookable_id' => $transport->id,
            'transport_with_driver' => $withDriver,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => (clone $checkIn)->modify('+2 days')->format('Y-m-d'),
            'guest_count' => 4,
            'subtotal' => 1200000,
            'total_price' => 1200000,
            'commission_amount' => 120000,
            'mitra_payout_amount' => 1080000,
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

    public function test_availability_returns_self_drive_price_when_requested(): void
    {
        $mitra = $this->approvedMitra();
        $transport = $this->publishedTransport($mitra);

        $response = $this->fromFrontend()->getJson(
            "/api/transports/{$transport->slug}/availability?check_in_date=2026-08-10&check_out_date=2026-08-13&with_driver=0"
        );

        $response->assertOk();
        $response->assertJsonPath('data.available', true);
        $response->assertJsonPath('data.total_price', 1800000); // 3 days x 600,000
    }

    public function test_availability_returns_with_driver_price_when_requested(): void
    {
        $mitra = $this->approvedMitra();
        $transport = $this->publishedTransport($mitra);

        $response = $this->fromFrontend()->getJson(
            "/api/transports/{$transport->slug}/availability?check_in_date=2026-08-10&check_out_date=2026-08-13&with_driver=1"
        );

        $response->assertJsonPath('data.total_price', 2700000); // 3 days x 900,000
    }

    public function test_availability_rejects_unsupported_driver_option(): void
    {
        $mitra = $this->approvedMitra();
        $transport = $this->publishedTransport($mitra, ['price_per_day_with_driver' => null]);

        $response = $this->fromFrontend()->getJson(
            "/api/transports/{$transport->slug}/availability?check_in_date=2026-08-10&check_out_date=2026-08-13&with_driver=1"
        );

        $response->assertJsonPath('data.available', false);
    }

    public function test_availability_enforces_capacity(): void
    {
        $mitra = $this->approvedMitra();
        $transport = $this->publishedTransport($mitra, ['capacity' => 4]);

        $response = $this->fromFrontend()->getJson(
            "/api/transports/{$transport->slug}/availability?check_in_date=2026-08-10&check_out_date=2026-08-13&guest_count=10&with_driver=0"
        );

        $response->assertJsonPath('data.available', false);
    }

    // --- Booking creation ---

    public function test_user_can_create_booking_for_a_transport(): void
    {
        $mitra = $this->approvedMitra();
        $transport = $this->publishedTransport($mitra);
        $user = $this->regularUser();

        $response = $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'bookable_type' => 'transport',
            'bookable_id' => $transport->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-13',
            'guest_count' => 4,
            'with_driver' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.total_price', 2700000);
        $response->assertJsonPath('data.bookable.type', 'transport');
        $response->assertJsonPath('data.transport_with_driver', true);
        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'bookable_type' => Transport::class,
            'bookable_id' => $transport->id,
            'transport_with_driver' => true,
        ]);
    }

    public function test_booking_creation_rejects_overlapping_dates(): void
    {
        $mitra = $this->approvedMitra();
        $transport = $this->publishedTransport($mitra);
        $this->paidBooking($this->regularUser(), $transport, 'dikonfirmasi', now()->addDays(10));

        $response = $this->fromFrontend()->actingAs($this->regularUser())->postJson('/api/bookings', [
            'bookable_type' => 'transport',
            'bookable_id' => $transport->id,
            'check_in_date' => now()->addDays(11)->format('Y-m-d'),
            'check_out_date' => now()->addDays(14)->format('Y-m-d'),
            'guest_count' => 4,
            'with_driver' => false,
        ]);

        $response->assertStatus(422);
    }

    // --- Mitra confirmation & cancellation ---

    public function test_mitra_can_accept_a_transport_booking(): void
    {
        $mitra = $this->approvedMitra();
        $transport = $this->publishedTransport($mitra);
        $booking = $this->paidBooking($this->regularUser(), $transport, 'menunggu_konfirmasi', now()->addDays(10));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/bookings/{$booking->id}/accept")
            ->assertOk();

        $this->assertSame('dikonfirmasi', $booking->fresh()->status);
    }

    public function test_mitra_reject_triggers_full_refund_for_transport_booking(): void
    {
        Http::fake([
            'api.xendit.co/refunds' => Http::response(['id' => 'rfd_tr_1', 'status' => 'SUCCEEDED'], 200),
        ]);
        $mitra = $this->approvedMitra();
        $transport = $this->publishedTransport($mitra);
        $booking = $this->paidBooking($this->regularUser(), $transport, 'menunggu_konfirmasi', now()->addDays(10));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/bookings/{$booking->id}/reject")
            ->assertOk();

        $booking->refresh();
        $this->assertSame('dibatalkan_mitra', $booking->status);
        $this->assertSame(100, $booking->refund_percentage);
    }

    // --- Review ---

    public function test_user_can_review_a_completed_transport_booking(): void
    {
        $mitra = $this->approvedMitra();
        $transport = $this->publishedTransport($mitra);
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, $transport, 'selesai', now()->subDays(10));

        $response = $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/review", ['rating' => 5, 'comment' => 'Mobilnya bersih dan nyaman']);

        $response->assertCreated();
        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'reviewable_type' => Transport::class,
            'reviewable_id' => $transport->id,
            'rating' => 5,
        ]);
    }

    public function test_transport_listing_shows_review_aggregates(): void
    {
        $mitra = $this->approvedMitra();
        $transport = $this->publishedTransport($mitra);
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, $transport, 'selesai', now()->subDays(10));
        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/review", ['rating' => 4])
            ->assertCreated();

        $response = $this->fromFrontend()->getJson("/api/transports/{$transport->slug}");

        $response->assertJsonPath('data.reviews_count', 1);
        $response->assertJsonPath('data.reviews_avg_rating', 4);
    }

    public function test_public_can_list_reviews_for_a_published_transport(): void
    {
        $mitra = $this->approvedMitra();
        $transport = $this->publishedTransport($mitra);
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, $transport, 'selesai', now()->subDays(10));
        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/review", ['rating' => 5, 'comment' => 'Sopirnya ramah'])
            ->assertCreated();

        $response = $this->fromFrontend()->getJson("/api/transports/{$transport->slug}/reviews");

        $response->assertOk();
        $response->assertJsonFragment(['comment' => 'Sopirnya ramah']);
    }

    // --- Payout: cross-category correctness ---

    public function test_payout_batches_transport_bookings_alongside_villa(): void
    {
        Http::fake([
            'api.xendit.co/disbursements' => Http::response(['id' => 'disb_tr_1', 'status' => 'COMPLETED'], 200),
        ]);
        $mitra = $this->approvedMitra();
        $transport = $this->publishedTransport($mitra);
        $villa = $mitra->mitraProfile->villas()->create([
            'name' => 'Villa Transport Mixed', 'slug' => 'villa-transport-mixed-'.uniqid(), 'city' => 'Bali',
            'capacity_guest' => 4, 'base_price' => 1000000, 'status' => 'published',
        ]);

        $this->paidBooking($this->regularUser(), $transport, 'selesai', now()->subDays(20));
        Booking::create([
            'booking_code' => 'BK'.uniqid(), 'user_id' => $this->regularUser()->id,
            'bookable_type' => \App\Models\Villa::class, 'bookable_id' => $villa->id,
            'check_in_date' => '2026-06-01', 'check_out_date' => '2026-06-03', 'guest_count' => 2,
            'subtotal' => 2000000, 'total_price' => 2000000, 'commission_amount' => 200000, 'mitra_payout_amount' => 1800000,
            'status' => 'selesai',
        ]);

        $response = $this->fromFrontend()->actingAs($this->admin())->postJson('/api/admin/payouts/run');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.booking_count', 2);
        $this->assertSame(1, Payout::count());
    }
}
