<?php

namespace Tests\Feature\GatheringVenue;

use App\Models\Facility;
use App\Models\MitraProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GatheringVenueModuleTest extends TestCase
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
            'business_name' => 'Gathering Approved Co',
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
            'business_name' => 'Gathering Pending Co',
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

    private function venuePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Balai Gathering Merapi',
            'description' => 'Venue outdoor dengan pemandangan gunung.',
            'city' => 'Yogyakarta',
            'province' => 'DI Yogyakarta',
            'capacity' => 100,
        ], $overrides);
    }

    public function test_approved_mitra_can_create_gathering_venue_as_draft(): void
    {
        $mitra = $this->approvedMitra();

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson('/api/mitra/gathering-venues', $this->venuePayload());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.name', 'Balai Gathering Merapi');
        $this->assertDatabaseHas('gathering_venues', ['name' => 'Balai Gathering Merapi', 'status' => 'draft']);
    }

    public function test_mitra_cannot_view_or_edit_another_mitras_venue(): void
    {
        $owner = $this->approvedMitra();
        $venue = $owner->mitraProfile->gatheringVenues()->create($this->venuePayload(['slug' => 'balai-gathering-merapi']));

        $otherMitra = $this->approvedMitra();

        $this->fromFrontend()->actingAs($otherMitra)
            ->getJson("/api/mitra/gathering-venues/{$venue->id}")
            ->assertForbidden();

        $this->fromFrontend()->actingAs($otherMitra)
            ->putJson("/api/mitra/gathering-venues/{$venue->id}", ['name' => 'Hijacked'])
            ->assertForbidden();
    }

    public function test_regular_user_cannot_access_mitra_gathering_venue_endpoints(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->fromFrontend()->actingAs($user)
            ->postJson('/api/mitra/gathering-venues', $this->venuePayload())
            ->assertForbidden();
    }

    public function test_approved_mitra_can_submit_draft_venue_for_review(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $mitra->mitraProfile->gatheringVenues()->create($this->venuePayload(['slug' => 'balai-gathering-merapi']));

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/gathering-venues/{$venue->id}/submit");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending_review');
    }

    public function test_pending_mitra_cannot_submit_venue_for_review(): void
    {
        $mitra = $this->pendingMitra();
        $venue = $mitra->mitraProfile->gatheringVenues()->create($this->venuePayload(['slug' => 'balai-gathering-merapi']));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/gathering-venues/{$venue->id}/submit")
            ->assertForbidden();
    }

    public function test_admin_can_approve_pending_venue(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $mitra->mitraProfile->gatheringVenues()->create($this->venuePayload([
            'slug' => 'balai-gathering-merapi',
            'status' => 'pending_review',
        ]));

        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/gathering-venues/{$venue->id}/approve");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'published');
        $this->assertDatabaseHas('notifications', ['user_id' => $mitra->id, 'type' => 'gathering_venue_approved']);
    }

    public function test_admin_can_reject_pending_venue_with_reason(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $mitra->mitraProfile->gatheringVenues()->create($this->venuePayload([
            'slug' => 'balai-gathering-merapi',
            'status' => 'pending_review',
        ]));

        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/gathering-venues/{$venue->id}/reject", ['reason' => 'Kapasitas tidak sesuai.']);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'rejected');
    }

    public function test_public_index_only_shows_published_venues_from_approved_mitra(): void
    {
        $approvedMitra = $this->approvedMitra();
        $approvedMitra->mitraProfile->gatheringVenues()->create($this->venuePayload([
            'name' => 'Venue Published',
            'slug' => 'venue-published',
            'status' => 'published',
        ]));

        $approvedMitra->mitraProfile->gatheringVenues()->create($this->venuePayload([
            'name' => 'Venue Draft',
            'slug' => 'venue-draft',
            'status' => 'draft',
        ]));

        $response = $this->fromFrontend()->getJson('/api/gathering-venues');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Venue Published'));
        $this->assertFalse($names->contains('Venue Draft'));
    }

    public function test_public_show_returns_404_for_non_published_venue(): void
    {
        $mitra = $this->approvedMitra();
        $mitra->mitraProfile->gatheringVenues()->create($this->venuePayload([
            'slug' => 'venue-draft-hidden',
            'status' => 'draft',
        ]));

        $this->fromFrontend()->getJson('/api/gathering-venues/venue-draft-hidden')->assertNotFound();
    }

    public function test_public_show_returns_published_venue_with_facilities(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $mitra->mitraProfile->gatheringVenues()->create($this->venuePayload([
            'slug' => 'venue-published-detail',
            'status' => 'published',
        ]));

        $wifi = Facility::create(['name' => 'WiFi', 'category' => 'general']);
        $venue->facilities()->attach($wifi);

        $response = $this->fromFrontend()->getJson('/api/gathering-venues/venue-published-detail');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'venue-published-detail');
        $response->assertJsonPath('data.facilities.0.name', 'WiFi');
    }

    // --- Slot management ---

    public function test_mitra_can_add_a_slot_to_their_venue(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $mitra->mitraProfile->gatheringVenues()->create($this->venuePayload(['slug' => 'balai-gathering-merapi']));

        $response = $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/gathering-venues/{$venue->id}/slots", [
                'name' => 'Sesi Pagi',
                'start_time' => '08:00',
                'end_time' => '12:00',
                'price' => 500000,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Sesi Pagi');
        $response->assertJsonPath('data.start_time', '08:00');
        $response->assertJsonPath('data.end_time', '12:00');
        $this->assertDatabaseHas('gathering_venue_slots', ['gathering_venue_id' => $venue->id, 'name' => 'Sesi Pagi', 'price' => 500000]);
    }

    public function test_slot_end_time_must_be_after_start_time(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $mitra->mitraProfile->gatheringVenues()->create($this->venuePayload(['slug' => 'balai-gathering-merapi']));

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/gathering-venues/{$venue->id}/slots", [
                'name' => 'Sesi Salah',
                'start_time' => '12:00',
                'end_time' => '08:00',
                'price' => 500000,
            ])
            ->assertStatus(422);
    }

    public function test_mitra_cannot_add_slot_to_another_mitras_venue(): void
    {
        $owner = $this->approvedMitra();
        $venue = $owner->mitraProfile->gatheringVenues()->create($this->venuePayload(['slug' => 'balai-gathering-merapi']));

        $otherMitra = $this->approvedMitra();

        $this->fromFrontend()->actingAs($otherMitra)
            ->postJson("/api/mitra/gathering-venues/{$venue->id}/slots", [
                'name' => 'Sesi Nakal', 'start_time' => '08:00', 'end_time' => '12:00', 'price' => 100,
            ])
            ->assertForbidden();
    }

    public function test_mitra_can_update_and_delete_own_slot(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $mitra->mitraProfile->gatheringVenues()->create($this->venuePayload(['slug' => 'balai-gathering-merapi']));
        $slot = $venue->slots()->create([
            'name' => 'Sesi Siang', 'start_time' => '13:00', 'end_time' => '17:00', 'price' => 600000,
        ]);

        $this->fromFrontend()->actingAs($mitra)
            ->patchJson("/api/mitra/gathering-venues/{$venue->id}/slots/{$slot->id}", ['price' => 750000])
            ->assertOk()
            ->assertJsonPath('data.price', 750000);

        $this->fromFrontend()->actingAs($mitra)
            ->deleteJson("/api/mitra/gathering-venues/{$venue->id}/slots/{$slot->id}")
            ->assertOk();

        $this->assertDatabaseMissing('gathering_venue_slots', ['id' => $slot->id]);
    }

    public function test_public_detail_shows_active_slots_with_starting_price(): void
    {
        $mitra = $this->approvedMitra();
        $venue = $mitra->mitraProfile->gatheringVenues()->create($this->venuePayload([
            'slug' => 'venue-with-slots',
            'status' => 'published',
        ]));
        $venue->slots()->create(['name' => 'Sesi Pagi', 'start_time' => '08:00', 'end_time' => '12:00', 'price' => 500000]);
        $venue->slots()->create(['name' => 'Sesi Malam', 'start_time' => '18:00', 'end_time' => '22:00', 'price' => 800000]);

        $response = $this->fromFrontend()->getJson('/api/gathering-venues/venue-with-slots');

        $response->assertOk();
        $response->assertJsonPath('data.starting_price', 500000);
        $this->assertCount(2, $response->json('data.slots'));
    }
}
