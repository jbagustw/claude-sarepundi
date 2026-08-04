<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Banner;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Facility;
use App\Models\GatheringVenue;
use App\Models\GatheringVenueSlot;
use App\Models\Homestay;
use App\Models\MitraProfile;
use App\Models\Notification;
use App\Models\Payout;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Transport;
use App\Models\User;
use App\Models\Villa;
use Database\Seeders\Concerns\DemoSeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Populates the platform with realistic, interconnected demo data across
 * every feature — mitras/listings in all 6 categories, bookings covering
 * every lifecycle status, reviews, payouts, articles, coupons, and
 * banners — so the app can be demoed (e.g. to investors) without an empty
 * "day one" look.
 *
 * NOT included in DatabaseSeeder's default run() — this is opt-in, run
 * explicitly with:
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * All demo accounts use the password "password".
 */
class DemoDataSeeder extends Seeder
{
    use DemoSeederHelpers;

    public function run(): void
    {
        $this->downloadImagePool();

        $admin = User::where('email', 'admin@bookingvilla.test')->first();

        $users = $this->demoUsers();
        $mitras = $this->createMitras($admin);

        $villas = $this->createVillas($mitras, $admin);
        $homestays = $this->createHomestays($mitras, $admin);
        $gatheringVenues = $this->createGatheringVenues($mitras, $admin);
        $transports = $this->createTransports($mitras, $admin);

        $bookings = $this->createBookings($users, $villas, $homestays, $gatheringVenues, $transports);

        $this->createReviews($bookings);
        $this->createPayouts($mitras, $bookings);
        $this->createNotifications($users, $mitras);
        $this->createArticles($admin);
        $this->createCoupons();
        $this->createBanners();

        // Glamping and Apartment each own their mitra/listings/bookings —
        // delegated to standalone seeders (see their docblocks) so they can
        // also be run individually to top up a server that already has the
        // rest of this demo dataset (or real data) without touching it.
        $this->call([GlampingDemoSeeder::class, ApartmentDemoSeeder::class]);

        $this->command?->info('Demo data seeded. All demo accounts use password "password".');
    }

    // --- Users & mitras ---------------------------------------------------

