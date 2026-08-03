<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\MitraProfile;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingDocumentTest extends TestCase
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

    private function regularUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    private function approvedMitra(string $businessName = 'Villa Approved Co'): User
    {
        $user = User::factory()->create();
        $user->assignRole('mitra');
        MitraProfile::create([
            'user_id' => $user->id,
            'business_name' => $businessName,
            'status' => 'approved',
        ]);

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function confirmedBooking(User $guest, ?User $mitra = null, array $overrides = []): Booking
    {
        $mitra ??= $this->approvedMitra();

        $villa = $mitra->mitraProfile->villas()->create([
            'name' => 'Villa Damai Yogyakarta',
            'slug' => 'villa-damai-yogyakarta-'.uniqid(),
            'city' => 'Yogyakarta',
            'capacity_guest' => 8,
            'bedroom_count' => 3,
            'bathroom_count' => 2,
            'base_price' => 1000000,
            'status' => 'published',
        ]);

        return Booking::create(array_merge([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => $guest->id,
            'bookable_type' => Villa::class,
            'bookable_id' => $villa->id,
            'check_in_date' => '2026-09-10',
            'check_out_date' => '2026-09-13',
            'guest_count' => 2,
            'subtotal' => 3000000,
            'total_price' => 3000000,
            'commission_amount' => 300000,
            'mitra_payout_amount' => 2700000,
            'status' => 'dikonfirmasi',
            'mitra_confirmed_at' => now(),
        ], $overrides));
    }

    public function test_owner_can_download_voucher_and_receipt(): void
    {
        $user = $this->regularUser();
        $booking = $this->confirmedBooking($user);

        $this->fromFrontend()->actingAs($user)
            ->get("/api/bookings/{$booking->id}/voucher")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->fromFrontend()->actingAs($user)
            ->get("/api/bookings/{$booking->id}/receipt")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_other_user_cannot_download_someone_elses_booking_documents(): void
    {
        $owner = $this->regularUser();
        $other = $this->regularUser();
        $booking = $this->confirmedBooking($owner);

        $this->fromFrontend()->actingAs($other)
            ->get("/api/bookings/{$booking->id}/voucher")
            ->assertForbidden();

        $this->fromFrontend()->actingAs($other)
            ->get("/api/bookings/{$booking->id}/receipt")
            ->assertForbidden();
    }

    public function test_mitra_can_download_documents_for_booking_on_own_listing(): void
    {
        $mitra = $this->approvedMitra();
        $booking = $this->confirmedBooking($this->regularUser(), $mitra);

        $this->fromFrontend()->actingAs($mitra)
            ->get("/api/bookings/{$booking->id}/voucher")
            ->assertOk();
    }

    public function test_mitra_cannot_download_documents_for_another_mitras_listing(): void
    {
        $booking = $this->confirmedBooking($this->regularUser());
        $otherMitra = $this->approvedMitra('Other Villa Co');

        $this->fromFrontend()->actingAs($otherMitra)
            ->get("/api/bookings/{$booking->id}/voucher")
            ->assertForbidden();
    }

    public function test_admin_can_download_any_booking_document(): void
    {
        $booking = $this->confirmedBooking($this->regularUser());

        $this->fromFrontend()->actingAs($this->admin())
            ->get("/api/bookings/{$booking->id}/receipt")
            ->assertOk();
    }

    public function test_documents_are_blocked_before_payment(): void
    {
        $user = $this->regularUser();
        $booking = $this->confirmedBooking($user, overrides: ['status' => 'pending_payment', 'mitra_confirmed_at' => null]);

        $this->fromFrontend()->actingAs($user)
            ->get("/api/bookings/{$booking->id}/voucher")
            ->assertForbidden();
    }

    public function test_documents_remain_downloadable_after_cancellation(): void
    {
        $user = $this->regularUser();
        $booking = $this->confirmedBooking($user, overrides: [
            'status' => 'dibatalkan_user',
            'cancellation_reason' => 'user_cancel_confirmed',
            'cancelled_at' => now(),
        ]);

        $this->fromFrontend()->actingAs($user)
            ->get("/api/bookings/{$booking->id}/receipt")
            ->assertOk();
    }
}
