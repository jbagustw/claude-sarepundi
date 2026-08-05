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

    /**
     * Sanctum's guard (Illuminate\Auth\RequestGuard) caches the user it
     * resolves on first use, and that guard instance is itself cached by
     * AuthManager — both live on the container, which a feature test does
     * NOT rebuild between sequential $this->postJson()/getJson() calls in
     * the same test method (only real separate HTTP requests would get a
     * fresh container). So a test that authenticates with a token, revokes
     * it, then makes another authenticated call to prove it's revoked has
     * to force that cache to drop first — otherwise it's asserting against
     * a stale in-memory guard, not the database. Verified this isn't
     * hiding a real bug by hitting the running dev server with plain curl
     * (real separate processes): revocation there takes effect immediately
     * without this workaround.
     */
    private function forgetAuthGuards(): void
    {
        $this->app->make('auth')->forgetGuards();
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

    // --- Mobile (Sanctum bearer token) auth ---
    //
    // No `fromFrontend()` here on purpose: a request without an Origin
    // matching SANCTUM_STATEFUL_DOMAINS is exactly what a native mobile
    // HTTP client looks like — EnsureFrontendRequestsAreStateful won't
    // start a cookie session for it, so AuthController must fall back to
    // issuing a Sanctum personal access token instead.

    public function test_mobile_client_receives_bearer_token_on_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Mobile User',
            'email' => 'mobile-register@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
            'device_name' => 'iPhone 15 Pro',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'mobile-register@example.com');
        $this->assertIsString($response->json('token'));
        $this->assertStringContainsString('|', $response->json('token'));

        $user = User::where('email', 'mobile-register@example.com')->first();
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'iPhone 15 Pro',
        ]);
    }

    public function test_mobile_client_receives_bearer_token_on_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $user->assignRole('user');

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'Pixel 9',
        ]);

        $response->assertOk();
        $this->assertIsString($response->json('token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Pixel 9',
        ]);
    }

    public function test_login_without_device_name_still_issues_a_token_for_mobile(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $user->assignRole('user');

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk();
        $this->assertIsString($response->json('token'));
    }

    public function test_web_client_does_not_receive_a_bearer_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $user->assignRole('user');

        $response = $this->fromFrontend()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertAuthenticatedAs($user);
    }

    public function test_bearer_token_authenticates_subsequent_requests_without_a_session(): void
    {
        $registerResponse = $this->postJson('/api/register', [
            'name' => 'Mobile User',
            'email' => 'mobile-auth@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
            'device_name' => 'iPhone 15 Pro',
        ]);
        $token = $registerResponse->json('token');

        // No actingAs()/session cookie at all — only the bearer token.
        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/me');

        $response->assertOk();
        $response->assertJsonPath('data.email', 'mobile-auth@example.com');
    }

    public function test_request_without_any_token_or_session_is_unauthenticated(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_logout_revokes_the_bearer_token_used(): void
    {
        $registerResponse = $this->postJson('/api/register', [
            'name' => 'Mobile User',
            'email' => 'mobile-logout@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
            'device_name' => 'iPhone 15 Pro',
        ]);
        $token = $registerResponse->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/logout')->assertOk();
        $this->forgetAuthGuards();

        // The exact same token must no longer work.
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/me')->assertStatus(401);

        $user = User::where('email', 'mobile-logout@example.com')->first();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNotNull($user);
    }

    public function test_logging_out_one_device_token_does_not_revoke_another_devices_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $user->assignRole('user');

        $tokenA = $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'password123', 'device_name' => 'Device A',
        ])->json('token');
        $this->forgetAuthGuards();
        $tokenB = $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'password123', 'device_name' => 'Device B',
        ])->json('token');
        $this->forgetAuthGuards();

        $this->withHeader('Authorization', "Bearer {$tokenA}")->postJson('/api/logout')->assertOk();
        $this->forgetAuthGuards();

        $this->withHeader('Authorization', "Bearer {$tokenA}")->getJson('/api/me')->assertStatus(401);
        $this->forgetAuthGuards();
        $this->withHeader('Authorization', "Bearer {$tokenB}")->getJson('/api/me')->assertOk();
    }
}
