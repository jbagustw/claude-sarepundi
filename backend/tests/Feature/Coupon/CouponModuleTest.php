<?php

namespace Tests\Feature\Coupon;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CouponModuleTest extends TestCase
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

    private function coupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'DISKON'.uniqid(),
            'title' => 'Diskon 10% Booking Pertama',
            'description' => 'Khusus transaksi pertama di aplikasi',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ], $overrides));
    }

    // --- Admin CRUD ---

    public function test_admin_can_create_a_coupon(): void
    {
        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)->postJson('/api/admin/coupons', [
            'code' => 'HEMAT10',
            'title' => 'Diskon 10% Villa Pertama',
            'description' => 'Untuk pengguna baru',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'valid_until' => '2026-12-31',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'HEMAT10');
        $response->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('coupons', ['code' => 'HEMAT10']);
    }

    public function test_coupon_code_must_be_unique(): void
    {
        $admin = $this->admin();
        $this->coupon(['code' => 'SAMA123']);

        $this->fromFrontend()->actingAs($admin)->postJson('/api/admin/coupons', [
            'code' => 'SAMA123',
            'title' => 'Duplikat',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
        ])->assertStatus(422);
    }

    public function test_non_admin_cannot_create_coupon(): void
    {
        $this->fromFrontend()->actingAs($this->regularUser())
            ->postJson('/api/admin/coupons', ['code' => 'X', 'title' => 'Y', 'discount_type' => 'fixed', 'discount_value' => 1000])
            ->assertForbidden();
    }

    public function test_admin_can_update_a_coupon(): void
    {
        $admin = $this->admin();
        $coupon = $this->coupon();

        $response = $this->fromFrontend()->actingAs($admin)
            ->patchJson("/api/admin/coupons/{$coupon->id}", ['title' => 'Judul Baru', 'is_active' => false]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Judul Baru');
        $response->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_delete_a_coupon(): void
    {
        $admin = $this->admin();
        $coupon = $this->coupon();

        $this->fromFrontend()->actingAs($admin)
            ->deleteJson("/api/admin/coupons/{$coupon->id}")
            ->assertOk();

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_admin_listing_includes_inactive_coupons(): void
    {
        $admin = $this->admin();
        $this->coupon(['is_active' => false]);

        $response = $this->fromFrontend()->actingAs($admin)->getJson('/api/admin/coupons');

        $response->assertJsonCount(1, 'data');
    }

    // --- Public visibility ---

    public function test_public_listing_only_shows_active_coupons(): void
    {
        $this->coupon(['title' => 'Aktif', 'is_active' => true]);
        $this->coupon(['title' => 'Nonaktif', 'is_active' => false]);

        $response = $this->fromFrontend()->getJson('/api/coupons');

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Aktif'));
        $this->assertFalse($titles->contains('Nonaktif'));
    }

    public function test_public_listing_hides_expired_coupons(): void
    {
        $this->coupon(['title' => 'Belum Expired', 'valid_until' => now()->addDay()->toDateString()]);
        $this->coupon(['title' => 'Sudah Expired', 'valid_until' => now()->subDay()->toDateString()]);
        $this->coupon(['title' => 'Tanpa Batas Waktu', 'valid_until' => null]);

        $response = $this->fromFrontend()->getJson('/api/coupons');

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Belum Expired'));
        $this->assertTrue($titles->contains('Tanpa Batas Waktu'));
        $this->assertFalse($titles->contains('Sudah Expired'));
    }
}
