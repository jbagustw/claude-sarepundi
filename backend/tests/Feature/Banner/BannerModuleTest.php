<?php

namespace Tests\Feature\Banner;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BannerModuleTest extends TestCase
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

    private function banner(array $overrides = []): Banner
    {
        return Banner::create(array_merge([
            'title' => 'Promo Akhir Tahun',
            'image_path' => 'banners/example.jpg',
            'is_active' => true,
        ], $overrides));
    }

    // --- Admin CRUD ---

    public function test_admin_can_create_a_banner(): void
    {
        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)->postJson('/api/admin/banners', [
            'title' => 'Promo Gathering Akhir Tahun',
            'link_url' => '/gathering-venues',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Promo Gathering Akhir Tahun');
        $response->assertJsonPath('data.is_active', true);
        $response->assertJsonPath('data.image', null);
    }

    public function test_non_admin_cannot_create_banner(): void
    {
        $this->fromFrontend()->actingAs($this->regularUser())
            ->postJson('/api/admin/banners', ['title' => 'X'])
            ->assertForbidden();
    }

    public function test_admin_can_upload_and_replace_banner_image(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $banner = $this->banner(['image_path' => null]);

        $file = UploadedFile::fake()->image('banner.jpg');

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/banners/{$banner->id}/image", ['image' => $file]);

        $response->assertOk();
        $this->assertNotNull($banner->fresh()->image_path);
        Storage::disk('public')->assertExists($banner->fresh()->image_path);
    }

    public function test_admin_can_update_a_banner(): void
    {
        $admin = $this->admin();
        $banner = $this->banner();

        $response = $this->fromFrontend()->actingAs($admin)
            ->patchJson("/api/admin/banners/{$banner->id}", ['title' => 'Judul Baru', 'is_active' => false]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Judul Baru');
        $response->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_delete_a_banner(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $banner = $this->banner(['image_path' => 'banners/to-delete.jpg']);
        Storage::disk('public')->put('banners/to-delete.jpg', 'fake-content');

        $this->fromFrontend()->actingAs($admin)
            ->deleteJson("/api/admin/banners/{$banner->id}")
            ->assertOk();

        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
        Storage::disk('public')->assertMissing('banners/to-delete.jpg');
    }

    // --- Public visibility ---

    public function test_public_listing_only_shows_active_banners_with_an_image(): void
    {
        $this->banner(['title' => 'Aktif Dengan Gambar', 'is_active' => true, 'image_path' => 'banners/a.jpg']);
        $this->banner(['title' => 'Nonaktif', 'is_active' => false, 'image_path' => 'banners/b.jpg']);
        $this->banner(['title' => 'Belum Ada Gambar', 'is_active' => true, 'image_path' => null]);

        $response = $this->fromFrontend()->getJson('/api/banners');

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Aktif Dengan Gambar'));
        $this->assertFalse($titles->contains('Nonaktif'));
        $this->assertFalse($titles->contains('Belum Ada Gambar'));
    }
}
