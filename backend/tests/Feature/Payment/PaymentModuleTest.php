<?php

namespace Tests\Feature\Payment;

use App\Models\MitraProfile;
use App\Models\Payment;
use App\Models\User;
use App\Models\Villa;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentModuleTest extends TestCase
{
    use RefreshDatabase;

    private const CALLBACK_TOKEN = 'test-callback-token';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'mitra', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        config([
            'services.xendit.secret_key' => 'test-secret-key',
            'services.xendit.callback_token' => self::CALLBACK_TOKEN,
        ]);
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

    private function pendingPaymentBooking(?User $user = null): Booking
    {
        $user ??= $this->regularUser();

        $mitra = User::factory()->create();
        $mitra->assignRole('mitra');
        MitraProfile::create(['user_id' => $mitra->id, 'business_name' => 'Villa Co', 'status' => 'approved']);

        $villa = $mitra->mitraProfile->villas()->create([
            'name' => 'Villa Damai',
            'slug' => 'villa-damai-'.uniqid(),
            'city' => 'Yogyakarta',
            'capacity_guest' => 4,
            'base_price' => 1000000,
            'status' => 'published',
        ]);

        return Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => Villa::class,
            'bookable_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-13',
            'guest_count' => 2,
            'total_price' => 3000000,
            'commission_amount' => 300000,
            'mitra_payout_amount' => 2700000,
            'status' => 'pending_payment',
        ]);
    }

    private function fakeXenditInvoiceSuccess(): void
    {
        Http::fake([
            'api.xendit.co/v2/invoices' => Http::response([
                'id' => 'inv_test_123',
                'invoice_url' => 'https://checkout-staging.xendit.co/web/inv_test_123',
            ], 200),
        ]);
    }

    // --- Initiating payment ---

    public function test_user_can_initiate_payment_for_own_pending_booking(): void
    {
        $this->fakeXenditInvoiceSuccess();
        $user = $this->regularUser();
        $booking = $this->pendingPaymentBooking($user);

        $response = $this->fromFrontend()->actingAs($user)->postJson("/api/bookings/{$booking->id}/pay");

        $response->assertCreated();
        $response->assertJsonPath('data.invoice_url', 'https://checkout-staging.xendit.co/web/inv_test_123');
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'xendit_invoice_id' => 'inv_test_123',
            'status' => 'pending',
            'amount' => 3000000,
        ]);
    }

    public function test_repeated_pay_request_reuses_existing_pending_invoice(): void
    {
        $this->fakeXenditInvoiceSuccess();
        $user = $this->regularUser();
        $booking = $this->pendingPaymentBooking($user);

        $this->fromFrontend()->actingAs($user)->postJson("/api/bookings/{$booking->id}/pay")->assertCreated();
        $this->fromFrontend()->actingAs($user)->postJson("/api/bookings/{$booking->id}/pay")->assertOk();

        Http::assertSentCount(1);
        $this->assertSame(1, Payment::where('booking_id', $booking->id)->count());
    }

    public function test_user_cannot_pay_for_another_users_booking(): void
    {
        $this->fakeXenditInvoiceSuccess();
        $booking = $this->pendingPaymentBooking();
        $other = $this->regularUser();

        $this->fromFrontend()->actingAs($other)
            ->postJson("/api/bookings/{$booking->id}/pay")
            ->assertForbidden();
    }

    public function test_cannot_pay_for_booking_not_in_pending_payment_status(): void
    {
        $user = $this->regularUser();
        $booking = $this->pendingPaymentBooking($user);
        $booking->update(['status' => 'menunggu_konfirmasi']);

        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/pay")
            ->assertStatus(422);
    }

    public function test_payment_fails_gracefully_when_xendit_not_configured(): void
    {
        config(['services.xendit.secret_key' => null]);
        $user = $this->regularUser();
        $booking = $this->pendingPaymentBooking($user);

        $response = $this->fromFrontend()->actingAs($user)->postJson("/api/bookings/{$booking->id}/pay");

        $response->assertStatus(422);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_fails_gracefully_when_xendit_api_errors(): void
    {
        Http::fake(['api.xendit.co/v2/invoices' => Http::response(['message' => 'server error'], 500)]);
        $user = $this->regularUser();
        $booking = $this->pendingPaymentBooking($user);

        $response = $this->fromFrontend()->actingAs($user)->postJson("/api/bookings/{$booking->id}/pay");

        $response->assertStatus(422);
        $this->assertDatabaseCount('payments', 0);
    }

    // --- Webhook ---

    public function test_webhook_marks_payment_success_and_advances_booking_status(): void
    {
        $booking = $this->pendingPaymentBooking();
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'xendit_invoice_id' => 'inv_test_456',
            'invoice_url' => 'https://checkout.xendit.co/web/inv_test_456',
            'amount' => $booking->total_price,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders(['x-callback-token' => self::CALLBACK_TOKEN])
            ->postJson('/api/webhooks/xendit', [
                'id' => 'inv_test_456',
                'external_id' => $booking->booking_code,
                'status' => 'PAID',
                'payment_method' => 'BANK_TRANSFER',
                'paid_at' => now()->toIso8601String(),
            ]);

        $response->assertOk();

        $payment->refresh();
        $booking->refresh();

        $this->assertSame('success', $payment->status);
        $this->assertSame('BANK_TRANSFER', $payment->payment_method);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('menunggu_konfirmasi', $booking->status);
        $this->assertNotNull($booking->mitra_confirmation_deadline);
        $this->assertEqualsWithDelta(
            now()->addHours(24)->timestamp,
            $booking->mitra_confirmation_deadline->timestamp,
            5
        );
    }

    public function test_webhook_rejects_invalid_callback_token(): void
    {
        $booking = $this->pendingPaymentBooking();
        Payment::create([
            'booking_id' => $booking->id,
            'xendit_invoice_id' => 'inv_test_789',
            'amount' => $booking->total_price,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders(['x-callback-token' => 'wrong-token'])
            ->postJson('/api/webhooks/xendit', ['id' => 'inv_test_789', 'status' => 'PAID']);

        $response->assertStatus(401);
        $this->assertDatabaseHas('payments', ['xendit_invoice_id' => 'inv_test_789', 'status' => 'pending']);
        $this->assertSame('pending_payment', $booking->fresh()->status);
    }

    public function test_webhook_is_idempotent_for_already_processed_payment(): void
    {
        $booking = $this->pendingPaymentBooking();
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'xendit_invoice_id' => 'inv_test_idem',
            'amount' => $booking->total_price,
            'status' => 'success',
            'paid_at' => now()->subHour(),
        ]);
        $booking->update(['status' => 'menunggu_konfirmasi', 'mitra_confirmation_deadline' => now()->addHours(24)]);
        $originalDeadline = $booking->mitra_confirmation_deadline;

        $this->withHeaders(['x-callback-token' => self::CALLBACK_TOKEN])
            ->postJson('/api/webhooks/xendit', ['id' => 'inv_test_idem', 'status' => 'PAID'])
            ->assertOk();

        $booking->refresh();
        $this->assertSame('menunggu_konfirmasi', $booking->status);
        $this->assertEquals($originalDeadline, $booking->mitra_confirmation_deadline);
    }

    public function test_webhook_marks_payment_failed_on_expired_invoice(): void
    {
        $booking = $this->pendingPaymentBooking();
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'xendit_invoice_id' => 'inv_test_expired',
            'amount' => $booking->total_price,
            'status' => 'pending',
        ]);

        $this->withHeaders(['x-callback-token' => self::CALLBACK_TOKEN])
            ->postJson('/api/webhooks/xendit', ['id' => 'inv_test_expired', 'status' => 'EXPIRED'])
            ->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('pending_payment', $booking->fresh()->status);
    }
}
