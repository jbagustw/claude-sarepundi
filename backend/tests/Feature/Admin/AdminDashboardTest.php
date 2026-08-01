<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\MitraProfile;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
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

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function regularUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    private function approvedMitra(?int $commissionRate = null): User
    {
        $user = User::factory()->create();
        $user->assignRole('mitra');
        MitraProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Villa Co',
            'status' => 'approved',
            'commission_rate' => $commissionRate,
        ]);

        return $user;
    }

    private function publishedVilla(User $mitra, array $overrides = []): Villa
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

    // --- Stats ---

    public function test_admin_can_view_stats(): void
    {
        $admin = $this->admin();
        $mitra = $this->approvedMitra();
        $villa = $this->publishedVilla($mitra);

        Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => $this->regularUser()->id,
            'bookable_type' => Villa::class,
            'bookable_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12',
            'guest_count' => 2,
            'subtotal' => 2000000,
            'total_price' => 2000000,
            'commission_amount' => 200000,
            'mitra_payout_amount' => 1800000,
            'status' => 'selesai',
        ]);

        $response = $this->fromFrontend()->actingAs($admin)->getJson('/api/admin/stats');

        $response->assertOk();
        $response->assertJsonPath('data.bookings.total', 1);
        $response->assertJsonPath('data.bookings.completed', 1);
        $response->assertJsonPath('data.commission_earned', 200000);
        $response->assertJsonPath('data.mitras.approved', 1);
    }

    public function test_non_admin_cannot_view_stats(): void
    {
        $this->fromFrontend()->actingAs($this->regularUser())
            ->getJson('/api/admin/stats')
            ->assertForbidden();
    }

    // --- Transaction monitoring ---

    public function test_admin_can_list_all_bookings_with_status_filter(): void
    {
        $admin = $this->admin();
        $mitra = $this->approvedMitra();
        $villa = $this->publishedVilla($mitra);
        $user = $this->regularUser();

        Booking::create([
            'booking_code' => 'BKCONFIRMED',
            'user_id' => $user->id,
            'bookable_type' => Villa::class,
            'bookable_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12',
            'guest_count' => 2,
            'subtotal' => 2000000,
            'total_price' => 2000000,
            'commission_amount' => 200000,
            'mitra_payout_amount' => 1800000,
            'status' => 'dikonfirmasi',
        ]);
        Booking::create([
            'booking_code' => 'BKPENDING',
            'user_id' => $user->id,
            'bookable_type' => Villa::class,
            'bookable_id' => $villa->id,
            'check_in_date' => '2026-09-10',
            'check_out_date' => '2026-09-12',
            'guest_count' => 2,
            'subtotal' => 2000000,
            'total_price' => 2000000,
            'commission_amount' => 200000,
            'mitra_payout_amount' => 1800000,
            'status' => 'pending_payment',
        ]);

        $response = $this->fromFrontend()->actingAs($admin)
            ->getJson('/api/admin/bookings?status=dikonfirmasi');

        $codes = collect($response->json('data'))->pluck('booking_code');
        $this->assertTrue($codes->contains('BKCONFIRMED'));
        $this->assertFalse($codes->contains('BKPENDING'));
    }

    public function test_admin_can_search_bookings_by_code(): void
    {
        $admin = $this->admin();
        $mitra = $this->approvedMitra();
        $villa = $this->publishedVilla($mitra);

        Booking::create([
            'booking_code' => 'BKSEARCHME',
            'user_id' => $this->regularUser()->id,
            'bookable_type' => Villa::class,
            'bookable_id' => $villa->id,
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12',
            'guest_count' => 2,
            'subtotal' => 2000000,
            'total_price' => 2000000,
            'commission_amount' => 200000,
            'mitra_payout_amount' => 1800000,
            'status' => 'pending_payment',
        ]);

        $response = $this->fromFrontend()->actingAs($admin)
            ->getJson('/api/admin/bookings?search=SEARCHME');

        $codes = collect($response->json('data'))->pluck('booking_code');
        $this->assertTrue($codes->contains('BKSEARCHME'));
    }

    // --- User management ---

    public function test_admin_can_list_users_filtered_by_role(): void
    {
        $admin = $this->admin();
        $this->regularUser();
        $this->approvedMitra();

        $response = $this->fromFrontend()->actingAs($admin)->getJson('/api/admin/users?role=mitra');

        $roles = collect($response->json('data'))->pluck('role');
        $this->assertTrue($roles->every(fn ($r) => $r === 'mitra'));
    }

    public function test_admin_can_suspend_and_reactivate_user(): void
    {
        $admin = $this->admin();
        $user = $this->regularUser();

        $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/users/{$user->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');
        $this->assertSame('suspended', $user->fresh()->status);

        $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/users/{$user->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_admin_cannot_suspend_another_admin(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();

        $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/users/{$otherAdmin->id}/suspend")
            ->assertStatus(422);

        $this->assertSame('active', $otherAdmin->fresh()->status);
    }

    public function test_suspended_user_is_immediately_blocked_from_authenticated_routes(): void
    {
        $user = $this->regularUser();

        // Simulate an already-authenticated session (as if they'd logged
        // in before the suspension happened) — the middleware must catch
        // this on the very next request, not just at login time.
        $user->update(['status' => 'suspended']);

        $this->fromFrontend()->actingAs($user)
            ->getJson('/api/bookings')
            ->assertStatus(403);
    }

    public function test_suspended_mitras_villas_are_hidden_from_public_even_if_still_published(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $this->publishedVilla($mitra);
        $mitra->update(['status' => 'suspended']);

        $this->fromFrontend()->getJson('/api/villas')
            ->assertOk()
            ->assertJsonMissing(['slug' => $villa->slug]);

        $this->fromFrontend()->getJson("/api/villas/{$villa->slug}")->assertNotFound();
    }

    // --- Commission override ---

    public function test_admin_can_override_mitra_commission_rate(): void
    {
        $admin = $this->admin();
        $mitra = $this->approvedMitra();

        $response = $this->fromFrontend()->actingAs($admin)
            ->patchJson("/api/admin/mitras/{$mitra->mitraProfile->id}/commission", ['commission_rate' => 15]);

        $response->assertOk();
        $response->assertJsonPath('data.commission_rate', 15);
        $response->assertJsonPath('data.effective_commission_rate', 15);
        $this->assertSame(15, $mitra->mitraProfile->fresh()->commission_rate);
    }

    public function test_commission_override_affects_new_booking_pricing(): void
    {
        $mitra = $this->approvedMitra(commissionRate: 20);
        $villa = $this->publishedVilla($mitra, ['base_price' => 1000000]);

        $response = $this->fromFrontend()->getJson(
            "/api/villas/{$villa->slug}/availability?check_in_date=2026-08-10&check_out_date=2026-08-12"
        );

        $response->assertJsonPath('data.total_price', 2000000);
        $response->assertJsonPath('data.commission_amount', 400000); // 20% of 2,000,000
        $response->assertJsonPath('data.mitra_payout_amount', 1600000);
    }

    public function test_commission_reset_to_null_uses_platform_default(): void
    {
        $admin = $this->admin();
        $mitra = $this->approvedMitra(commissionRate: 20);

        $this->fromFrontend()->actingAs($admin)
            ->patchJson("/api/admin/mitras/{$mitra->mitraProfile->id}/commission", ['commission_rate' => null])
            ->assertOk()
            ->assertJsonPath('data.commission_rate', null)
            ->assertJsonPath('data.effective_commission_rate', 10);
    }
}
