<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Glamping;
use App\Models\MitraProfile;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\Concerns\DemoSeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Top-up seeder for the Glamping category — adds the "Kaliwatu Glamping
 * Nusantara" mitra, its 4 listings, and a handful of bookings/review/payout
 * covering every lifecycle status, WITHOUT touching any other table.
 *
 * Safe to run standalone against a server that already has other demo (or
 * real) data:
 *   php artisan db:seed --class=GlampingDemoSeeder
 *
 * Also called automatically from DemoDataSeeder for fresh installs. Skips
 * itself if this mitra already has glamping listings, so re-running it is
 * a no-op rather than a duplicate.
 */
class GlampingDemoSeeder extends Seeder
{
    use DemoSeederHelpers;

    public function run(): void
    {
        $mitra = $this->glampingMitra();

        if ($mitra->glampings()->exists()) {
            $this->command?->warn('Data demo Glamping sudah pernah di-seed sebelumnya, dilewati.');

            return;
        }

        $this->downloadImagePool();
        $admin = $this->demoAdmin();
        $users = $this->demoUsers();

        $glampings = $this->createGlampings($mitra, $admin);
        $bookings = $this->createBookings($users, $glampings);
        $this->createReview($bookings);
        $this->createPayout($mitra, $bookings);

        $this->command?->info('Data demo Glamping berhasil ditambahkan (mitra "Kaliwatu Glamping Nusantara").');
    }

    private function glampingMitra(): MitraProfile
    {
        $admin = $this->demoAdmin();

        $user = User::firstOrCreate(
            ['email' => 'mitra7-glamping@sarepundi.demo'],
            [
                'name' => 'Kaliwatu Glamping Nusantara (Pemilik)',
                'password' => bcrypt('password'),
                'phone' => '0812'.rand(10000000, 99999999),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        if (! $user->hasRole('mitra')) {
            $user->assignRole('mitra');
        }

        return MitraProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'business_name' => 'Kaliwatu Glamping Nusantara',
                'business_address' => 'Indonesia',
                'bank_name' => 'BCA',
                'bank_account' => (string) rand(1000000000, 9999999999),
                'status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now()->subDays(30),
            ]
        );
    }

    /** @return array<string, Glamping> */
    private function createGlampings(MitraProfile $mitra, User $admin): array
    {
        $facilityIds = Facility::pluck('id', 'name');
        $glampings = [];

        $roster = [
            'batu' => ['name' => 'Kaliwatu Glamping Batu', 'city' => 'Batu', 'price' => 850000, 'status' => 'published'],
            'bromo' => ['name' => 'Glamping Bromo Savana', 'city' => 'Probolinggo', 'price' => 750000, 'status' => 'published'],
            'malang' => ['name' => 'Glamping Coban Rondo Malang', 'city' => 'Malang', 'price' => 650000, 'status' => 'published'],
            'pacet' => ['name' => 'Glamping Pacet Hills', 'city' => 'Mojokerto', 'price' => 700000, 'status' => 'pending_review'],
        ];

        foreach ($roster as $handle => $info) {
            $glamping = $mitra->glampings()->create([
                'name' => $info['name'],
                'slug' => Str::slug($info['name']),
                'description' => "{$info['name']} menghadirkan pengalaman camping ala hotel di tengah alam {$info['city']}, lengkap dengan tenda dome nyaman dan fasilitas layaknya penginapan bintang.",
                'address' => "Kawasan Wisata {$info['city']}",
                'city' => $info['city'],
                'province' => 'Jawa Timur',
                'capacity_guest' => rand(2, 6),
                'bedroom_count' => rand(1, 3),
                'bathroom_count' => rand(1, 2),
                'base_price' => $info['price'],
                'status' => $info['status'],
                'reviewed_by' => $info['status'] === 'published' ? $admin->id : null,
                'reviewed_at' => $info['status'] === 'published' ? now()->subDays(10) : null,
            ]);

            $this->attachFacilities($glamping, $facilityIds);
            $this->attachImages($glamping, 'glamping-images', 3);

            $glampings[$handle] = $glamping;
        }

        return $glampings;
    }

