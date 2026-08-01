<?php

namespace Tests\Feature\Notification;

use App\Models\Booking;
use App\Models\MitraProfile;
use App\Models\Notification;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationModuleTest extends TestCase
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

    private function regularUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    private function adminUser(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function mitraUser(): User
    {
        $mitra = User::factory()->create();
        $mitra->assignRole('mitra');
        MitraProfile::create(['user_id' => $mitra->id, 'business_name' => 'Villa Co', 'status' => 'approved']);

        return $mitra;
    }

    public function test_user_can_list_own_notifications_with_accurate_unread_count(): void
    {
        $user = $this->regularUser();
        Notification::create(['user_id' => $user->id, 'type' => 'info', 'title' => 'A', 'message' => 'a', 'is_read' => false]);
        Notification::create(['user_id' => $user->id, 'type' => 'info', 'title' => 'B', 'message' => 'b', 'is_read' => false]);
        Notification::create(['user_id' => $user->id, 'type' => 'info', 'title' => 'C', 'message' => 'c', 'is_read' => true]);

        $response = $this->fromFrontend()->actingAs($user)->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonPath('meta.unread_count', 2);
    }

    public function test_user_only_sees_their_own_notifications(): void
    {
        $user = $this->regularUser();
        $other = $this->regularUser();
        Notification::create(['user_id' => $other->id, 'type' => 'info', 'title' => 'Not mine', 'message' => 'x', 'is_read' => false]);

        $response = $this->fromFrontend()->actingAs($user)->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $user = $this->regularUser();
        $notification = Notification::create([
            'user_id' => $user->id, 'type' => 'info', 'title' => 'A', 'message' => 'a', 'is_read' => false,
        ]);

        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = $this->regularUser();
        $other = $this->regularUser();
        $notification = Notification::create([
            'user_id' => $owner->id, 'type' => 'info', 'title' => 'A', 'message' => 'a', 'is_read' => false,
        ]);

        $this->fromFrontend()->actingAs($other)
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertForbidden();

        $this->assertFalse($notification->fresh()->is_read);
    }

    public function test_user_can_mark_all_their_notifications_as_read_without_touching_others(): void
    {
        $user = $this->regularUser();
        $other = $this->regularUser();
        $mine1 = Notification::create(['user_id' => $user->id, 'type' => 'info', 'title' => 'A', 'message' => 'a', 'is_read' => false]);
        $mine2 = Notification::create(['user_id' => $user->id, 'type' => 'info', 'title' => 'B', 'message' => 'b', 'is_read' => false]);
        $theirs = Notification::create(['user_id' => $other->id, 'type' => 'info', 'title' => 'C', 'message' => 'c', 'is_read' => false]);

        $this->fromFrontend()->actingAs($user)
            ->postJson('/api/notifications/read-all')
            ->assertOk();

        $this->assertTrue($mine1->fresh()->is_read);
        $this->assertTrue($mine2->fresh()->is_read);
        $this->assertFalse($theirs->fresh()->is_read);
    }

    public function test_villa_approval_notifies_the_owning_mitra(): void
    {
        $admin = $this->adminUser();
        $mitra = $this->mitraUser();
        $villa = $mitra->mitraProfile->villas()->create([
            'name' => 'Villa Damai',
            'slug' => 'villa-damai-'.uniqid(),
            'city' => 'Yogyakarta',
            'capacity_guest' => 4,
            'base_price' => 1000000,
            'status' => 'pending_review',
        ]);

        $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/villas/{$villa->id}/approve")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $mitra->id,
            'type' => 'villa_approved',
        ]);
    }

    public function test_mitra_confirming_a_booking_notifies_the_user(): void
    {
        $mitra = $this->mitraUser();
        $villa = $mitra->mitraProfile->villas()->create([
            'name' => 'Villa Damai',
            'slug' => 'villa-damai-'.uniqid(),
            'city' => 'Yogyakarta',
            'capacity_guest' => 4,
            'base_price' => 1000000,
            'status' => 'published',
        ]);
        $user = $this->regularUser();
        $booking = Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => Villa::class,
            'bookable_id' => $villa->id,
            'check_in_date' => now()->addDays(10)->format('Y-m-d'),
            'check_out_date' => now()->addDays(13)->format('Y-m-d'),
            'guest_count' => 2,
            'subtotal' => 3000000,
            'total_price' => 3000000,
            'commission_amount' => 300000,
            'mitra_payout_amount' => 2700000,
            'status' => 'menunggu_konfirmasi',
            'mitra_confirmation_deadline' => now()->addHours(24),
        ]);

        $this->fromFrontend()->actingAs($mitra)
            ->postJson("/api/mitra/bookings/{$booking->id}/accept")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'booking_confirmed',
        ]);
    }
}
