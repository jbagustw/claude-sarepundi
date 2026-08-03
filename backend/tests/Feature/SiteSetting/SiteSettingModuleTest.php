<?php

namespace Tests\Feature\SiteSetting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SiteSettingModuleTest extends TestCase
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

    public function test_public_endpoint_returns_null_links_by_default(): void
    {
        $response = $this->getJson('/api/site-settings');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'instagram_url' => null,
                'facebook_url' => null,
                'tiktok_url' => null,
                'hero_image_url' => null,
                'logo_url' => null,
                'favicon_url' => null,
            ],
        ]);
    }

    public function test_admin_can_update_social_media_links(): void
    {
        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)->patchJson('/api/admin/site-settings', [
            'instagram_url' => 'https://instagram.com/sarepundi',
            'facebook_url' => 'https://facebook.com/sarepundi',
            'tiktok_url' => 'https://tiktok.com/@sarepundi',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.instagram_url', 'https://instagram.com/sarepundi');
        $response->assertJsonPath('data.facebook_url', 'https://facebook.com/sarepundi');
        $response->assertJsonPath('data.tiktok_url', 'https://tiktok.com/@sarepundi');

        $this->getJson('/api/site-settings')
            ->assertJsonPath('data.instagram_url', 'https://instagram.com/sarepundi');
    }

    public function test_admin_can_clear_a_link(): void
    {
        $admin = $this->admin();

        $this->fromFrontend()->actingAs($admin)->patchJson('/api/admin/site-settings', [
            'instagram_url' => 'https://instagram.com/sarepundi',
        ]);

        $response = $this->fromFrontend()->actingAs($admin)->patchJson('/api/admin/site-settings', [
            'instagram_url' => null,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.instagram_url', null);
    }

    public function test_update_rejects_an_invalid_url(): void
    {
        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)->patchJson('/api/admin/site-settings', [
            'instagram_url' => 'not-a-url',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('instagram_url');
    }

    public function test_non_admin_cannot_update_social_media_links(): void
    {
        $this->fromFrontend()->actingAs($this->regularUser())
            ->patchJson('/api/admin/site-settings', ['instagram_url' => 'https://instagram.com/sarepundi'])
            ->assertForbidden();
    }

    public function test_guest_cannot_update_social_media_links(): void
    {
        $this->fromFrontend()
            ->patchJson('/api/admin/site-settings', ['instagram_url' => 'https://instagram.com/sarepundi'])
            ->assertUnauthorized();
    }

    // --- Hero banner image ---

    public function test_admin_can_upload_hero_image(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/hero-image', ['image' => UploadedFile::fake()->image('hero.jpg')]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.hero_image_url'));

        $this->getJson('/api/site-settings')->assertJsonPath('data.hero_image_url', fn ($url) => ! is_null($url));
    }

    public function test_uploading_a_new_hero_image_replaces_and_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $first = $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/hero-image', ['image' => UploadedFile::fake()->image('hero-1.jpg')]);
        $firstPath = \App\Models\SiteSetting::current()->hero_image_path;
        Storage::disk('public')->assertExists($firstPath);

        $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/hero-image', ['image' => UploadedFile::fake()->image('hero-2.jpg')]);

        Storage::disk('public')->assertMissing($firstPath);
        $this->assertNotEquals($firstPath, \App\Models\SiteSetting::current()->hero_image_path);
    }

    public function test_admin_can_remove_hero_image(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/hero-image', ['image' => UploadedFile::fake()->image('hero.jpg')]);

        $response = $this->fromFrontend()->actingAs($admin)->deleteJson('/api/admin/site-settings/hero-image');

        $response->assertOk();
        $response->assertJsonPath('data.hero_image_url', null);
    }

    public function test_hero_image_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/hero-image', ['image' => UploadedFile::fake()->create('hero.pdf', 100)]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('image');
    }

    public function test_non_admin_cannot_upload_hero_image(): void
    {
        Storage::fake('public');

        $this->fromFrontend()->actingAs($this->regularUser())
            ->postJson('/api/admin/site-settings/hero-image', ['image' => UploadedFile::fake()->image('hero.jpg')])
            ->assertForbidden();
    }

    // --- Logo ---

    public function test_admin_can_upload_logo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/logo', ['image' => UploadedFile::fake()->image('logo.png')]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.logo_url'));

        $this->getJson('/api/site-settings')->assertJsonPath('data.logo_url', fn ($url) => ! is_null($url));
    }

    public function test_uploading_a_new_logo_replaces_and_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/logo', ['image' => UploadedFile::fake()->image('logo-1.png')]);
        $firstPath = \App\Models\SiteSetting::current()->logo_path;
        Storage::disk('public')->assertExists($firstPath);

        $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/logo', ['image' => UploadedFile::fake()->image('logo-2.png')]);

        Storage::disk('public')->assertMissing($firstPath);
        $this->assertNotEquals($firstPath, \App\Models\SiteSetting::current()->logo_path);
    }

    public function test_admin_can_remove_logo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/logo', ['image' => UploadedFile::fake()->image('logo.png')]);

        $response = $this->fromFrontend()->actingAs($admin)->deleteJson('/api/admin/site-settings/logo');

        $response->assertOk();
        $response->assertJsonPath('data.logo_url', null);
    }

    public function test_logo_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/logo', ['image' => UploadedFile::fake()->create('logo.pdf', 100)]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('image');
    }

    public function test_non_admin_cannot_upload_logo(): void
    {
        Storage::fake('public');

        $this->fromFrontend()->actingAs($this->regularUser())
            ->postJson('/api/admin/site-settings/logo', ['image' => UploadedFile::fake()->image('logo.png')])
            ->assertForbidden();
    }

    // --- Favicon ---

    public function test_admin_can_upload_favicon(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/favicon', ['image' => UploadedFile::fake()->image('favicon.png')]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.favicon_url'));

        $this->getJson('/api/site-settings')->assertJsonPath('data.favicon_url', fn ($url) => ! is_null($url));
    }

    public function test_uploading_a_new_favicon_replaces_and_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/favicon', ['image' => UploadedFile::fake()->image('favicon-1.png')]);
        $firstPath = \App\Models\SiteSetting::current()->favicon_path;
        Storage::disk('public')->assertExists($firstPath);

        $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/favicon', ['image' => UploadedFile::fake()->image('favicon-2.png')]);

        Storage::disk('public')->assertMissing($firstPath);
        $this->assertNotEquals($firstPath, \App\Models\SiteSetting::current()->favicon_path);
    }

    public function test_admin_can_remove_favicon(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/favicon', ['image' => UploadedFile::fake()->image('favicon.png')]);

        $response = $this->fromFrontend()->actingAs($admin)->deleteJson('/api/admin/site-settings/favicon');

        $response->assertOk();
        $response->assertJsonPath('data.favicon_url', null);
    }

    public function test_favicon_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson('/api/admin/site-settings/favicon', ['image' => UploadedFile::fake()->create('favicon.pdf', 100)]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('image');
    }

    public function test_non_admin_cannot_upload_favicon(): void
    {
        Storage::fake('public');

        $this->fromFrontend()->actingAs($this->regularUser())
            ->postJson('/api/admin/site-settings/favicon', ['image' => UploadedFile::fake()->image('favicon.png')])
            ->assertForbidden();
    }
}
