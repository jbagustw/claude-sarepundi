<?php

namespace Tests\Feature\Apartment;

use App\Models\Facility;
use App\Models\MitraProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApartmentModuleTest extends TestCase
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
            'business_name' => 'Apartment Approved Co',
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
            'business_name' => 'Apartment Pending Co',
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

    private function apartmentPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Apartemen Sudirman Suites',
            'description' => 'Unit apartemen nyaman dekat pusat kota, cocok untuk sewa harian.',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'capacity_guest' => 3,
            'bedroom_count' => 1,
            'bathroom_count' => 1,
            'base_price' => 500000,
        ], $overrides);
    }

    public function test_approved_mitra_can_create_apartment_as_draft(): void
    {
        $mitra = $this->approvedMitra();

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson('/api/mitra/apartments', $this->apartmentPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.name', 'Apartemen Sudirman Suites');
        $this->assertDatabaseHas('apartments', ['name' => 'Apartemen Sudirman Suites', 'status' => 'draft']);
    }

    public function test_mitra_cannot_view_or_edit_another_mitras_apartment(): void
    {
        $owner = $this->approvedMitra();
        $apartment = $owner->mitraProfile->apartments()->create($this->apartmentPayload(['slug' => 'apartemen-sudirman-suites']));

        $otherMitra = $this->approvedMitra();

        $this->fromFrontend()->actingAs($otherMitra)
            ->getJson("/api/mitra/apartments/{$apartment->id}")
            ->assertForbidden();

        $this->fromFrontend()->actingAs($otherMitra)
            ->putJson("/api/mitra/apartments/{$apartment->id}", ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('apartments', ['id' => $apartment->id, 'name' => 'Apartemen Sudirman Suites']);
    }

    public function test_regular_user_cannot_access_mitra_apartment_endpoints(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->fromFrontend()->actingAs($user)
            ->postJson('/api/mitra/apartments', $this->apartmentPayload())
            ->assertForbidden();
    }

    public function test_approved_mitra_can_submit_draft_apartment_for_review(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $mitra->mitraProfile->apartments()->create($this->apartmentPayload(['slug' => 'apartemen-sudirman-suites']));

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/apartments/{$apartment->id}/submit");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending_review');
    }

    public function test_pending_mitra_cannot_submit_apartment_for_review(): void
    {
        $mitra = $this->pendingMitra();
        $apartment = $mitra->mitraProfile->apartments()->create($this->apartmentPayload(['slug' => 'apartemen-sudirman-suites']));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/apartments/{$apartment->id}/submit")
            ->assertForbidden();

        $this->assertDatabaseHas('apartments', ['id' => $apartment->id, 'status' => 'draft']);
    }

    public function test_admin_can_approve_pending_apartment(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $mitra->mitraProfile->apartments()->create($this->apartmentPayload([
            'slug' => 'apartemen-sudirman-suites',
            'status' => 'pending_review',
        ]));

        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/apartments/{$apartment->id}/approve");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'published');
        $this->assertDatabaseHas('apartments', ['id' => $apartment->id, 'status' => 'published', 'reviewed_by' => $admin->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $mitra->id, 'type' => 'apartment_approved']);
    }

    public function test_admin_can_reject_pending_apartment_with_reason(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $mitra->mitraProfile->apartments()->create($this->apartmentPayload([
            'slug' => 'apartemen-sudirman-suites',
            'status' => 'pending_review',
        ]));

        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/apartments/{$apartment->id}/reject", ['reason' => 'Foto tidak jelas.']);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'rejected');
        $this->assertDatabaseHas('apartments', [
            'id' => $apartment->id,
            'status' => 'rejected',
            'rejection_reason' => 'Foto tidak jelas.',
        ]);
    }

    public function test_mitra_cannot_approve_own_apartment(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $mitra->mitraProfile->apartments()->create($this->apartmentPayload([
            'slug' => 'apartemen-sudirman-suites',
            'status' => 'pending_review',
        ]));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/admin/apartments/{$apartment->id}/approve")
            ->assertForbidden();
    }

    public function test_public_index_only_shows_published_apartments_from_approved_mitra(): void
    {
        $approvedMitra = $this->approvedMitra();
        $approvedMitra->mitraProfile->apartments()->create($this->apartmentPayload([
            'name' => 'Apartment Published',
            'slug' => 'apartment-published',
            'status' => 'published',
        ]));

        $approvedMitra->mitraProfile->apartments()->create($this->apartmentPayload([
            'name' => 'Apartment Draft',
            'slug' => 'apartment-draft',
            'status' => 'draft',
        ]));

        $pendingMitra = $this->pendingMitra();
        $pendingMitra->mitraProfile->apartments()->create($this->apartmentPayload([
            'name' => 'Apartment From Unapproved Mitra',
            'slug' => 'apartment-unapproved-mitra',
            'status' => 'published',
        ]));

        $response = $this->fromFrontend()->getJson('/api/apartments');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Apartment Published'));
        $this->assertFalse($names->contains('Apartment Draft'));
        $this->assertFalse($names->contains('Apartment From Unapproved Mitra'));
    }

    public function test_public_show_returns_404_for_non_published_apartment(): void
    {
        $mitra = $this->approvedMitra();
        $mitra->mitraProfile->apartments()->create($this->apartmentPayload([
            'slug' => 'apartment-draft-hidden',
            'status' => 'draft',
        ]));

        $this->fromFrontend()->getJson('/api/apartments/apartment-draft-hidden')->assertNotFound();
    }

    public function test_public_show_returns_published_apartment_with_facilities(): void
    {
        $mitra = $this->approvedMitra();
        $apartment = $mitra->mitraProfile->apartments()->create($this->apartmentPayload([
            'slug' => 'apartment-published-detail',
            'status' => 'published',
        ]));

        $wifi = Facility::create(['name' => 'WiFi', 'category' => 'general']);
        $apartment->facilities()->attach($wifi);

        $response = $this->fromFrontend()->getJson('/api/apartments/apartment-published-detail');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'apartment-published-detail');
        $response->assertJsonPath('data.facilities.0.name', 'WiFi');
    }

    public function test_public_index_search_matches_city_as_well_as_name(): void
    {
        $mitra = $this->approvedMitra();
        $mitra->mitraProfile->apartments()->create($this->apartmentPayload([
            'name' => 'Apartment Alpha',
            'slug' => 'apartment-alpha',
            'city' => 'Jakarta',
            'status' => 'published',
        ]));
        $mitra->mitraProfile->apartments()->create($this->apartmentPayload([
            'name' => 'Apartment Beta',
            'slug' => 'apartment-beta',
            'city' => 'Surabaya',
            'status' => 'published',
        ]));

        $response = $this->fromFrontend()->getJson('/api/apartments?q=jakarta');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Apartment Alpha'));
        $this->assertFalse($names->contains('Apartment Beta'));
    }
}
