<?php

namespace Tests\Feature\Review;

use App\Models\Booking;
use App\Models\MitraProfile;
use App\Models\Review;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewModuleTest extends TestCase
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

    private function villaWithMitra(string $status = 'published'): Villa
    {
        $mitra = User::factory()->create();
        $mitra->assignRole('mitra');
        MitraProfile::create(['user_id' => $mitra->id, 'business_name' => 'Villa Co', 'status' => 'approved']);

        return $mitra->mitraProfile->villas()->create([
            'name' => 'Villa Damai',
            'slug' => 'villa-damai-'.uniqid(),
            'city' => 'Yogyakarta',
            'capacity_guest' => 4,
            'base_price' => 1000000,
            'status' => $status,
        ]);
    }

    private function booking(User $user, Villa $villa, string $status): Booking
    {
        return Booking::create([
            'booking_code' => 'BK'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => Villa::class,
            'bookable_id' => $villa->id,
            'check_in_date' => now()->subDays(10)->format('Y-m-d'),
            'check_out_date' => now()->subDays(7)->format('Y-m-d'),
            'guest_count' => 2,
            'subtotal' => 2000000,
            'total_price' => 2000000,
            'commission_amount' => 200000,
            'mitra_payout_amount' => 1800000,
            'status' => $status,
        ]);
    }

    public function test_user_can_review_a_completed_booking(): void
    {
        $user = $this->regularUser();
        $villa = $this->villaWithMitra();
        $booking = $this->booking($user, $villa, 'selesai');

        $response = $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/review", ['rating' => 5, 'comment' => 'Bagus sekali']);

        $response->assertCreated();
        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'reviewable_type' => Villa::class,
            'reviewable_id' => $villa->id,
            'rating' => 5,
            'comment' => 'Bagus sekali',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $villa->mitraProfile->user_id,
            'type' => 'new_review',
        ]);
    }

    public function test_user_cannot_review_a_booking_that_is_not_selesai(): void
    {
        $user = $this->regularUser();
        $villa = $this->villaWithMitra();
        $booking = $this->booking($user, $villa, 'dikonfirmasi');

        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/review", ['rating' => 4])
            ->assertStatus(422);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_user_cannot_review_a_booking_twice(): void
    {
        $user = $this->regularUser();
        $villa = $this->villaWithMitra();
        $booking = $this->booking($user, $villa, 'selesai');
        Review::create([
            'booking_id' => $booking->id, 'user_id' => $user->id, 'reviewable_type' => Villa::class, 'reviewable_id' => $villa->id, 'rating' => 4,
        ]);

        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/review", ['rating' => 5])
            ->assertStatus(422);

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_user_cannot_review_another_users_booking(): void
    {
        $owner = $this->regularUser();
        $villa = $this->villaWithMitra();
        $booking = $this->booking($owner, $villa, 'selesai');
        $other = $this->regularUser();

        $this->fromFrontend()->actingAs($other)
            ->postJson("/api/bookings/{$booking->id}/review", ['rating' => 3])
            ->assertForbidden();
    }

    public function test_review_rating_must_be_between_1_and_5(): void
    {
        $user = $this->regularUser();
        $villa = $this->villaWithMitra();
        $booking = $this->booking($user, $villa, 'selesai');

        $this->fromFrontend()->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/review", ['rating' => 6])
            ->assertStatus(422);
    }

    public function test_public_can_list_reviews_for_a_published_villa(): void
    {
        $user = $this->regularUser();
        $villa = $this->villaWithMitra();
        $booking = $this->booking($user, $villa, 'selesai');
        Review::create([
            'booking_id' => $booking->id, 'user_id' => $user->id, 'reviewable_type' => Villa::class, 'reviewable_id' => $villa->id,
            'rating' => 5, 'comment' => 'Mantap',
        ]);

        $response = $this->fromFrontend()->getJson("/api/villas/{$villa->slug}/reviews");

        $response->assertOk();
        $response->assertJsonFragment(['comment' => 'Mantap']);
    }

    public function test_public_review_listing_404s_for_a_villa_not_publicly_visible(): void
    {
        $villa = $this->villaWithMitra('pending_review');

        $this->fromFrontend()->getJson("/api/villas/{$villa->slug}/reviews")
            ->assertNotFound();
    }

    public function test_mitra_can_list_reviews_only_for_their_own_villas(): void
    {
        $user = $this->regularUser();
        $villa = $this->villaWithMitra();
        $booking = $this->booking($user, $villa, 'selesai');
        Review::create([
            'booking_id' => $booking->id, 'user_id' => $user->id, 'reviewable_type' => Villa::class, 'reviewable_id' => $villa->id, 'rating' => 5,
        ]);

        $otherVilla = $this->villaWithMitra();
        $otherBooking = $this->booking($this->regularUser(), $otherVilla, 'selesai');
        Review::create([
            'booking_id' => $otherBooking->id, 'user_id' => $otherBooking->user_id, 'reviewable_type' => Villa::class, 'reviewable_id' => $otherVilla->id, 'rating' => 3,
        ]);

        $response = $this->fromFrontend()->actingAs($villa->mitraProfile->user)->getJson('/api/mitra/reviews');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['reviewable' => ['type' => 'villa', 'id' => $villa->id, 'name' => $villa->name]]);
    }

    public function test_mitra_can_reply_to_a_review_on_their_own_villa(): void
    {
        $user = $this->regularUser();
        $villa = $this->villaWithMitra();
        $booking = $this->booking($user, $villa, 'selesai');
        $review = Review::create([
            'booking_id' => $booking->id, 'user_id' => $user->id, 'reviewable_type' => Villa::class, 'reviewable_id' => $villa->id, 'rating' => 5,
        ]);

        $response = $this->fromFrontend()->actingAs($villa->mitraProfile->user)
            ->postJson("/api/mitra/reviews/{$review->id}/reply", ['mitra_reply' => 'Terima kasih!']);

        $response->assertOk();
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'mitra_reply' => 'Terima kasih!']);
        $this->assertNotNull($review->fresh()->mitra_replied_at);
    }

    public function test_mitra_cannot_reply_to_a_review_on_another_mitras_villa(): void
    {
        $user = $this->regularUser();
        $villa = $this->villaWithMitra();
        $booking = $this->booking($user, $villa, 'selesai');
        $review = Review::create([
            'booking_id' => $booking->id, 'user_id' => $user->id, 'reviewable_type' => Villa::class, 'reviewable_id' => $villa->id, 'rating' => 5,
        ]);

        $otherMitra = $this->villaWithMitra()->mitraProfile->user;

        $this->fromFrontend()->actingAs($otherMitra)
            ->postJson("/api/mitra/reviews/{$review->id}/reply", ['mitra_reply' => 'Bukan villa saya'])
            ->assertForbidden();

        $this->assertNull($review->fresh()->mitra_reply);
    }

    public function test_mitra_cannot_reply_to_a_review_twice(): void
    {
        $user = $this->regularUser();
        $villa = $this->villaWithMitra();
        $booking = $this->booking($user, $villa, 'selesai');
        $review = Review::create([
            'booking_id' => $booking->id, 'user_id' => $user->id, 'reviewable_type' => Villa::class, 'reviewable_id' => $villa->id, 'rating' => 5,
            'mitra_reply' => 'Sudah dibalas', 'mitra_replied_at' => now(),
        ]);

        $this->fromFrontend()->actingAs($villa->mitraProfile->user)
            ->postJson("/api/mitra/reviews/{$review->id}/reply", ['mitra_reply' => 'Balasan kedua'])
            ->assertStatus(422);

        $this->assertSame('Sudah dibalas', $review->fresh()->mitra_reply);
    }
}
