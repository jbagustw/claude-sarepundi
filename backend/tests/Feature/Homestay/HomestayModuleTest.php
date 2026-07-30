<?php

namespace Tests\Feature\Homestay;

use App\Models\Facility;
use App\Models\MitraProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HomestayModuleTest extends TestCase
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
            'business_name' => 'Homestay Approved Co',
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
            'business_name' => 'Homestay Pending Co',
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

    private function homestayPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Homestay Kampung Damai',
            'description' => 'Homestay asri di tengah kampung wisata.',
            'city' => 'Yogyakarta',
            'province' => 'DI Yogyakarta',
            'capacity_guest' => 4,
            'bedroom_count' => 2,
            'bathroom_count' => 1,
            'base_price' => 350000,
        ], $overrides);
    }

    public function test_approved_mitra_can_create_homestay_as_draft(): void
    {
        $mitra = $this->approvedMitra();

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson('/api/mitra/homestays', $this->homestayPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.name', 'Homestay Kampung Damai');
        $this->assertDatabaseHas('homestays', ['name' => 'Homestay Kampung Damai', 'status' => 'draft']);
    }

    public function test_mitra_cannot_view_or_edit_another_mitras_homestay(): void
    {
        $owner = $this->approvedMitra();
        $homestay = $owner->mitraProfile->homestays()->create($this->homestayPayload(['slug' => 'homestay-kampung-damai']));

        $otherMitra = $this->approvedMitra();

        $this->fromFrontend()->actingAs($otherMitra)
            ->getJson("/api/mitra/homestays/{$homestay->id}")
            ->assertForbidden();

        $this->fromFrontend()->actingAs($otherMitra)
            ->putJson("/api/mitra/homestays/{$homestay->id}", ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('homestays', ['id' => $homestay->id, 'name' => 'Homestay Kampung Damai']);
    }

    public function test_regular_user_cannot_access_mitra_homestay_endpoints(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->fromFrontend()->actingAs($user)
            ->postJson('/api/mitra/homestays', $this->homestayPayload())
            ->assertForbidden();
    }

    public function test_approved_mitra_can_submit_draft_homestay_for_review(): void
    {
        $mitra = $this->approvedMitra();
        $homestay = $mitra->mitraProfile->homestays()->create($this->homestayPayload(['slug' => 'homestay-kampung-damai']));

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/homestays/{$homestay->id}/submit");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending_review');
    }

    public function test_pending_mitra_cannot_submit_homestay_for_review(): void
    {
        $mitra = $this->pendingMitra();
        $homestay = $mitra->mitraProfile->homestays()->create($this->homestayPayload(['slug' => 'homestay-kampung-damai']));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/homestays/{$homestay->id}/submit")
            ->assertForbidden();

        $this->assertDatabaseHas('homestays', ['id' => $homestay->id, 'status' => 'draft']);
    }

    public function test_admin_can_approve_pending_homestay(): void
    {
        $mitra = $this->approvedMitra();
        $homestay = $mitra->mitraProfile->homestays()->create($this->homestayPayload([
            'slug' => 'homestay-kampung-damai',
            'status' => 'pending_review',
        ]));

        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/homestays/{$homestay->id}/approve");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'published');
        $this->assertDatabaseHas('homestays', ['id' => $homestay->id, 'status' => 'published', 'reviewed_by' => $admin->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $mitra->id, 'type' => 'homestay_approved']);
    }

    public function test_admin_can_reject_pending_homestay_with_reason(): void
    {
        $mitra = $this->approvedMitra();
        $homestay = $mitra->mitraProfile->homestays()->create($this->homestayPayload([
            'slug' => 'homestay-kampung-damai',
            'status' => 'pending_review',
        ]));

        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/homestays/{$homestay->id}/reject", ['reason' => 'Foto tidak jelas.']);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'rejected');
        $this->assertDatabaseHas('homestays', [
            'id' => $homestay->id,
            'status' => 'rejected',
            'rejection_reason' => 'Foto tidak jelas.',
        ]);
    }

    public function test_mitra_cannot_approve_own_homestay(): void
    {
        $mitra = $this->approvedMitra();
        $homestay = $mitra->mitraProfile->homestays()->create($this->homestayPayload([
            'slug' => 'homestay-kampung-damai',
            'status' => 'pending_review',
        ]));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/admin/homestays/{$homestay->id}/approve")
            ->assertForbidden();
    }

    public function test_public_index_only_shows_published_homestays_from_approved_mitra(): void
    {
        $approvedMitra = $this->approvedMitra();
        $approvedMitra->mitraProfile->homestays()->create($this->homestayPayload([
            'name' => 'Homestay Published',
            'slug' => 'homestay-published',
            'status' => 'published',
        ]));

        $approvedMitra->mitraProfile->homestays()->create($this->homestayPayload([
            'name' => 'Homestay Draft',
            'slug' => 'homestay-draft',
            'status' => 'draft',
        ]));

        $pendingMitra = $this->pendingMitra();
        $pendingMitra->mitraProfile->homestays()->create($this->homestayPayload([
            'name' => 'Homestay From Unapproved Mitra',
            'slug' => 'homestay-unapproved-mitra',
            'status' => 'published',
        ]));

        $response = $this->fromFrontend()->getJson('/api/homestays');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Homestay Published'));
        $this->assertFalse($names->contains('Homestay Draft'));
        $this->assertFalse($names->contains('Homestay From Unapproved Mitra'));
    }

    public function test_public_show_returns_404_for_non_published_homestay(): void
    {
        $mitra = $this->approvedMitra();
        $mitra->mitraProfile->homestays()->create($this->homestayPayload([
            'slug' => 'homestay-draft-hidden',
            'status' => 'draft',
        ]));

        $this->fromFrontend()->getJson('/api/homestays/homestay-draft-hidden')->assertNotFound();
    }

    public function test_public_show_returns_published_homestay_with_facilities(): void
    {
        $mitra = $this->approvedMitra();
        $homestay = $mitra->mitraProfile->homestays()->create($this->homestayPayload([
            'slug' => 'homestay-published-detail',
            'status' => 'published',
        ]));

        $wifi = Facility::create(['name' => 'WiFi', 'category' => 'general']);
        $homestay->facilities()->attach($wifi);

        $response = $this->fromFrontend()->getJson('/api/homestays/homestay-published-detail');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'homestay-published-detail');
        $response->assertJsonPath('data.facilities.0.name', 'WiFi');
    }
}
