<?php

namespace Tests\Feature\SiteSetting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
