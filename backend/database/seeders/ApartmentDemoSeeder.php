<?php

namespace Database\Seeders;

use App\Models\Apartment;
use App\Models\Facility;
use App\Models\MitraProfile;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\Concerns\DemoSeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Top-up seeder for the Apartment category — adds the "Jakarta Apartment
 * Living" mitra, its 4 listings, and a handful of bookings/review/payout
 * covering every lifecycle status, WITHOUT touching any other table.
 *
 * Safe to run standalone against a server that already has other demo (or
 * real) data:
 *   php artisan db:seed --class=ApartmentDemoSeeder
 *
 * Also called automatically from DemoDataSeeder for fresh installs. Skips
 * itself if this mitra already has apartment listings, so re-running it is
 * a no-op rather than a duplicate.
 */
class ApartmentDemoSeeder extends Seeder
{
    use DemoSeederHelpers;

    public function run(): void
    {
        $mitra = $this->apartmentMitra();

        if ($mitra->apartments()->exists()) {
            $this->command?->warn('Data demo Apartment sudah pernah di-seed sebelumnya, dilewati.');

            return;
        }

        $this->downloadImagePool();
        $admin = $this->demoAdmin();
        $users = $this->demoUsers();

        $apartments = $this->createApartments($mitra, $admin);
        $bookings = $this->createBookings($users, $apartments);
        $this->createReview($bookings);
        $this->createPayout($mitra, $bookings);

        $this->command?->info('Data demo Apartment berhasil ditambahkan (mitra "Jakarta Apartment Living").');
    }

    private function apartmentMitra(): MitraProfile
    {
        $admin = $this->demoAdmin();

        $user = User::firstOrCreate(
            ['email' => 'mitra8-apartment@sarepundi.demo'],
            [
                'name' => 'Jakarta Apartment Living (Pemilik)',
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
                'business_name' => 'Jakarta Apartment Living',
                'business_address' => 'Indonesia',
                'bank_name' => 'BCA',
                'bank_account' => (string) rand(1000000000, 9999999999),
                'status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now()->subDays(30),
            ]
        );
    }

    /** @return array<string, Apartment> */
    private function createApartments(MitraProfile $mitra, User $admin): array
    {
        $facilityIds = Facility::pluck('id', 'name');
        $apartments = [];

        $roster = [
            'kemang' => ['name' => 'Apartemen Kemang Village', 'city' => 'Jakarta', 'price' => 550000, 'status' => 'published'],
            'sudirman' => ['name' => 'Apartemen Sudirman Suites', 'city' => 'Jakarta', 'price' => 650000, 'status' => 'published'],
            'gubeng' => ['name' => 'Apartemen Gubeng Residence', 'city' => 'Surabaya', 'price' => 400000, 'status' => 'published'],
            'dharmahusada' => ['name' => 'Apartemen Dharmahusada Tower', 'city' => 'Surabaya', 'price' => 380000, 'status' => 'pending_review'],
        ];

        foreach ($roster as $handle => $info) {
            $apartment = $mitra->apartments()->create([
                'name' => $info['name'],
                'slug' => Str::slug($info['name']),
                'description' => "{$info['name']} adalah unit apartemen yang bisa disewa harian, lengkap dengan fasilitas modern di lokasi strategis {$info['city']}.",
                'address' => "Jl. {$info['city']} No. ".rand(1, 99),
                'city' => $info['city'],
                'province' => $info['city'] === 'Jakarta' ? 'DKI Jakarta' : 'Jawa Timur',
                'capacity_guest' => rand(2, 4),
                'bedroom_count' => rand(1, 2),
                'bathroom_count' => 1,
                'base_price' => $info['price'],
                'status' => $info['status'],
                'reviewed_by' => $info['status'] === 'published' ? $admin->id : null,
                'reviewed_at' => $info['status'] === 'published' ? now()->subDays(8) : null,
            ]);

            $this->attachFacilities($apartment, $facilityIds);
            $this->attachImages($apartment, 'apartment-images', 3);

            $apartments[$handle] = $apartment;
        }

        return $apartments;
    }

