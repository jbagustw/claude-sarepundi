<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\MitraProfile;
use App\Models\Payment;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserCancellationTest extends TestCase
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

    private function regularUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
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

    private function paidBooking(User $user, string $status, \DateTimeInterface $checkIn): Booking
    {
        $booking = Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => Villa::class,
            'bookable_id' => $this->villa()->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => (clone $checkIn)->modify('+3 days')->format('Y-m-d'),
            'guest_count' => 2,
            'subtotal' => 3000000,
            'total_price' => 3000000,
            'commission_amount' => 300000,
            'mitra_payout_amount' => 2700000,
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

    private function fakeRefundSuccess(): void
    {
        Http::fake([
            'api.xendit.co/refunds' => Http::response(['id' => 'rfd_test', 'status' => 'SUCCEEDED'], 200),
        ]);
    }

    public function test_user_can_cancel_booking_awaiting_confirmation_with_full_refund(): void
    {
        $this->fakeRefundSuccess();
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, 'menunggu_konfirmasi', now()->addDays(10));

        $response = $this->fromFrontend()->actingAs($user)->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk();
        $booking->refresh();
        $this->assertSame('dibatalkan_user', $booking->status);
        $this->assertSame('user_cancel_pending', $booking->cancellation_reason);
        $this->assertSame(100, $booking->refund_percentage);
        $this->assertSame($booking->total_price, $booking->refund_amount);
        $this->assertDatabaseHas('refunds', [
            'booking_id' => $booking->id,
            'reason' => 'user_cancel_pending',
            'percentage' => 100,
            'status' => 'succeeded',
        ]);
        $this->assertSame('refunded', $booking->payments()->latest()->first()->status);
    }

    public function test_user_can_cancel_confirmed_booking_at_or_beyond_h2_with_85_percent_refund(): void
    {
        $this->fakeRefundSuccess();
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, 'dikonfirmasi', now()->addDays(2));

        $response = $this->fromFrontend()->actingAs($user)->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk();
        $booking->refresh();
        $this->assertSame('dibatalkan_user', $booking->status);
        $this->assertSame('user_cancel_confirmed', $booking->cancellation_reason);
        $this->assertSame(85, $booking->refund_percentage);
        $this->assertSame((int) round($booking->total_price * 0.85), $booking->refund_amount);
        $this->assertDatabaseHas('refunds', [
            'booking_id' => $booking->id,
            'reason' => 'user_cancel_confirmed',
            'percentage' => 85,
        ]);
    }

    public function test_user_cancelling_confirmed_booking_within_h2_gets_zero_refund(): void
    {
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, 'dikonfirmasi', now()->addDay());

        $response = $this->fromFrontend()->actingAs($user)->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk();
        $booking->refresh();
        $this->assertSame('dibatalkan_user', $booking->status);
        $this->assertSame(0, $booking->refund_percentage);
        $this->assertSame(0, $booking->refund_amount);
        // No refund actually owed, so no Xendit call and no Refund record.
        $this->assertDatabaseCount('refunds', 0);
        Http::assertNothingSent();
    }

    public function test_user_cannot_cancel_a_booking_still_pending_payment(): void
    {
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, 'pending_payment', now()->addDays(10));

        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertForbidden();

        $this->assertSame('pending_payment', $booking->fresh()->status);
    }

    public function test_user_cannot_cancel_a_completed_booking(): void
    {
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, 'selesai', now()->subDays(5));

        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertForbidden();
    }

    public function test_user_cannot_cancel_another_users_booking(): void
    {
        $owner = $this->regularUser();
        $booking = $this->paidBooking($owner, 'menunggu_konfirmasi', now()->addDays(10));
        $other = $this->regularUser();

        $this->fromFrontend()->actingAs($other)
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertForbidden();

        $this->assertSame('menunggu_konfirmasi', $booking->fresh()->status);
    }

    public function test_refund_failure_during_user_cancellation_still_cancels_booking(): void
    {
        Http::fake(['api.xendit.co/refunds' => Http::response(['message' => 'error'], 500)]);
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, 'menunggu_konfirmasi', now()->addDays(10));

        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertOk();

        $booking->refresh();
        $this->assertSame('dibatalkan_user', $booking->status);
        $this->assertDatabaseHas('refunds', ['booking_id' => $booking->id, 'status' => 'failed']);
    }

    public function test_mitra_role_cannot_hit_user_cancel_endpoint(): void
    {
        $user = $this->regularUser();
        $booking = $this->paidBooking($user, 'menunggu_konfirmasi', now()->addDays(10));

        $mitra = User::factory()->create();
        $mitra->assignRole('mitra');
        MitraProfile::create(['user_id' => $mitra->id, 'business_name' => 'X', 'status' => 'approved']);

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertForbidden();
    }
}