    /**
     * @param  array<int, User>  $users
     * @param  array<string, Glamping>  $glampings
     * @return array<string, \App\Models\Booking>
     */
    private function createBookings(array $users, array $glampings): array
    {
        $bookings = [];

        [$total, $commission, $payout] = $this->pricing($glampings['batu']->base_price, 2);
        $bookings['glamping_pending_payment'] = $this->makeBooking([
            'user_id' => $users[2]->id, 'bookable_type' => Glamping::class, 'bookable_id' => $glampings['batu']->id,
            'check_in_date' => now()->addDays(18), 'check_out_date' => now()->addDays(20), 'guest_count' => 2,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'pending_payment',
        ]);

        [$total, $commission, $payout] = $this->pricing($glampings['batu']->base_price, 2);
        $booking = $this->makeBooking([
            'user_id' => $users[6]->id, 'bookable_type' => Glamping::class, 'bookable_id' => $glampings['batu']->id,
            'check_in_date' => now()->addDays(6), 'check_out_date' => now()->addDays(8), 'guest_count' => 4,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dikonfirmasi', 'mitra_confirmed_at' => now()->subHours(5),
        ]);
        $this->makePayment($booking, 'success', now()->subHours(5));
        $bookings['glamping_confirmed_recent'] = $booking;

        [$total, $commission, $payout] = $this->pricing($glampings['bromo']->base_price, 1);
        $booking = $this->makeBooking([
            'user_id' => $users[7]->id, 'bookable_type' => Glamping::class, 'bookable_id' => $glampings['bromo']->id,
            'check_in_date' => now()->addDays(4), 'check_out_date' => now()->addDays(5), 'guest_count' => 2,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dikonfirmasi', 'mitra_confirmed_at' => now()->subDay(),
        ]);
        $this->makePayment($booking, 'success', now()->subDays(2));
        $bookings['glamping_confirmed'] = $booking;

        [$total, $commission, $payout] = $this->pricing($glampings['malang']->base_price, 2);
        $booking = $this->makeBooking([
            'user_id' => $users[3]->id, 'bookable_type' => Glamping::class, 'bookable_id' => $glampings['malang']->id,
            'check_in_date' => now()->subDays(9), 'check_out_date' => now()->subDays(7), 'guest_count' => 3,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'selesai', 'mitra_confirmed_at' => now()->subDays(11),
        ]);
        $this->makePayment($booking, 'success', now()->subDays(12));
        $bookings['glamping_done'] = $booking;

        [$total, $commission, $payout] = $this->pricing($glampings['bromo']->base_price, 2);
        $refundAmount = (int) round($total * 0.85);
        $booking = $this->makeBooking([
            'user_id' => $users[2]->id, 'bookable_type' => Glamping::class, 'bookable_id' => $glampings['bromo']->id,
            'check_in_date' => now()->addDays(13), 'check_out_date' => now()->addDays(15), 'guest_count' => 2,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dibatalkan_user', 'mitra_confirmed_at' => now()->subDays(2),
            'cancellation_reason' => 'user_cancel_confirmed', 'cancelled_at' => now()->subHours(6),
            'refund_amount' => $refundAmount, 'refund_percentage' => 85,
        ]);
        $payment = $this->makePayment($booking, 'refunded', now()->subDays(3));
        \App\Models\Refund::create(['booking_id' => $booking->id, 'payment_id' => $payment->id, 'amount' => $refundAmount, 'percentage' => 85, 'reason' => 'user_cancel_confirmed', 'xendit_refund_id' => 'demo_rfd_'.Str::random(10), 'status' => 'succeeded', 'processed_at' => now()->subHours(5)]);
        $bookings['glamping_cancelled_user'] = $booking;

        return $bookings;
    }

    private function createReview(array $bookings): void
    {
        $booking = $bookings['glamping_done'];

        Review::create([
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'reviewable_type' => $booking->bookable_type,
            'reviewable_id' => $booking->bookable_id,
            'rating' => 5,
            'comment' => 'Serasa camping tapi tidurnya empuk seperti di hotel, pemandangan alamnya juara!',
            'mitra_reply' => 'Terima kasih sudah camping bareng kami, ditunggu kunjungan berikutnya!',
            'mitra_replied_at' => now()->subDays(3),
        ]);
    }

    private function createPayout(MitraProfile $mitra, array $bookings): void
    {
        $booking = $bookings['glamping_done'];

        $payout = \App\Models\Payout::create([
            'mitra_id' => $mitra->id,
            'amount' => $booking->mitra_payout_amount,
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->subDays(1)->toDateString(),
            'xendit_disbursement_id' => 'demo_disb_'.Str::random(12),
            'status' => 'completed',
            'processed_at' => now()->subDays(2),
        ]);

        $booking->update(['payout_id' => $payout->id]);
    }
}
