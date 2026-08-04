<?php

namespace Tests\Feature\Apartment;

use App\Models\Apartment;
use App\Models\Booking;
use App\Models\MitraProfile;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApartmentBookingModuleTest extends TestCase
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
            'business_name' => 'Apartment Approved Co',
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

    private function publishedApartment(User $mitra, array $overrides = []): Apartment
    {
        return $mitra->mitraProfile->apartments()->create(array_merge([
            'name' => 'Apartemen Kemang Village',
            'slug' => 'apartemen-kemang-village-'.uniqid(),
            'city' => 'Jakarta',
            'capacity_guest' => 3,
            'bedroom_count' => 1,
            'bathroom_count' => 1,
            'base_price' => 500000,
            'status' => 'published',
        ], $overrides));
    }

    private function paidBooking(User $user, Apartment $apartment, string $status, \DateTimeInterface $checkIn): Booking
    {
        $booking = Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => Apartment::class,
            'bookable_id' => $apartment->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => (clone $checkIn)->modify('+3 days')->format('Y-m-d'),
            'guest_count' => 2,
            'subtotal' => 1500000,
            'total_price' => 1500000,
            'commission_amount' => 150000,
            'mitra_payout_amount' => 1350000,
            'status' => $status,
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

    public function test_availability_check_returns_correct_price_for_open_dates(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $this->publishedApartment($mitra, ['base_price' => 500000]);

        $response = $this->fromFrontend()->getJson(
            "/api/apartments/{$apartment->slug}/availability?check_in_date=2026-08-10&check_out_date=2026-08-13&guest_count=2"
        );

        $response->assertOk();
        $response->assertJsonPath('data.available', true);
        $response->assertJsonPath('data.nights', 3);
        $response->assertJsonPath('data.total_price', 1500000);
        $response->assertJsonPath('data.commission_amount', 150000);
        $response->assertJsonPath('data.mitra_payout_amount', 1350000);
    }

    public function test_availability_check_enforces_guest_capacity(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $this->publishedApartment($mitra, ['capacity_guest' => 2]);

        $response = $this->fromFrontend()->getJson(
            "/api/apartments/{$apartment->slug}/availability?check_in_date=2026-08-10&check_out_date=2026-08-12&guest_count=5"
        );

        $response->assertJsonPath('data.available', false);
    }

    public function test_availability_check_blocks_overlapping_confirmed_booking(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $this->publishedApartment($mitra);
        $user = $this->regularUser();
        $this->paidBooking($user, $apartment, 'dikonfirmasi', now()->addDays(10));

        $response = $this->fromFrontend()->getJson(
            "/api/apartments/{$apartment->slug}/availability?check_in_date=".now()->addDays(11)->format('Y-m-d')
            ."&check_out_date=".now()->addDays(14)->format('Y-m-d')
        );

        $response->assertJsonPath('data.available', false);
    }

    // --- Booking creation ---

    public function test_user_can_create_booking_for_an_apartment(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $this->publishedApartment($mitra, ['base_price' => 500000]);
        $user = $this->regularUser();

        $response = $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'bookable_type' => 'apartment',
            'bookable_id' => $apartment->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-13',
            'guest_count' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending_payment');
        $response->assertJsonPath('data.total_price', 1500000);
        $response->assertJsonPath('data.bookable.type', 'apartment');
        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'bookable_type' => Apartment::class,
            'bookable_id' => $apartment->id,
            'status' => 'pending_payment',
        ]);
    }

    public function test_cannot_book_an_apartment_that_is_not_published(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $this->publishedApartment($mitra, ['status' => 'draft']);
        $user = $this->regularUser();

        $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'bookable_type' => 'apartment',
            'bookable_id' => $apartment->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12',
            'guest_count' => 2,
        ])->assertNotFound();
    }

    // --- Payment ---

    public function test_user_can_initiate_and_webhook_completes_payment(): void
    {
        Http::fake([
            'api.xendit.co/v2/invoices' => Http::response([
                'id' => 'inv_apartment_test',
                'invoice_url' => 'https://checkout-staging.xendit.co/web/inv_apartment_test',
            ], 200),
        ]);
        config(['services.xendit.callback_token' => 'test-callback-token']);

        $mitra = $this->approvedMitra();
        $apartment = $this->publishedApartment($mitra, ['base_price' => 500000]);
        $user = $this->regularUser();

        $created = $this->fromFrontend()->actingAs($user)->postJson('/api/bookings', [
            'bookable_type' => 'apartment',
            'bookable_id' => $apartment->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-13',
            'guest_count' => 2,
        ]);
        $bookingId = $created->json('data.id');
        $bookingCode = $created->json('data.booking_code');

        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$bookingId}/pay")
            ->assertCreated();

        $response = $this->withHeaders(['x-callback-token' => 'test-callback-token'])
            ->postJson('/api/webhooks/xendit', [
                'id' => 'inv_apartment_test',
                'external_id' => $bookingCode,
                'status' => 'PAID',
                'payment_method' => 'BANK_TRANSFER',
                'paid_at' => now()->toIso8601String(),
            ]);

        $response->assertOk();
        $booking = Booking::find($bookingId);
        // Mitra never approves/rejects — a paid booking is confirmed immediately.
        $this->assertSame('dikonfirmasi', $booking->status);
        $this->assertNotNull($booking->mitra_confirmed_at);
    }

    // --- User cancellation refund tiers ---

    public function test_user_cancelling_confirmed_apartment_booking_h2_or_more_gets_85_percent_refund(): void
    {
        Http::fake([
            'api.xendit.co/refunds' => Http::response(['id' => 'rfd_apartment_2', 'status' => 'SUCCEEDED'], 200),
        ]);
        $mitra = $this->approvedMitra();
        $apartment = $this->publishedApartment($mitra);
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, $apartment, 'dikonfirmasi', now()->addDays(10));

        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertOk();

        $booking->refresh();
        $this->assertSame('dibatalkan_user', $booking->status);
        $this->assertSame(85, $booking->refund_percentage);
    }

    public function test_user_cancelling_confirmed_apartment_booking_within_h2_gets_zero_refund(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $this->publishedApartment($mitra);
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, $apartment, 'dikonfirmasi', now()->addDay());

        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertOk();

        $booking->refresh();
        $this->assertSame(0, $booking->refund_percentage);
        Http::assertNothingSent();
    }

    // --- Review ---

    public function test_user_can_review_a_completed_apartment_booking(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $this->publishedApartment($mitra);
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, $apartment, 'selesai', now()->subDays(10));

        $response = $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/review", ['rating' => 5, 'comment' => 'Unitnya bersih dan strategis']);

        $response->assertCreated();
        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'reviewable_type' => Apartment::class,
            'reviewable_id' => $apartment->id,
            'rating' => 5,
        ]);
    }

    public function test_public_can_list_reviews_for_a_published_apartment(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $this->publishedApartment($mitra);
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, $apartment, 'selesai', now()->subDays(10));
        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/review", ['rating' => 4, 'comment' => 'Oke banget'])
            ->assertCreated();

        $response = $this->fromFrontend()->getJson("/api/apartments/{$apartment->slug}/reviews");

        $response->assertOk();
        $response->assertJsonFragment(['comment' => 'Oke banget']);
    }

    public function test_apartment_listing_shows_review_aggregates(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $this->publishedApartment($mitra);
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, $apartment, 'selesai', now()->subDays(10));
        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/review", ['rating' => 4])
            ->assertCreated();

        $response = $this->fromFrontend()->getJson("/api/apartments/{$apartment->slug}");

        $response->assertJsonPath('data.reviews_count', 1);
        $response->assertJsonPath('data.reviews_avg_rating', 4);
    }

    // --- Payout & mitra dashboard: cross-category (villa + apartment) correctness ---

    public function test_payout_batches_completed_bookings_across_villa_and_apartment(): void
    {
        Http::fake([
            'api.xendit.co/disbursements' => Http::response(['id' => 'disb_mixed_apartment_1', 'status' => 'COMPLETED'], 200),
        ]);
        $mitra = $this->approvedMitra();
        $villa = $mitra->mitraProfile->villas()->create([
            'name' => 'Villa Mixed Apartment', 'slug' => 'villa-mixed-apartment-'.uniqid(), 'city' => 'Bali',
            'capacity_guest' => 4, 'base_price' => 1000000, 'status' => 'published',
        ]);
        $apartment = $this->publishedApartment($mitra);

        Booking::create([
            'booking_code' => 'BK'.uniqid(), 'user_id' => $this->regularUser()->id,
            'bookable_type' => Villa::class, 'bookable_id' => $villa->id,
            'check_in_date' => '2026-06-01', 'check_out_date' => '2026-06-03', 'guest_count' => 2,
            'subtotal' => 2000000, 'total_price' => 2000000, 'commission_amount' => 200000, 'mitra_payout_amount' => 1800000,
            'status' => 'selesai',
        ]);
        Booking::create([
            'booking_code' => 'BK'.uniqid(), 'user_id' => $this->regularUser()->id,
            'bookable_type' => Apartment::class, 'bookable_id' => $apartment->id,
            'check_in_date' => '2026-06-01', 'check_out_date' => '2026-06-03', 'guest_count' => 2,
            'subtotal' => 1000000, 'total_price' => 1000000, 'commission_amount' => 100000, 'mitra_payout_amount' => 900000,
            'status' => 'selesai',
        ]);

        $response = $this->fromFrontend()->actingAs($this->admin())->postJson('/api/admin/payouts/run');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.amount', 2700000);
        $response->assertJsonPath('data.0.booking_count', 2);
        $this->assertSame(1, Payout::count());
    }

    public function test_mitra_stats_include_apartment_bookings_alongside_villa(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $this->publishedApartment($mitra);
        $this->paidBooking($this->regularUser(), $apartment, 'selesai', now()->subDays(20));

        $response = $this->fromFrontend()->actingAs($mitra)->getJson('/api/mitra/stats');

        $response->assertOk();
        $response->assertJsonPath('data.total_apartments', 1);
        $response->assertJsonPath('data.published_apartments', 1);
        $response->assertJsonPath('data.booking_counts.selesai', 1);
    }
}
