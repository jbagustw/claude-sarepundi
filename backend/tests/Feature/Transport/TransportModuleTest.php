<?php

namespace Tests\Feature\Transport;

use App\Models\MitraProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransportModuleTest extends TestCase
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
            'business_name' => 'Transport Approved Co',
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
            'business_name' => 'Transport Pending Co',
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

    private function transportPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Toyota Avanza Silver',
            'vehicle_type' => 'MPV',
            'description' => 'Mobil keluarga nyaman untuk perjalanan wisata.',
            'capacity' => 7,
            'city' => 'Yogyakarta',
            'province' => 'DI Yogyakarta',
            'price_per_day_self_drive' => 350000,
            'price_per_day_with_driver' => 500000,
        ], $overrides);
    }

    public function test_approved_mitra_can_create_transport_as_draft(): void
    {
        $mitra = $this->approvedMitra();

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson('/api/mitra/transports', $this->transportPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.name', 'Toyota Avanza Silver');
        $this->assertDatabaseHas('transports', ['name' => 'Toyota Avanza Silver', 'status' => 'draft']);
    }

    public function test_at_least_one_price_must_be_provided(): void
    {
        $mitra = $this->approvedMitra();

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson('/api/mitra/transports', $this->transportPayload([
                'price_per_day_self_drive' => null,
                'price_per_day_with_driver' => null,
            ]));

        $response->assertStatus(422);
    }

    public function test_self_drive_only_listing_is_valid(): void
    {
        $mitra = $this->approvedMitra();

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson('/api/mitra/transports', $this->transportPayload([
                'price_per_day_with_driver' => null,
            ]));

        $response->assertCreated();
        $response->assertJsonPath('data.price_per_day_with_driver', null);
        $response->assertJsonPath('data.price_per_day_self_drive', 350000);
    }

    public function test_mitra_cannot_view_or_edit_another_mitras_transport(): void
    {
        $owner = $this->approvedMitra();
        $transport = $owner->mitraProfile->transports()->create($this->transportPayload(['slug' => 'toyota-avanza-silver']));

        $otherMitra = $this->approvedMitra();

        $this->fromFrontend()->actingAs($otherMitra)
            ->getJson("/api/mitra/transports/{$transport->id}")
            ->assertForbidden();

        $this->fromFrontend()->actingAs($otherMitra)
            ->putJson("/api/mitra/transports/{$transport->id}", ['name' => 'Hijacked'])
            ->assertForbidden();
    }

    public function test_regular_user_cannot_access_mitra_transport_endpoints(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->fromFrontend()->actingAs($user)
            ->postJson('/api/mitra/transports', $this->transportPayload())
            ->assertForbidden();
    }

    public function test_approved_mitra_can_submit_draft_transport_for_review(): void
    {
        $mitra = $this->approvedMitra();
        $transport = $mitra->mitraProfile->transports()->create($this->transportPayload(['slug' => 'toyota-avanza-silver']));

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/transports/{$transport->id}/submit");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending_review');
    }

    public function test_pending_mitra_cannot_submit_transport_for_review(): void
    {
        $mitra = $this->pendingMitra();
        $transport = $mitra->mitraProfile->transports()->create($this->transportPayload(['slug' => 'toyota-avanza-silver']));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/transports/{$transport->id}/submit")
            ->assertForbidden();
    }

    public function test_admin_can_approve_pending_transport(): void
    {
        $mitra = $this->approvedMitra();
        $transport = $mitra->mitraProfile->transports()->create($this->transportPayload([
            'slug' => 'toyota-avanza-silver',
            'status' => 'pending_review',
        ]));

        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/transports/{$transport->id}/approve");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'published');
        $this->assertDatabaseHas('notifications', ['user_id' => $mitra->id, 'type' => 'transport_approved']);
    }

    public function test_admin_can_reject_pending_transport_with_reason(): void
    {
        $mitra = $this->approvedMitra();
        $transport = $mitra->mitraProfile->transports()->create($this->transportPayload([
            'slug' => 'toyota-avanza-silver',
            'status' => 'pending_review',
        ]));

        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/transports/{$transport->id}/reject", ['reason' => 'Foto kendaraan tidak jelas.']);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'rejected');
    }

    public function test_public_index_only_shows_published_transports_from_approved_mitra(): void
    {
        $approvedMitra = $this->approvedMitra();
        $approvedMitra->mitraProfile->transports()->create($this->transportPayload([
            'name' => 'Transport Published',
            'slug' => 'transport-published',
            'status' => 'published',
        ]));

        $approvedMitra->mitraProfile->transports()->create($this->transportPayload([
            'name' => 'Transport Draft',
            'slug' => 'transport-draft',
            'status' => 'draft',
        ]));

        $response = $this->fromFrontend()->getJson('/api/transports');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Transport Published'));
        $this->assertFalse($names->contains('Transport Draft'));
    }

    public function test_public_show_returns_404_for_non_published_transport(): void
    {
        $mitra = $this->approvedMitra();
        $mitra->mitraProfile->transports()->create($this->transportPayload([
            'slug' => 'transport-draft-hidden',
            'status' => 'draft',
        ]));

        $this->fromFrontend()->getJson('/api/transports/transport-draft-hidden')->assertNotFound();
    }

    public function test_public_index_can_filter_by_with_driver_availability(): void
    {
        $mitra = $this->approvedMitra();
        $mitra->mitraProfile->transports()->create($this->transportPayload([
            'name' => 'Self Drive Only',
            'slug' => 'self-drive-only',
            'status' => 'published',
            'price_per_day_with_driver' => null,
        ]));
        $mitra->mitraProfile->transports()->create($this->transportPayload([
            'name' => 'With Driver Option',
            'slug' => 'with-driver-option',
            'status' => 'published',
        ]));

        $response = $this->fromFrontend()->getJson('/api/transports?with_driver=1');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('With Driver Option'));
        $this->assertFalse($names->contains('Self Drive Only'));
    }
}
