<?php

namespace Tests\Feature\Payout;

use App\Models\Booking;
use App\Models\MitraProfile;
use App\Models\Payout;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayoutModuleTest extends TestCase
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

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function approvedMitra(bool $withBankDetails = true): User
    {
        $user = User::factory()->create();
        $user->assignRole('mitra');
        MitraProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Villa Co',
            'status' => 'approved',
            'bank_name' => $withBankDetails ? 'BCA' : null,
            'bank_account' => $withBankDetails ? '1234567890' : null,
        ]);

        return $user;
    }

    private function villa(User $mitra): Villa
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

    private function completedBooking(Villa $villa, int $totalPrice = 2000000): Booking
    {
        return Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Villa::class,
            'bookable_id' => $villa->id,
            'check_in_date' => '2026-06-01',
            'check_out_date' => '2026-06-03',
            'guest_count' => 2,
            'subtotal' => $totalPrice,
            'total_price' => $totalPrice,
            'commission_amount' => (int) ($totalPrice * 0.1),
            'mitra_payout_amount' => (int) ($totalPrice * 0.9),
            'status' => 'selesai',
        ]);
    }

    private function fakeDisbursementSuccess(): void
    {
        Http::fake([
            'api.xendit.co/disbursements' => Http::response(['id' => 'disb_test_1', 'status' => 'COMPLETED'], 200),
        ]);
    }

    // --- Run ---

    public function test_admin_can_manually_run_payouts_and_it_batches_completed_bookings(): void
    {
        $this->fakeDisbursementSuccess();
        $mitra = $this->approvedMitra();
        $villa = $this->villa($mitra);
        $b1 = $this->completedBooking($villa, 2000000); // payout 1,800,000
        $b2 = $this->completedBooking($villa, 1000000); // payout 900,000

        $response = $this->fromFrontend()->actingAs($this->admin())->postJson('/api/admin/payouts/run');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.amount', 2700000);
        $response->assertJsonPath('data.0.status', 'completed');
        $response->assertJsonPath('data.0.booking_count', 2);

        $payout = Payout::first();
        $this->assertSame($payout->id, $b1->fresh()->payout_id);
        $this->assertSame($payout->id, $b2->fresh()->payout_id);
    }

    public function test_run_skips_mitras_with_no_completed_bookings(): void
    {
        $this->fakeDisbursementSuccess();
        $mitra = $this->approvedMitra();
        $villa = $this->villa($mitra);
        // pending booking, not selesai
        Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Villa::class,
            'bookable_id' => $villa->id,
            'check_in_date' => '2026-08-01',
            'check_out_date' => '2026-08-03',
            'guest_count' => 2,
            'subtotal' => 2000000,
            'total_price' => 2000000,
            'commission_amount' => 200000,
            'mitra_payout_amount' => 1800000,
            'status' => 'menunggu_konfirmasi',
        ]);

        $response = $this->fromFrontend()->actingAs($this->admin())->postJson('/api/admin/payouts/run');

        $response->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_bookings_already_in_a_payout_are_not_paid_out_again(): void
    {
        $this->fakeDisbursementSuccess();
        $mitra = $this->approvedMitra();
        $villa = $this->villa($mitra);
        $this->completedBooking($villa);

        $this->fromFrontend()->actingAs($this->admin())->postJson('/api/admin/payouts/run')->assertOk();
        $response = $this->fromFrontend()->actingAs($this->admin())->postJson('/api/admin/payouts/run');

        // Second run finds nothing new to pay out.
        $response->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('payouts', 1);
    }

    public function test_run_fails_gracefully_when_mitra_bank_details_are_missing(): void
    {
        $mitra = $this->approvedMitra(withBankDetails: false);
        $villa = $this->villa($mitra);
        $this->completedBooking($villa);

        $response = $this->fromFrontend()->actingAs($this->admin())->postJson('/api/admin/payouts/run');

        $response->assertJsonPath('data.0.status', 'failed');
        $response->assertJsonPath('data.0.failure_reason', 'Data rekening mitra belum lengkap. Mitra perlu melengkapi data bank di profil.');
        Http::assertNothingSent();
    }

    public function test_run_records_failure_when_xendit_api_errors(): void
    {
        Http::fake(['api.xendit.co/disbursements' => Http::response(['message' => 'error'], 500)]);
        $mitra = $this->approvedMitra();
        $villa = $this->villa($mitra);
        $booking = $this->completedBooking($villa);

        $this->fromFrontend()->actingAs($this->admin())->postJson('/api/admin/payouts/run');

        $payout = Payout::first();
        $this->assertSame('failed', $payout->status);
        // Booking is still reserved into this (failed) payout, not left
        // free to be silently re-batched by the next run.
        $this->assertSame($payout->id, $booking->fresh()->payout_id);
    }

    // --- Retry ---

    public function test_admin_can_retry_a_failed_payout(): void
    {
        $mitra = $this->approvedMitra(withBankDetails: false);
        $villa = $this->villa($mitra);
        $this->completedBooking($villa);
        $this->fromFrontend()->actingAs($this->admin())->postJson('/api/admin/payouts/run');
        $payout = Payout::first();
        $this->assertSame('failed', $payout->status);

        // Mitra fixes their bank details, admin retries.
        $mitra->mitraProfile->update(['bank_name' => 'BCA', 'bank_account' => '1234567890']);
        $this->fakeDisbursementSuccess();

        $response = $this->fromFrontend()->actingAs($this->admin())
            ->postJson("/api/admin/payouts/{$payout->id}/retry");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'completed');
    }

    public function test_cannot_retry_a_payout_that_is_not_failed(): void
    {
        $this->fakeDisbursementSuccess();
        $mitra = $this->approvedMitra();
        $villa = $this->villa($mitra);
        $this->completedBooking($villa);
        $this->fromFrontend()->actingAs($this->admin())->postJson('/api/admin/payouts/run');
        $payout = Payout::first();
        $this->assertSame('completed', $payout->status);

        $this->fromFrontend()->actingAs($this->admin())
            ->postJson("/api/admin/payouts/{$payout->id}/retry")
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_access_payout_endpoints(): void
    {
        $mitra = $this->approvedMitra();

        $this->fromFrontend()->actingAs($mitra)->getJson('/api/admin/payouts')->assertForbidden();
        $this->fromFrontend()->actingAs($mitra)->postJson('/api/admin/payouts/run')->assertForbidden();
    }

    // --- Mitra-facing ---

    public function test_mitra_can_view_own_payout_report(): void
    {
        $this->fakeDisbursementSuccess();
        $mitra = $this->approvedMitra();
        $villa = $this->villa($mitra);
        $this->completedBooking($villa);
        $this->fromFrontend()->actingAs($this->admin())->postJson('/api/admin/payouts/run');

        $response = $this->fromFrontend()->actingAs($mitra)->getJson('/api/mitra/payouts');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'completed');
    }

    public function test_mitra_only_sees_own_payouts(): void
    {
        $this->fakeDisbursementSuccess();
        $mitraA = $this->approvedMitra();
        $this->completedBooking($this->villa($mitraA));
        $mitraB = $this->approvedMitra();
        $this->completedBooking($this->villa($mitraB));

        $this->fromFrontend()->actingAs($this->admin())->postJson('/api/admin/payouts/run');

        $response = $this->fromFrontend()->actingAs($mitraA)->getJson('/api/mitra/payouts');
        $this->assertCount(1, $response->json('data'));
    }

    public function test_mitra_can_update_bank_details(): void
    {
        $mitra = $this->approvedMitra(withBankDetails: false);

        $response = $this->fromFrontend()->actingAs($mitra)->patchJson('/api/mitra/profile', [
            'bank_name' => 'Mandiri',
            'bank_account' => '9988776655',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.bank_name', 'Mandiri');
        $response->assertJsonPath('data.bank_account', '9988776655');
        $this->assertSame('Mandiri', $mitra->mitraProfile->fresh()->bank_name);
    }
}
