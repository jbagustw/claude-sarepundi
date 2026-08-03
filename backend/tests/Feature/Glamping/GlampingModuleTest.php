<?php

namespace Tests\Feature\Glamping;

use App\Models\Facility;
use App\Models\MitraProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GlampingModuleTest extends TestCase
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

    private function approvedMitra(): User
    {
        $user = User::factory()->create();
        $user->assignRole('mitra');
        MitraProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Glamping Approved Co',
            'status' => 'approved',
        ]);

        return $user;
    }

    private function pendingMitra(): User
    {
        $user = User::factory()->create();
        $user->assignRole('mitra');
        MitraProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Glamping Pending Co',
            'status' => 'pending',
        ]);

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function glampingPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Glamping Bukit Sunrise',
            'description' => 'Glamping dengan tenda mewah dan pemandangan bukit.',
            'city' => 'Malang',
            'province' => 'Jawa Timur',
            'capacity_guest' => 4,
            'bedroom_count' => 2,
            'bathroom_count' => 1,
            'base_price' => 450000,
        ], $overrides);
    }

    public function test_approved_mitra_can_create_glamping_as_draft(): void
    {
        $mitra = $this->approvedMitra();

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson('/api/mitra/glampings', $this->glampingPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.name', 'Glamping Bukit Sunrise');
        $this->assertDatabaseHas('glampings', ['name' => 'Glamping Bukit Sunrise', 'status' => 'draft']);
    }

    public function test_mitra_cannot_view_or_edit_another_mitras_glamping(): void
    {
        $owner = $this->approvedMitra();
        $glamping = $owner->mitraProfile->glampings()->create($this->glampingPayload(['slug' => 'glamping-bukit-sunrise']));

        $otherMitra = $this->approvedMitra();

        $this->fromFrontend()->actingAs($otherMitra)
            ->getJson("/api/mitra/glampings/{$glamping->id}")
            ->assertForbidden();

        $this->fromFrontend()->actingAs($otherMitra)
            ->putJson("/api/mitra/glampings/{$glamping->id}", ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('glampings', ['id' => $glamping->id, 'name' => 'Glamping Bukit Sunrise']);
    }

    public function test_regular_user_cannot_access_mitra_glamping_endpoints(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->fromFrontend()->actingAs($user)
            ->postJson('/api/mitra/glampings', $this->glampingPayload())
            ->assertForbidden();
    }

    public function test_approved_mitra_can_submit_draft_glamping_for_review(): void
    {
        $mitra = $this->approvedMitra();
        $glamping = $mitra->mitraProfile->glampings()->create($this->glampingPayload(['slug' => 'glamping-bukit-sunrise']));

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/glampings/{$glamping->id}/submit");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending_review');
    }

    public function test_pending_mitra_cannot_submit_glamping_for_review(): void
    {
        $mitra = $this->pendingMitra();
        $glamping = $mitra->mitraProfile->glampings()->create($this->glampingPayload(['slug' => 'glamping-bukit-sunrise']));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/glampings/{$glamping->id}/submit")
            ->assertForbidden();

        $this->assertDatabaseHas('glampings', ['id' => $glamping->id, 'status' => 'draft']);
    }

    public function test_admin_can_approve_pending_glamping(): void
    {
        $mitra = $this->approvedMitra();
        $glamping = $mitra->mitraProfile->glampings()->create($this->glampingPayload([
            'slug' => 'glamping-bukit-sunrise',
            'status' => 'pending_review',
        ]));

        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/glampings/{$glamping->id}/approve");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'published');
        $this->assertDatabaseHas('glampings', ['id' => $glamping->id, 'status' => 'published', 'reviewed_by' => $admin->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $mitra->id, 'type' => 'glamping_approved']);
    }

    public function test_admin_can_reject_pending_glamping_with_reason(): void
    {
        $mitra = $this->approvedMitra();
        $glamping = $mitra->mitraProfile->glampings()->create($this->glampingPayload([
            'slug' => 'glamping-bukit-sunrise',
            'status' => 'pending_review',
        ]));

        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/glampings/{$glamping->id}/reject", ['reason' => 'Foto tidak jelas.']);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'rejected');
        $this->assertDatabaseHas('glampings', [
            'id' => $glamping->id,
            'status' => 'rejected',
            'rejection_reason' => 'Foto tidak jelas.',
        ]);
    }

    public function test_mitra_cannot_approve_own_glamping(): void
    {
        $mitra = $this->approvedMitra();
        $glamping = $mitra->mitraProfile->glampings()->create($this->glampingPayload([
            'slug' => 'glamping-bukit-sunrise',
            'status' => 'pending_review',
        ]));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/admin/glampings/{$glamping->id}/approve")
            ->assertForbidden();
    }

    public function test_public_index_only_shows_published_glampings_from_approved_mitra(): void
    {
        $approvedMitra = $this->approvedMitra();
        $approvedMitra->mitraProfile->glampings()->create($this->glampingPayload([
            'name' => 'Glamping Published',
            'slug' => 'glamping-published',
            'status' => 'published',
        ]));

        $approvedMitra->mitraProfile->glampings()->create($this->glampingPayload([
            'name' => 'Glamping Draft',
            'slug' => 'glamping-draft',
            'status' => 'draft',
        ]));

        $pendingMitra = $this->pendingMitra();
        $pendingMitra->mitraProfile->glampings()->create($this->glampingPayload([
            'name' => 'Glamping From Unapproved Mitra',
            'slug' => 'glamping-unapproved-mitra',
            'status' => 'published',
        ]));

        $response = $this->fromFrontend()->getJson('/api/glampings');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Glamping Published'));
        $this->assertFalse($names->contains('Glamping Draft'));
        $this->assertFalse($names->contains('Glamping From Unapproved Mitra'));
    }

    public function test_public_show_returns_404_for_non_published_glamping(): void
    {
        $mitra = $this->approvedMitra();
        $mitra->mitraProfile->glampings()->create($this->glampingPayload([
            'slug' => 'glamping-draft-hidden',
            'status' => 'draft',
        ]));

        $this->fromFrontend()->getJson('/api/glampings/glamping-draft-hidden')->assertNotFound();
    }

    public function test_public_show_returns_published_glamping_with_facilities(): void
    {
        $mitra = $this->approvedMitra();
        $glamping = $mitra->mitraProfile->glampings()->create($this->glampingPayload([
            'slug' => 'glamping-published-detail',
            'status' => 'published',
        ]));

        $wifi = Facility::create(['name' => 'WiFi', 'category' => 'general']);
        $glamping->facilities()->attach($wifi);

        $response = $this->fromFrontend()->getJson('/api/glampings/glamping-published-detail');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'glamping-published-detail');
        $response->assertJsonPath('data.facilities.0.name', 'WiFi');
    }

    public function test_public_index_search_matches_city_as_well_as_name(): void
    {
        $mitra = $this->approvedMitra();
        $mitra->mitraProfile->glampings()->create($this->glampingPayload([
            'name' => 'Glamping Alpha',
            'slug' => 'glamping-alpha',
            'city' => 'Malang',
            'status' => 'published',
        ]));
        $mitra->mitraProfile->glampings()->create($this->glampingPayload([
            'name' => 'Glamping Beta',
            'slug' => 'glamping-beta',
            'city' => 'Batu',
            'status' => 'published',
        ]));

        $response = $this->fromFrontend()->getJson('/api/glampings?q=malang');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Glamping Alpha'));
        $this->assertFalse($names->contains('Glamping Beta'));
    }
}
