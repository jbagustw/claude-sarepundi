<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\MitraProfile;
use App\Models\Payment;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MitraConfirmationTest extends TestCase
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
        MitraProfile::create(['user_id' => $user->id, 'business_name' => 'Villa Co', 'status' => 'approved']);

        return $user;
    }

    private function regularUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    private function publishedVilla(User $mitra): Villa
    {
        return $mitra->mitraProfile->villas()->create([
            'name' => 'Villa Damai',
            'slug' => 'villa-damai-'.uniqid(),
            'city' => 'Yogyakarta',
            'capacity_guest' => 4,
            'base_price' => 1000000,
            'status' => 'published',
        ]);
    }

    /**
     * A booking that has already been paid for and is awaiting the
     * mitra's decision, with a real Payment record behind it (needed so
     * the refund path in BookingCancellationService has something to
     * refund).
     */
    private function awaitingConfirmationBooking(User $mitra, ?User $renter = null, ?\DateTimeInterface $deadline = null): Booking
    {
        $renter ??= $this->regularUser();
        $villa = $this->publishedVilla($mitra);

        $booking = Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => $renter->id,
            'villa_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-13',
            'guest_count' => 2,
            'total_price' => 3000000,
            'commission_amount' => 300000,
            'mitra_payout_amount' => 2700000,
            'status' => 'menunggu_konfirmasi',
            'mitra_confirmation_deadline' => $deadline ?? now()->addHours(24),
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

    private function fakeRefundSuccess(): void
    {
        Http::fake([
            'api.xendit.co/refunds' => Http::response(['id' => 'rfd_test_1', 'status' => 'SUCCEEDED'], 200),
        ]);
    }

    // --- Accept ---

    public function test_mitra_can_accept_booking_for_own_villa(): void
    {
        $mitra = $this->approvedMitra();
        $booking = $this->awaitingConfirmationBooking($mitra);

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/bookings/{$booking->id}/accept");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'dikonfirmasi');
        $booking->refresh();
        $this->assertSame('dikonfirmasi', $booking->status);
        $this->assertNotNull($booking->mitra_confirmed_at);
    }

    public function test_mitra_cannot_accept_booking_for_another_mitras_villa(): void
    {
        $owner = $this->approvedMitra();
        $booking = $this->awaitingConfirmationBooking($owner);
        $otherMitra = $this->approvedMitra();

        $this->fromFrontend()->actingAs($otherMitra)
            ->postJson("/api/mitra/bookings/{$booking->id}/accept")
            ->assertForbidden();
    }

    public function test_accepting_already_decided_booking_fails(): void
    {
        $mitra = $this->approvedMitra();
        $booking = $this->awaitingConfirmationBooking($mitra);
        $booking->update(['status' => 'dikonfirmasi']);

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/bookings/{$booking->id}/accept")
            ->assertStatus(422);
    }

    public function test_user_role_cannot_hit_mitra_confirmation_endpoints(): void
    {
        $mitra = $this->approvedMitra();
        $booking = $this->awaitingConfirmationBooking($mitra);
        $user = $this->regularUser();

        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/mitra/bookings/{$booking->id}/accept")
            ->assertForbidden();
    }

    // --- Reject ---

    public function test_mitra_reject_cancels_booking_and_triggers_full_refund(): void
    {
        $this->fakeRefundSuccess();
        $mitra = $this->approvedMitra();
        $booking = $this->awaitingConfirmationBooking($mitra);

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/bookings/{$booking->id}/reject");

        $response->assertOk();
        $booking->refresh();

        $this->assertSame('dibatalkan_mitra', $booking->status);
        $this->assertSame('mitra_reject', $booking->cancellation_reason);
        $this->assertSame(100, $booking->refund_percentage);
        $this->assertSame($booking->total_price, $booking->refund_amount);
        $this->assertNotNull($booking->cancelled_at);

        $this->assertDatabaseHas('refunds', [
            'booking_id' => $booking->id,
            'reason' => 'mitra_reject',
            'percentage' => 100,
            'status' => 'succeeded',
            'xendit_refund_id' => 'rfd_test_1',
        ]);
        $this->assertSame('refunded', $booking->payments()->latest()->first()->status);
    }

    public function test_reject_still_cancels_booking_even_if_refund_api_fails(): void
    {
        Http::fake(['api.xendit.co/refunds' => Http::response(['message' => 'error'], 500)]);
        $mitra = $this->approvedMitra();
        $booking = $this->awaitingConfirmationBooking($mitra);

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/bookings/{$booking->id}/reject")
            ->assertOk();

        $booking->refresh();
        $this->assertSame('dibatalkan_mitra', $booking->status);
        $this->assertDatabaseHas('refunds', [
            'booking_id' => $booking->id,
            'status' => 'failed',
        ]);
    }

    public function test_mitra_cannot_reject_another_mitras_booking(): void
    {
        $owner = $this->approvedMitra();
        $booking = $this->awaitingConfirmationBooking($owner);
        $otherMitra = $this->approvedMitra();

        $this->fromFrontend()->actingAs($otherMitra)
            ->postJson("/api/mitra/bookings/{$booking->id}/reject")
            ->assertForbidden();

        $this->assertSame('menunggu_konfirmasi', $booking->fresh()->status);
    }

    // --- Index ---

    public function test_mitra_booking_index_only_shows_bookings_for_own_villas(): void
    {
        $mitra = $this->approvedMitra();
        $ownBooking = $this->awaitingConfirmationBooking($mitra);

        $otherMitra = $this->approvedMitra();
        $this->awaitingConfirmationBooking($otherMitra);

        $response = $this->fromFrontend()->actingAs($mitra)->getJson('/api/mitra/bookings');

        $codes = collect($response->json('data'))->pluck('booking_code');
        $this->assertTrue($codes->contains($ownBooking->booking_code));
        $this->assertCount(1, $codes);
    }

    // --- Auto-cancel scheduled command ---

    public function test_auto_cancel_command_cancels_expired_bookings_with_refund(): void
    {
        $this->fakeRefundSuccess();
        $mitra = $this->approvedMitra();
        $expired = $this->awaitingConfirmationBooking($mitra, deadline: now()->subHour());

        Artisan::call('bookings:auto-cancel-expired');

        $expired->refresh();
        $this->assertSame('dibatalkan_mitra', $expired->status);
        $this->assertSame('mitra_timeout', $expired->cancellation_reason);
        $this->assertSame(100, $expired->refund_percentage);
        $this->assertDatabaseHas('refunds', ['booking_id' => $expired->id, 'reason' => 'mitra_timeout']);
    }

    public function test_auto_cancel_command_leaves_non_expired_bookings_alone(): void
    {
        $mitra = $this->approvedMitra();
        $notYetExpired = $this->awaitingConfirmationBooking($mitra, deadline: now()->addHours(2));

        Artisan::call('bookings:auto-cancel-expired');

        $this->assertSame('menunggu_konfirmasi', $notYetExpired->fresh()->status);
    }

    public function test_auto_cancel_command_ignores_bookings_not_awaiting_confirmation(): void
    {
        $mitra = $this->approvedMitra();
        $confirmed = $this->awaitingConfirmationBooking($mitra, deadline: now()->subDay());
        $confirmed->update(['status' => 'dikonfirmasi']);

        Artisan::call('bookings:auto-cancel-expired');

        $this->assertSame('dikonfirmasi', $confirmed->fresh()->status);
    }
}
