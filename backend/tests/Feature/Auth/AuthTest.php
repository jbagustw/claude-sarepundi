<?php

namespace Tests\Feature\Auth;

use App\Models\MitraProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'mitra', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    /**
     * Requests must look like they come from the SPA (localhost:3000) so
     * Sanctum's EnsureFrontendRequestsAreStateful applies the session/CSRF
     * middleware stack — see config/sanctum.php `stateful`.
     */
    private function fromFrontend(): static
    {
        return $this->withHeaders(['Origin' => 'http://localhost:3000']);
    }

    public function test_user_can_register_with_user_role(): void
    {
        $response = $this->fromFrontend()->postJson('/api/register', [
            'name' => 'Citra User',
            'email' => 'citra@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.role', 'user');
        $response->assertJsonPath('data.mitra_profile', null);

        $this->assertDatabaseHas('users', ['email' => 'citra@example.com']);
        $this->assertTrue(User::where('email', 'citra@example.com')->first()->hasRole('user'));
    }

    public function test_user_can_register_with_mitra_role_and_gets_pending_mitra_profile(): void
    {
        $response = $this->fromFrontend()->postJson('/api/register', [
            'name' => 'Budi Mitra',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'mitra',
            'business_name' => 'Villa Budi Asri',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.role', 'mitra');
        $response->assertJsonPath('data.mitra_profile.status', 'pending');

        $user = User::where('email', 'budi@example.com')->first();
        $this->assertDatabaseHas('mitra_profiles', [
            'user_id' => $user->id,
            'business_name' => 'Villa Budi Asri',
            'status' => 'pending',
        ]);
    }

    public function test_user_can_register_when_optional_mitra_fields_are_sent_as_empty_strings(): void
    {
        // The frontend form always submits business_name/business_address
        // (even blank) regardless of the chosen role, and Laravel's
        // ConvertEmptyStringsToNull middleware turns "" into null before
        // validation runs — this must not break a plain "user" registration.
        $response = $this->fromFrontend()->postJson('/api/register', [
            'name' => 'Citra User',
            'email' => 'citra@example.com',
            'phone' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
            'business_name' => '',
            'business_address' => '',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.role', 'user');
    }

    public function test_register_requires_business_name_when_role_is_mitra(): void
    {
        $response = $this->fromFrontend()->postJson('/api/register', [
            'name' => 'Budi Mitra',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'mitra',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('business_name');
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $user->assignRole('user');

        $response = $this->fromFrontend()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.email', $user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $user->assignRole('user');

        $response = $this->fromFrontend()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'status' => 'suspended',
        ]);
        $user->assignRole('user');

        $response = $this->fromFrontend()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_authenticated_user_can_view_profile_via_me_endpoint(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->fromFrontend()->actingAs($user)->getJson('/api/me');

        $response->assertOk();
        $response->assertJsonPath('data.email', $user->email);
    }

    public function test_guest_cannot_access_me_endpoint(): void
    {
        $response = $this->fromFrontend()->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->fromFrontend()->actingAs($user)->postJson('/api/logout');

        $response->assertOk();
    }

    public function test_role_middleware_allows_matching_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->fromFrontend()->actingAs($user)->getJson('/api/user/ping');

        $response->assertOk();
    }

    public function test_role_middleware_blocks_mismatched_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->fromFrontend()->actingAs($user)->getJson('/api/admin/ping');

        $response->assertStatus(403);
    }

    public function test_mitra_can_access_mitra_route_but_not_admin_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole('mitra');
        MitraProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Villa Test',
            'status' => 'pending',
        ]);

        $this->fromFrontend()->actingAs($user)->getJson('/api/mitra/ping')->assertOk();
        $this->fromFrontend()->actingAs($user)->getJson('/api/admin/ping')->assertStatus(403);
    }
}