    /**
     * @param  array<int, User>  $users
     * @param  array<string, Apartment>  $apartments
     * @return array<string, \App\Models\Booking>
     */
    private function createBookings(array $users, array $apartments): array
    {
        $bookings = [];

        [$total, $commission, $payout] = $this->pricing($apartments['kemang']->base_price, 2);
        $bookings['apartment_pending_payment'] = $this->makeBooking([
            'user_id' => $users[3]->id, 'bookable_type' => Apartment::class, 'bookable_id' => $apartments['kemang']->id,
            'check_in_date' => now()->addDays(16), 'check_out_date' => now()->addDays(18), 'guest_count' => 2,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'pending_payment',
        ]);

        [$total, $commission, $payout] = $this->pricing($apartments['kemang']->base_price, 3);
        $booking = $this->makeBooking([
            'user_id' => $users[7]->id, 'bookable_type' => Apartment::class, 'bookable_id' => $apartments['kemang']->id,
            'check_in_date' => now()->addDays(5), 'check_out_date' => now()->addDays(8), 'guest_count' => 3,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dikonfirmasi', 'mitra_confirmed_at' => now()->subHours(3),
        ]);
        $this->makePayment($booking, 'success', now()->subHours(3));
        $bookings['apartment_confirmed_recent'] = $booking;

        [$total, $commission, $payout] = $this->pricing($apartments['sudirman']->base_price, 2);
        $booking = $this->makeBooking([
            'user_id' => $users[0]->id, 'bookable_type' => Apartment::class, 'bookable_id' => $apartments['sudirman']->id,
            'check_in_date' => now()->addDays(3), 'check_out_date' => now()->addDays(5), 'guest_count' => 2,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dikonfirmasi', 'mitra_confirmed_at' => now()->subDay(),
        ]);
        $this->makePayment($booking, 'success', now()->subDays(2));
        $bookings['apartment_confirmed'] = $booking;

        [$total, $commission, $payout] = $this->pricing($apartments['gubeng']->base_price, 2);
        $booking = $this->makeBooking([
            'user_id' => $users[5]->id, 'bookable_type' => Apartment::class, 'bookable_id' => $apartments['gubeng']->id,
            'check_in_date' => now()->subDays(8), 'check_out_date' => now()->subDays(6), 'guest_count' => 2,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'selesai', 'mitra_confirmed_at' => now()->subDays(10),
        ]);
        $this->makePayment($booking, 'success', now()->subDays(11));
        $bookings['apartment_done'] = $booking;

        [$total, $commission, $payout] = $this->pricing($apartments['sudirman']->base_price, 2);
        $refundAmount = (int) round($total * 0.85);
        $booking = $this->makeBooking([
            'user_id' => $users[7]->id, 'bookable_type' => Apartment::class, 'bookable_id' => $apartments['sudirman']->id,
            'check_in_date' => now()->addDays(12), 'check_out_date' => now()->addDays(14), 'guest_count' => 2,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dibatalkan_user', 'mitra_confirmed_at' => now()->subDays(1),
            'cancellation_reason' => 'user_cancel_confirmed', 'cancelled_at' => now()->subHours(4),
            'refund_amount' => $refundAmount, 'refund_percentage' => 85,
        ]);
        $payment = $this->makePayment($booking, 'refunded', now()->subDays(2));
        \App\Models\Refund::create(['booking_id' => $booking->id, 'payment_id' => $payment->id, 'amount' => $refundAmount, 'percentage' => 85, 'reason' => 'user_cancel_confirmed', 'xendit_refund_id' => 'demo_rfd_'.Str::random(10), 'status' => 'succeeded', 'processed_at' => now()->subHours(3)]);
        $bookings['apartment_cancelled_user'] = $booking;

        return $bookings;
    }

    private function createReview(array $bookings): void
    {
        $booking = $bookings['apartment_done'];

        Review::create([
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'reviewable_type' => $booking->bookable_type,
            'reviewable_id' => $booking->bookable_id,
            'rating' => 4,
            'comment' => 'Unitnya bersih dan lokasinya strategis dekat pusat kota, cocok untuk sewa harian.',
            'mitra_reply' => 'Terima kasih atas ulasannya, sampai jumpa lagi!',
            'mitra_replied_at' => now()->subDays(2),
        ]);
    }

    private function createPayout(MitraProfile $mitra, array $bookings): void
    {
        $booking = $bookings['apartment_done'];

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