    /** @return array<string, MitraProfile> keyed by a short handle used elsewhere in this seeder */
    private function createMitras(User $admin): array
    {
        $roster = [
            'bali' => ['business_name' => 'Bali Escape Retreats', 'email' => 'mitra1@sarepundi.demo'],
            'jogja' => ['business_name' => 'Jogja Homestay Nusantara', 'email' => 'mitra2@sarepundi.demo'],
            'events' => ['business_name' => 'Grand Events Hall', 'email' => 'mitra3@sarepundi.demo'],
            'transport' => ['business_name' => 'Nusantara Transport', 'email' => 'mitra4@sarepundi.demo'],
            'lombok' => ['business_name' => 'Lombok Paradise Stays', 'email' => 'mitra5@sarepundi.demo'],
            // 'jatim' (Glamping) and 'jakarta' (Apartment) mitras are owned
            // by GlampingDemoSeeder / ApartmentDemoSeeder respectively —
            // see the call in run().
        ];

        $mitras = [];

        foreach ($roster as $handle => $info) {
            $user = User::firstOrCreate(
                ['email' => $info['email']],
                [
                    'name' => $info['business_name'].' (Pemilik)',
                    'password' => bcrypt('password'),
                    'phone' => '0812'.rand(10000000, 99999999),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            if (! $user->hasRole('mitra')) {
                $user->assignRole('mitra');
            }

            $mitras[$handle] = MitraProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => $info['business_name'],
                    'business_address' => 'Indonesia',
                    'bank_name' => 'BCA',
                    'bank_account' => (string) rand(1000000000, 9999999999),
                    'status' => 'approved',
                    'approved_by' => $admin->id,
                    'approved_at' => now()->subDays(30),
                ]
            );
        }

        // One pending mitra, deliberately left unapproved to demo the
        // admin approval queue.
        $pendingUser = User::firstOrCreate(
            ['email' => 'mitra6-pending@sarepundi.demo'],
            [
                'name' => 'Sumatra Adventure Co (Pemilik)',
                'password' => bcrypt('password'),
                'phone' => '0812'.rand(10000000, 99999999),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        if (! $pendingUser->hasRole('mitra')) {
            $pendingUser->assignRole('mitra');
        }
        MitraProfile::firstOrCreate(
            ['user_id' => $pendingUser->id],
            ['business_name' => 'Sumatra Adventure Co', 'business_address' => 'Indonesia', 'status' => 'pending']
        );

        return $mitras;
    }

    // --- Listings -----------------------------------------------------

    /** @return array<string, Villa> */
    private function createVillas(array $mitras, User $admin): array
    {
        $facilityIds = Facility::pluck('id', 'name');
        $villas = [];

        $roster = [
            'seminyak' => ['mitra' => 'bali', 'name' => 'Villa Sunset Seminyak', 'city' => 'Bali', 'province' => 'Bali', 'price' => 1500000, 'status' => 'published'],
            'ubud' => ['mitra' => 'bali', 'name' => 'Villa Ubud Hijau', 'city' => 'Bali', 'province' => 'Bali', 'price' => 1200000, 'status' => 'published'],
            'canggu' => ['mitra' => 'bali', 'name' => 'Villa Canggu Breeze', 'city' => 'Bali', 'province' => 'Bali', 'price' => 1350000, 'status' => 'published'],
            'nusa_dua' => ['mitra' => 'bali', 'name' => 'Villa Nusa Dua Elite', 'city' => 'Bali', 'province' => 'Bali', 'price' => 2200000, 'status' => 'pending_review'],
            'senggigi' => ['mitra' => 'lombok', 'name' => 'Villa Senggigi Sunset', 'city' => 'Lombok', 'province' => 'Nusa Tenggara Barat', 'price' => 1100000, 'status' => 'published'],
            'gili_air' => ['mitra' => 'lombok', 'name' => 'Villa Gili Air Escape', 'city' => 'Lombok', 'province' => 'Nusa Tenggara Barat', 'price' => 950000, 'status' => 'published'],
        ];

        foreach ($roster as $handle => $info) {
            $villa = $mitras[$info['mitra']]->villas()->create([
                'name' => $info['name'],
                'slug' => Str::slug($info['name']),
                'description' => "{$info['name']} menawarkan pengalaman menginap nyaman dengan pemandangan indah di {$info['city']}, cocok untuk liburan keluarga maupun bersama teman.",
                'address' => "Jl. Raya {$info['city']} No. ".rand(1, 99),
                'city' => $info['city'],
                'province' => $info['province'],
                'capacity_guest' => rand(4, 10),
                'bedroom_count' => rand(2, 5),
                'bathroom_count' => rand(2, 4),
                'base_price' => $info['price'],
                'status' => $info['status'],
                'reviewed_by' => $info['status'] === 'published' ? $admin->id : null,
                'reviewed_at' => $info['status'] === 'published' ? now()->subDays(25) : null,
            ]);

            $this->attachFacilities($villa, $facilityIds);
            $this->attachImages($villa, 'villa-images', 3);

            $villas[$handle] = $villa;
        }

        return $villas;
    }

    /** @return array<string, Homestay> */
    private function createHomestays(array $mitras, User $admin): array
    {
        $facilityIds = Facility::pluck('id', 'name');
        $homestays = [];

        $roster = [
            'malioboro' => ['mitra' => 'jogja', 'name' => 'Homestay Malioboro Heritage', 'city' => 'Yogyakarta', 'price' => 450000],
            'prawirotaman' => ['mitra' => 'jogja', 'name' => 'Homestay Prawirotaman Cozy', 'city' => 'Yogyakarta', 'price' => 350000],
            'kaliurang' => ['mitra' => 'jogja', 'name' => 'Homestay Kaliurang View', 'city' => 'Yogyakarta', 'price' => 400000],
            'kuta_lombok' => ['mitra' => 'lombok', 'name' => 'Homestay Kuta Lombok', 'city' => 'Lombok', 'price' => 380000],
            'mataram' => ['mitra' => 'lombok', 'name' => 'Homestay Mataram Simple', 'city' => 'Lombok', 'price' => 250000],
        ];

        foreach ($roster as $handle => $info) {
            $homestay = $mitras[$info['mitra']]->homestays()->create([
                'name' => $info['name'],
                'slug' => Str::slug($info['name']),
                'description' => "{$info['name']} adalah pilihan menginap hemat dan nyaman di {$info['city']}, dekat dengan tempat wisata populer.",
                'address' => "Jl. {$info['city']} No. ".rand(1, 99),
                'city' => $info['city'],
                'province' => $info['city'] === 'Yogyakarta' ? 'DI Yogyakarta' : 'Nusa Tenggara Barat',
                'capacity_guest' => rand(2, 6),
                'bedroom_count' => rand(1, 3),
                'bathroom_count' => rand(1, 2),
                'base_price' => $info['price'],
                'status' => 'published',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(20),
            ]);

            $this->attachFacilities($homestay, $facilityIds);
            $this->attachImages($homestay, 'homestay-images', 2);

            $homestays[$handle] = $homestay;
        }

        return $homestays;
    }

    /** @return array<string, GatheringVenue> */
    private function createGatheringVenues(array $mitras, User $admin): array
    {
        $facilityIds = Facility::pluck('id', 'name');
        $venues = [];

        $ballroom = $mitras['events']->gatheringVenues()->create([
            'name' => 'Aula Grand Ballroom Jakarta',
            'slug' => Str::slug('Aula Grand Ballroom Jakarta'),
            'description' => 'Aula serbaguna luas untuk seminar, gathering perusahaan, resepsi, dan acara komunitas hingga 200 orang.',
            'address' => 'Jl. Sudirman No. 45',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'capacity' => 200,
            'status' => 'published',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now()->subDays(18),
        ]);
        $this->attachFacilities($ballroom, $facilityIds);
        $this->attachImages($ballroom, 'gathering-venue-images', 3);
        $ballroom->slots()->createMany([
            ['name' => 'Sesi Pagi', 'start_time' => '08:00', 'end_time' => '12:00', 'price' => 2500000, 'is_active' => true],
            ['name' => 'Sesi Siang', 'start_time' => '13:00', 'end_time' => '17:00', 'price' => 2800000, 'is_active' => true],
            ['name' => 'Sesi Malam', 'start_time' => '18:00', 'end_time' => '22:00', 'price' => 3200000, 'is_active' => true],
        ]);
        $venues['ballroom'] = $ballroom;

        $meetingRoom = $mitras['events']->gatheringVenues()->create([
            'name' => 'Ruang Meeting Eksekutif',
            'slug' => Str::slug('Ruang Meeting Eksekutif'),
            'description' => 'Ruang meeting representatif untuk rapat, workshop, dan pelatihan perusahaan kapasitas hingga 40 orang.',
            'address' => 'Jl. Gatot Subroto No. 12',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'capacity' => 40,
            'status' => 'published',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now()->subDays(15),
        ]);
        $this->attachFacilities($meetingRoom, $facilityIds);
        $this->attachImages($meetingRoom, 'gathering-venue-images', 2);
        $meetingRoom->slots()->createMany([
            ['name' => 'Half Day', 'start_time' => '08:00', 'end_time' => '13:00', 'price' => 800000, 'is_active' => true],
            ['name' => 'Full Day', 'start_time' => '08:00', 'end_time' => '17:00', 'price' => 1400000, 'is_active' => true],
        ]);
        $venues['meeting_room'] = $meetingRoom;

        return $venues;
    }

    /** @return array<string, Transport> */
    private function createTransports(array $mitras, User $admin): array
    {
        $transports = [];

        $roster = [
            'hiace' => ['name' => 'Toyota Hiace Commuter', 'capacity' => 15, 'self_drive' => 600000, 'with_driver' => 900000],
            'alphard' => ['name' => 'Toyota Alphard Executive', 'capacity' => 6, 'self_drive' => null, 'with_driver' => 1500000],
            'xenia' => ['name' => 'Daihatsu Xenia', 'capacity' => 6, 'self_drive' => 350000, 'with_driver' => null],
        ];

        foreach ($roster as $handle => $info) {
            $transport = $mitras['transport']->transports()->create([
                'name' => $info['name'],
                'slug' => Str::slug($info['name']),
                'vehicle_type' => $info['capacity'] > 10 ? 'Minibus' : 'MPV',
                'description' => "{$info['name']} dalam kondisi prima, siap untuk perjalanan wisata maupun bisnis di dalam dan luar kota.",
                'capacity' => $info['capacity'],
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'price_per_day_self_drive' => $info['self_drive'],
                'price_per_day_with_driver' => $info['with_driver'],
                'status' => 'published',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(12),
            ]);

            $this->attachImages($transport, 'transport-images', 2);

            $transports[$handle] = $transport;
        }

        return $transports;
    }

    // --- Bookings -----------------------------------------------------

    /** @return array<int, Booking> */
    private function createBookings(array $users, array $villas, array $homestays, array $gatheringVenues, array $transports): array
    {
        $bookings = [];

        // --- Villa bookings (mostly Bali Escape Retreats) ---
        [$total, $commission, $payout] = $this->pricing($villas['seminyak']->base_price, 3);
        $bookings['villa_pending_payment'] = $this->makeBooking([
            'user_id' => $users[0]->id, 'bookable_type' => Villa::class, 'bookable_id' => $villas['seminyak']->id,
            'check_in_date' => now()->addDays(20), 'check_out_date' => now()->addDays(23), 'guest_count' => 4,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'pending_payment',
        ]);

        [$total, $commission, $payout] = $this->pricing($villas['seminyak']->base_price, 4);
        $booking = $this->makeBooking([
            'user_id' => $users[1]->id, 'bookable_type' => Villa::class, 'bookable_id' => $villas['seminyak']->id,
            'check_in_date' => now()->addDays(15), 'check_out_date' => now()->addDays(19), 'guest_count' => 5,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dikonfirmasi', 'mitra_confirmed_at' => now()->subHours(4),
        ]);
        $this->makePayment($booking, 'success', now()->subHours(4));
        $bookings['villa_confirmed_recent'] = $booking;

        [$total, $commission, $payout] = $this->pricing($villas['ubud']->base_price, 3);
        $booking = $this->makeBooking([
            'user_id' => $users[2]->id, 'bookable_type' => Villa::class, 'bookable_id' => $villas['ubud']->id,
            'check_in_date' => now()->addDays(10), 'check_out_date' => now()->addDays(13), 'guest_count' => 3,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dikonfirmasi', 'mitra_confirmed_at' => now()->subDay(),
        ]);
        $this->makePayment($booking, 'success', now()->subDays(2));
        $bookings['villa_confirmed'] = $booking;

        [$total, $commission, $payout] = $this->pricing($villas['canggu']->base_price, 3);
        $booking = $this->makeBooking([
            'user_id' => $users[3]->id, 'bookable_type' => Villa::class, 'bookable_id' => $villas['canggu']->id,
            'check_in_date' => now()->subDay(), 'check_out_date' => now()->addDays(2), 'guest_count' => 4,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'checked_in', 'mitra_confirmed_at' => now()->subDays(3),
        ]);
        $this->makePayment($booking, 'success', now()->subDays(4));
        $bookings['villa_checked_in'] = $booking;

        [$total, $commission, $payout] = $this->pricing($villas['seminyak']->base_price, 3);
        $booking = $this->makeBooking([
            'user_id' => $users[0]->id, 'bookable_type' => Villa::class, 'bookable_id' => $villas['seminyak']->id,
            'check_in_date' => now()->subDays(20), 'check_out_date' => now()->subDays(17), 'guest_count' => 2,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'selesai', 'mitra_confirmed_at' => now()->subDays(22),
        ]);
        $this->makePayment($booking, 'success', now()->subDays(23));
        $bookings['villa_done_1'] = $booking;

        [$total, $commission, $payout] = $this->pricing($villas['ubud']->base_price, 2);
        $booking = $this->makeBooking([
            'user_id' => $users[1]->id, 'bookable_type' => Villa::class, 'bookable_id' => $villas['ubud']->id,
            'check_in_date' => now()->subDays(10), 'check_out_date' => now()->subDays(8), 'guest_count' => 2,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'selesai', 'mitra_confirmed_at' => now()->subDays(12),
        ]);
        $this->makePayment($booking, 'success', now()->subDays(13));
        $bookings['villa_done_2'] = $booking;

        [$total, $commission, $payout] = $this->pricing($villas['canggu']->base_price, 4);
        $refundAmount = (int) round($total * 0.85);
        $booking = $this->makeBooking([
            'user_id' => $users[2]->id, 'bookable_type' => Villa::class, 'bookable_id' => $villas['canggu']->id,
            'check_in_date' => now()->addDays(6), 'check_out_date' => now()->addDays(10), 'guest_count' => 3,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dibatalkan_user', 'mitra_confirmed_at' => now()->subDays(2),
            'cancellation_reason' => 'user_cancel_confirmed', 'cancelled_at' => now()->subDay(),
            'refund_amount' => $refundAmount, 'refund_percentage' => 85,
        ]);
        $payment = $this->makePayment($booking, 'refunded', now()->subDays(3));
        Refund::create(['booking_id' => $booking->id, 'payment_id' => $payment->id, 'amount' => $refundAmount, 'percentage' => 85, 'reason' => 'user_cancel_confirmed', 'xendit_refund_id' => 'demo_rfd_'.Str::random(10), 'status' => 'succeeded', 'processed_at' => now()->subDay()]);
        $bookings['villa_cancelled_user'] = $booking;

        // --- Homestay bookings (Jogja Homestay Nusantara) ---
        [$total, $commission, $payout] = $this->pricing($homestays['malioboro']->base_price, 2);
        $bookings['homestay_pending_payment'] = $this->makeBooking([
            'user_id' => $users[4]->id, 'bookable_type' => Homestay::class, 'bookable_id' => $homestays['malioboro']->id,
            'check_in_date' => now()->addDays(14), 'check_out_date' => now()->addDays(16), 'guest_count' => 2,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'pending_payment',
        ]);

        [$total, $commission, $payout] = $this->pricing($homestays['malioboro']->base_price, 3);
        $booking = $this->makeBooking([
            'user_id' => $users[5]->id, 'bookable_type' => Homestay::class, 'bookable_id' => $homestays['malioboro']->id,
            'check_in_date' => now()->addDays(9), 'check_out_date' => now()->addDays(12), 'guest_count' => 3,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dikonfirmasi', 'mitra_confirmed_at' => now()->subHours(6),
        ]);
        $this->makePayment($booking, 'success', now()->subHours(6));
        $bookings['homestay_confirmed_recent'] = $booking;

        [$total, $commission, $payout] = $this->pricing($homestays['prawirotaman']->base_price, 2);
        $booking = $this->makeBooking([
            'user_id' => $users[6]->id, 'bookable_type' => Homestay::class, 'bookable_id' => $homestays['prawirotaman']->id,
            'check_in_date' => now()->addDays(7), 'check_out_date' => now()->addDays(9), 'guest_count' => 2,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dikonfirmasi', 'mitra_confirmed_at' => now()->subDays(1),
        ]);
        $this->makePayment($booking, 'success', now()->subDays(2));
        $bookings['homestay_confirmed'] = $booking;

        [$total, $commission, $payout] = $this->pricing($homestays['kaliurang']->base_price, 2);
        $booking = $this->makeBooking([
            'user_id' => $users[4]->id, 'bookable_type' => Homestay::class, 'bookable_id' => $homestays['kaliurang']->id,
            'check_in_date' => now()->subDays(15), 'check_out_date' => now()->subDays(13), 'guest_count' => 2,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'selesai', 'mitra_confirmed_at' => now()->subDays(17),
        ]);
        $this->makePayment($booking, 'success', now()->subDays(18));
        $bookings['homestay_done'] = $booking;

        [$total, $commission, $payout] = $this->pricing($homestays['prawirotaman']->base_price, 2);
        $refundAmount = (int) round($total * 0.85);
        $booking = $this->makeBooking([
            'user_id' => $users[5]->id, 'bookable_type' => Homestay::class, 'bookable_id' => $homestays['prawirotaman']->id,
            'check_in_date' => now()->addDays(11), 'check_out_date' => now()->addDays(13), 'guest_count' => 2,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dibatalkan_user', 'cancellation_reason' => 'user_cancel_confirmed', 'cancelled_at' => now()->subHours(3),
            'refund_amount' => $refundAmount, 'refund_percentage' => 85,
        ]);
        $payment = $this->makePayment($booking, 'refunded', now()->subDays(1));
        Refund::create(['booking_id' => $booking->id, 'payment_id' => $payment->id, 'amount' => $refundAmount, 'percentage' => 85, 'reason' => 'user_cancel_confirmed', 'xendit_refund_id' => 'demo_rfd_'.Str::random(10), 'status' => 'succeeded', 'processed_at' => now()->subHours(2)]);
        $bookings['homestay_cancelled_user'] = $booking;

        // --- Gathering venue bookings (slot-based) ---
        $morningSlot = $gatheringVenues['ballroom']->slots()->where('name', 'Sesi Pagi')->first();
        $afternoonSlot = $gatheringVenues['ballroom']->slots()->where('name', 'Sesi Siang')->first();
        $eveningSlot = $gatheringVenues['ballroom']->slots()->where('name', 'Sesi Malam')->first();
        $halfDaySlot = $gatheringVenues['meeting_room']->slots()->where('name', 'Half Day')->first();
        $fullDaySlot = $gatheringVenues['meeting_room']->slots()->where('name', 'Full Day')->first();

        [$total, $commission, $payout] = $this->pricing($morningSlot->price, 1);
        $booking = $this->makeBooking([
            'user_id' => $users[7]->id, 'bookable_type' => GatheringVenue::class, 'bookable_id' => $gatheringVenues['ballroom']->id,
            'gathering_venue_slot_id' => $morningSlot->id,
            'check_in_date' => now()->addDays(25), 'check_out_date' => now()->addDays(25), 'guest_count' => 150,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dikonfirmasi', 'mitra_confirmed_at' => now()->subHours(2),
        ]);
        $this->makePayment($booking, 'success', now()->subHours(2));
        $bookings['gathering_confirmed_recent'] = $booking;

        [$total, $commission, $payout] = $this->pricing($eveningSlot->price, 1);
        $booking = $this->makeBooking([
            'user_id' => $users[0]->id, 'bookable_type' => GatheringVenue::class, 'bookable_id' => $gatheringVenues['ballroom']->id,
            'gathering_venue_slot_id' => $eveningSlot->id,
            'check_in_date' => now()->addDays(30), 'check_out_date' => now()->addDays(30), 'guest_count' => 180,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dikonfirmasi', 'mitra_confirmed_at' => now()->subDays(1),
        ]);
        $this->makePayment($booking, 'success', now()->subDays(2));
        $bookings['gathering_confirmed'] = $booking;

        [$total, $commission, $payout] = $this->pricing($halfDaySlot->price, 1);
        $booking = $this->makeBooking([
            'user_id' => $users[1]->id, 'bookable_type' => GatheringVenue::class, 'bookable_id' => $gatheringVenues['meeting_room']->id,
            'gathering_venue_slot_id' => $halfDaySlot->id,
            'check_in_date' => now()->subDays(15), 'check_out_date' => now()->subDays(15), 'guest_count' => 30,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'selesai', 'mitra_confirmed_at' => now()->subDays(17),
        ]);
        $this->makePayment($booking, 'success', now()->subDays(18));
        $bookings['gathering_done'] = $booking;

        [$total, $commission, $payout] = $this->pricing($fullDaySlot->price, 1);
        $bookings['gathering_pending_payment'] = $this->makeBooking([
            'user_id' => $users[3]->id, 'bookable_type' => GatheringVenue::class, 'bookable_id' => $gatheringVenues['meeting_room']->id,
            'gathering_venue_slot_id' => $fullDaySlot->id,
            'check_in_date' => now()->addDays(8), 'check_out_date' => now()->addDays(8), 'guest_count' => 35,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'pending_payment',
        ]);

        // --- Transport bookings ---
        [$total, $commission, $payout] = $this->pricing($transports['hiace']->price_per_day_self_drive, 2);
        $bookings['transport_pending_payment'] = $this->makeBooking([
            'user_id' => $users[4]->id, 'bookable_type' => Transport::class, 'bookable_id' => $transports['hiace']->id,
            'transport_with_driver' => false,
            'check_in_date' => now()->addDays(12), 'check_out_date' => now()->addDays(14), 'guest_count' => 10,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'pending_payment',
        ]);

        [$total, $commission, $payout] = $this->pricing($transports['alphard']->price_per_day_with_driver, 3);
        $booking = $this->makeBooking([
            'user_id' => $users[5]->id, 'bookable_type' => Transport::class, 'bookable_id' => $transports['alphard']->id,
            'transport_with_driver' => true,
            'check_in_date' => now()->addDays(8), 'check_out_date' => now()->addDays(11), 'guest_count' => 5,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dikonfirmasi', 'mitra_confirmed_at' => now()->subHours(8),
        ]);
        $this->makePayment($booking, 'success', now()->subHours(8));
        $bookings['transport_confirmed_recent'] = $booking;

        [$total, $commission, $payout] = $this->pricing($transports['xenia']->price_per_day_self_drive, 4);
        $booking = $this->makeBooking([
            'user_id' => $users[6]->id, 'bookable_type' => Transport::class, 'bookable_id' => $transports['xenia']->id,
            'transport_with_driver' => false,
            'check_in_date' => now()->addDays(4), 'check_out_date' => now()->addDays(8), 'guest_count' => 4,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dikonfirmasi', 'mitra_confirmed_at' => now()->subHours(12),
        ]);
        $this->makePayment($booking, 'success', now()->subDay());
        $bookings['transport_confirmed'] = $booking;

        [$total, $commission, $payout] = $this->pricing($transports['hiace']->price_per_day_with_driver, 2);
        $booking = $this->makeBooking([
            'user_id' => $users[7]->id, 'bookable_type' => Transport::class, 'bookable_id' => $transports['hiace']->id,
            'transport_with_driver' => true,
            'check_in_date' => now()->subDays(9), 'check_out_date' => now()->subDays(7), 'guest_count' => 12,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'selesai', 'mitra_confirmed_at' => now()->subDays(11),
        ]);
        $this->makePayment($booking, 'success', now()->subDays(12));
        $bookings['transport_done'] = $booking;

        [$total, $commission, $payout] = $this->pricing($transports['alphard']->price_per_day_with_driver, 2);
        $booking = $this->makeBooking([
            'user_id' => $users[4]->id, 'bookable_type' => Transport::class, 'bookable_id' => $transports['alphard']->id,
            'transport_with_driver' => true,
            'check_in_date' => now()->addDay(), 'check_out_date' => now()->addDays(3), 'guest_count' => 4,
            'total_price' => $total, 'commission_amount' => $commission, 'mitra_payout_amount' => $payout,
            'status' => 'dibatalkan_user', 'mitra_confirmed_at' => now()->subDays(4),
            'cancellation_reason' => 'user_cancel_confirmed', 'cancelled_at' => now()->subHours(10),
            'refund_amount' => 0, 'refund_percentage' => 0,
        ]);
        // Nothing owed back within H-2 of check-in — no Refund record, no Xendit call (mirrors BookingCancellationService behaviour).
        $this->makePayment($booking, 'success', now()->subDays(5));
        $bookings['transport_cancelled_user'] = $booking;

        return $bookings;
    }

    // --- Reviews, payouts, notifications -------------------------------

    private function createReviews(array $bookings): void
    {
        $reviewed = [
            'villa_done_1' => ['rating' => 5, 'comment' => 'Villa sangat bersih dan pemandangan sunset-nya luar biasa! Pasti balik lagi.', 'reply' => 'Terima kasih banyak sudah menginap bersama kami!'],
            'villa_done_2' => ['rating' => 4, 'comment' => 'Nyaman dan tenang, cocok untuk healing. Hanya wifi agak lambat.', 'reply' => null],
            'homestay_done' => ['rating' => 5, 'comment' => 'Homestay-nya homey banget, tuan rumah ramah dan lokasinya strategis.', 'reply' => 'Senang kamu betah, ditunggu kunjungan berikutnya ya!'],
            'gathering_done' => ['rating' => 4, 'comment' => 'Ruangannya representatif untuk meeting, fasilitas lengkap.', 'reply' => null],
            'transport_done' => ['rating' => 5, 'comment' => 'Mobil bersih, sopir tepat waktu dan sangat membantu selama perjalanan.', 'reply' => 'Terima kasih atas kepercayaannya!'],
        ];

        foreach ($reviewed as $key => $info) {
            $booking = $bookings[$key];
            Review::create([
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'reviewable_type' => $booking->bookable_type,
                'reviewable_id' => $booking->bookable_id,
                'rating' => $info['rating'],
                'comment' => $info['comment'],
                'mitra_reply' => $info['reply'],
                'mitra_replied_at' => $info['reply'] ? now()->subDays(rand(1, 5)) : null,
            ]);
        }
    }

    private function createPayouts(array $mitras, array $bookings): void
    {
        $groups = [
            'bali' => ['villa_done_1', 'villa_done_2'],
            'jogja' => ['homestay_done'],
            'events' => ['gathering_done'],
            'transport' => ['transport_done'],
        ];

        foreach ($groups as $mitraHandle => $bookingKeys) {
            $completedBookings = collect($bookingKeys)->map(fn ($key) => $bookings[$key]);
            $amount = (int) $completedBookings->sum('mitra_payout_amount');

            $payout = Payout::create([
                'mitra_id' => $mitras[$mitraHandle]->id,
                'amount' => $amount,
                'period_start' => now()->subDays(30)->toDateString(),
                'period_end' => now()->subDays(1)->toDateString(),
                'xendit_disbursement_id' => 'demo_disb_'.Str::random(12),
                'status' => 'completed',
                'processed_at' => now()->subDays(2),
            ]);

            foreach ($completedBookings as $booking) {
                $booking->update(['payout_id' => $payout->id]);
            }
        }
    }

    private function createNotifications(array $users, array $mitras): void
    {
        Notification::create(['user_id' => $users[1]->id, 'type' => 'payment_success', 'title' => 'Pembayaran berhasil', 'message' => 'Pembayaran untuk booking Villa Sunset Seminyak berhasil dan booking kamu sudah dikonfirmasi.', 'is_read' => false]);
        Notification::create(['user_id' => $users[2]->id, 'type' => 'booking_confirmed', 'title' => 'Booking dikonfirmasi', 'message' => 'Booking kamu di Villa Ubud Hijau sudah dikonfirmasi.', 'is_read' => false]);
        Notification::create(['user_id' => $users[0]->id, 'type' => 'booking_completed', 'title' => 'Terima kasih sudah menginap!', 'message' => 'Booking kamu di Villa Sunset Seminyak sudah selesai. Yuk beri review!', 'is_read' => true]);

        $baliUser = $mitras['bali']->user;
        Notification::create(['user_id' => $baliUser->id, 'type' => 'booking_confirmed', 'title' => 'Booking baru dikonfirmasi', 'message' => 'Ada booking baru untuk Villa Sunset Seminyak yang sudah dibayar dan otomatis dikonfirmasi.', 'is_read' => false]);
        Notification::create(['user_id' => $baliUser->id, 'type' => 'new_review', 'title' => 'Review baru untuk villa kamu', 'message' => 'Dewi Anggraini memberi rating 5/5 untuk Villa Sunset Seminyak.', 'is_read' => false]);
    }

    private function createArticles(User $admin): void
    {
        $articles = [
            ['title' => '5 Villa Terbaik di Bali untuk Liburan Keluarga', 'category' => 'Tips Wisata', 'excerpt' => 'Rekomendasi villa nyaman dan strategis untuk liburan bersama keluarga besar di Bali.'],
            ['title' => 'Tips Hemat Menginap di Homestay Yogyakarta', 'category' => 'Tips Liburan', 'excerpt' => 'Cara mendapatkan homestay nyaman dengan budget terbatas saat liburan ke Jogja.'],
            ['title' => 'Panduan Menyewa Kendaraan untuk Trip Keluarga', 'category' => 'Panduan Traveling', 'excerpt' => 'Hal-hal yang perlu diperhatikan sebelum menyewa mobil, lepas kunci atau dengan sopir.'],
            ['title' => 'Checklist Menyiapkan Acara Gathering Kantor', 'category' => 'Panduan Traveling', 'excerpt' => 'Langkah-langkah praktis merencanakan gathering perusahaan yang berkesan.'],
            ['title' => 'Promo Akhir Tahun: Diskon Villa & Homestay Pilihan', 'category' => 'Promo & Event', 'excerpt' => 'Kumpulan promo menarik untuk liburan akhir tahun bersama Sarepundi.'],
        ];

        foreach ($articles as $article) {
            Article::firstOrCreate(
                ['slug' => Str::slug($article['title'])],
                [
                    'author_id' => $admin->id,
                    'title' => $article['title'],
                    'category' => $article['category'],
                    'excerpt' => $article['excerpt'],
                    'content' => $article['excerpt']."\n\n".'Isi lengkap artikel akan segera hadir. Nantikan tips dan panduan traveling lainnya dari Sarepundi.',
                    'cover_image_path' => $this->storeImage('article-covers'),
                    'status' => 'published',
                    'published_at' => now()->subDays(rand(1, 20)),
                ]
            );
        }
    }

    private function createCoupons(): void
    {
        $coupons = [
            ['code' => 'HEMAT15', 'title' => 'Diskon 15% Booking Pertama', 'description' => 'Khusus pengguna baru, berlaku untuk semua kategori', 'discount_type' => 'percentage', 'discount_value' => 15, 'valid_until' => now()->addDays(60)->toDateString()],
            ['code' => 'GATHER10', 'title' => 'Diskon 10% Lokasi Gathering', 'description' => 'Berlaku untuk booking lokasi gathering & acara', 'discount_type' => 'percentage', 'discount_value' => 10, 'valid_until' => now()->addDays(45)->toDateString()],
            ['code' => 'TRANS50K', 'title' => 'Potongan Rp50.000 Sewa Kendaraan', 'description' => 'Minimum sewa 2 hari', 'discount_type' => 'fixed', 'discount_value' => 50000, 'valid_until' => null],
        ];

        foreach ($coupons as $index => $coupon) {
            Coupon::firstOrCreate(
                ['code' => $coupon['code']],
                array_merge($coupon, ['is_active' => true, 'sort_order' => $index])
            );
        }
    }

    private function createBanners(): void
    {
        $banners = [
            ['title' => 'Liburan ke Bali Mulai dari Sekarang', 'link_url' => '/villas'],
            ['title' => 'Sewa Kendaraan Nyaman untuk Perjalananmu', 'link_url' => '/transports'],
            ['title' => 'Lokasi Gathering Terbaik untuk Acaramu', 'link_url' => '/gathering-venues'],
        ];

        foreach ($banners as $index => $banner) {
            Banner::firstOrCreate(
                ['title' => $banner['title']],
                [
                    'link_url' => $banner['link_url'],
                    'image_path' => $this->storeImage('banners'),
                    'is_active' => true,
                    'sort_order' => $index,
                ]
            );
        }
    }
}
