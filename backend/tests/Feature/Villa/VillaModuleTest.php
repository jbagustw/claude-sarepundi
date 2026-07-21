<?php

namespace Tests\Feature\Villa;

use App\Models\Facility;
use App\Models\MitraProfile;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VillaModuleTest extends TestCase
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
            'business_name' => 'Villa Approved Co',
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
            'business_name' => 'Villa Pending Co',
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

    private function villaPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Villa Damai Yogyakarta',
            'description' => 'Villa nyaman dengan pemandangan sawah.',
            'city' => 'Yogyakarta',
            'province' => 'DI Yogyakarta',
            'capacity_guest' => 8,
            'bedroom_count' => 3,
            'bathroom_count' => 2,
            'base_price' => 1500000,
        ], $overrides);
    }

    public function test_approved_mitra_can_create_villa_as_draft(): void
    {
        $mitra = $this->approvedMitra();

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson('/api/mitra/villas', $this->villaPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.name', 'Villa Damai Yogyakarta');
        $this->assertDatabaseHas('villas', ['name' => 'Villa Damai Yogyakarta', 'status' => 'draft']);
    }

    public function test_pending_mitra_can_still_create_a_draft_villa(): void
    {
        $mitra = $this->pendingMitra();

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson('/api/mitra/villas', $this->villaPayload());

        $response->assertCreated();
    }

    public function test_mitra_cannot_view_or_edit_another_mitras_villa(): void
    {
        $owner = $this->approvedMitra();
        $villa = $owner->mitraProfile->villas()->create($this->villaPayload(['slug' => 'villa-damai-yogyakarta']));

        $otherMitra = $this->approvedMitra();

        $this->fromFrontend()->actingAs($otherMitra)
            ->getJson("/api/mitra/villas/{$villa->id}")
            ->assertForbidden();

        $this->fromFrontend()->actingAs($otherMitra)
            ->putJson("/api/mitra/villas/{$villa->id}", ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('villas', ['id' => $villa->id, 'name' => 'Villa Damai Yogyakarta']);
    }

    public function test_regular_user_cannot_access_mitra_villa_endpoints(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->fromFrontend()->actingAs($user)
            ->postJson('/api/mitra/villas', $this->villaPayload())
            ->assertForbidden();
    }

    public function test_approved_mitra_can_submit_draft_villa_for_review(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $mitra->mitraProfile->villas()->create($this->villaPayload(['slug' => 'villa-damai-yogyakarta']));

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/villas/{$villa->id}/submit");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending_review');
    }

    public function test_pending_mitra_cannot_submit_villa_for_review(): void
    {
        $mitra = $this->pendingMitra();
        $villa = $mitra->mitraProfile->villas()->create($this->villaPayload(['slug' => 'villa-damai-yogyakarta']));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/villas/{$villa->id}/submit")
            ->assertForbidden();

        $this->assertDatabaseHas('villas', ['id' => $villa->id, 'status' => 'draft']);
    }

    public function test_admin_can_approve_pending_villa(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $mitra->mitraProfile->villas()->create($this->villaPayload([
            'slug' => 'villa-damai-yogyakarta',
            'status' => 'pending_review',
        ]));

        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/villas/{$villa->id}/approve");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'published');
        $this->assertDatabaseHas('villas', ['id' => $villa->id, 'status' => 'published', 'reviewed_by' => $admin->id]);
    }

    public function test_admin_can_reject_pending_villa_with_reason(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $mitra->mitraProfile->villas()->create($this->villaPayload([
            'slug' => 'villa-damai-yogyakarta',
            'status' => 'pending_review',
        ]));

        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/villas/{$villa->id}/reject", ['reason' => 'Foto tidak jelas.']);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'rejected');
        $this->assertDatabaseHas('villas', [
            'id' => $villa->id,
            'status' => 'rejected',
            'rejection_reason' => 'Foto tidak jelas.',
        ]);
    }

    public function test_mitra_cannot_approve_own_villa(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $mitra->mitraProfile->villas()->create($this->villaPayload([
            'slug' => 'villa-damai-yogyakarta',
            'status' => 'pending_review',
        ]));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/admin/villas/{$villa->id}/approve")
            ->assertForbidden();
    }

    public function test_public_index_only_shows_published_villas_from_approved_mitra(): void
    {
        $approvedMitra = $this->approvedMitra();
        $published = $approvedMitra->mitraProfile->villas()->create($this->villaPayload([
            'name' => 'Villa Published',
            'slug' => 'villa-published',
            'status' => 'published',
        ]));

        $approvedMitra->mitraProfile->villas()->create($this->villaPayload([
            'name' => 'Villa Draft',
            'slug' => 'villa-draft',
            'status' => 'draft',
        ]));

        $pendingMitra = $this->pendingMitra();
        $pendingMitra->mitraProfile->villas()->create($this->villaPayload([
            'name' => 'Villa From Unapproved Mitra',
            'slug' => 'villa-unapproved-mitra',
            'status' => 'published',
        ]));

        $response = $this->fromFrontend()->getJson('/api/villas');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Villa Published'));
        $this->assertFalse($names->contains('Villa Draft'));
        $this->assertFalse($names->contains('Villa From Unapproved Mitra'));
    }

    public function test_public_show_returns_404_for_non_published_villa(): void
    {
        $mitra = $this->approvedMitra();
        $mitra->mitraProfile->villas()->create($this->villaPayload([
            'slug' => 'villa-draft-hidden',
            'status' => 'draft',
        ]));

        $this->fromFrontend()->getJson('/api/villas/villa-draft-hidden')->assertNotFound();
    }

    public function test_public_show_returns_published_villa_with_facilities(): void
    {
        $mitra = $this->approvedMitra();
        $villa = $mitra->mitraProfile->villas()->create($this->villaPayload([
            'slug' => 'villa-published-detail',
            'status' => 'published',
        ]));

        $wifi = Facility::create(['name' => 'WiFi', 'category' => 'general']);
        $villa->facilities()->attach($wifi);

        $response = $this->fromFrontend()->getJson('/api/villas/villa-published-detail');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'villa-published-detail');
        $response->assertJsonPath('data.facilities.0.name', 'WiFi');
    }

    public function test_admin_can_approve_pending_mitra(): void
    {
        $mitra = $this->pendingMitra();
        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/mitras/{$mitra->mitraProfile->id}/approve");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('mitra_profiles', [
            'id' => $mitra->mitraProfile->id,
            'status' => 'approved',
            'approved_by' => $admin->id,
        ]);
    }

    public function test_mitra_cannot_approve_own_mitra_profile(): void
    {
        $mitra = $this->pendingMitra();

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/admin/mitras/{$mitra->mitraProfile->id}/approve")
            ->assertForbidden();
    }

    public function test_facilities_endpoint_is_publicly_accessible(): void
    {
        Facility::create(['name' => 'WiFi', 'category' => 'general']);

        $this->fromFrontend()->getJson('/api/facilities')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
