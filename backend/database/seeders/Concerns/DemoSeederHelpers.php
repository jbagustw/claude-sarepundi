<?php

namespace Database\Seeders\Concerns;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared building blocks for every demo/investor-facing seeder
 * (DemoDataSeeder plus the per-category top-up seeders like
 * GlampingDemoSeeder and ApartmentDemoSeeder). Keeping this in one place
 * means a category seeder can be run standalone against a server that
 * already has other demo — or real — data, without re-deriving image
 * download, pricing, or booking-creation logic.
 */
trait DemoSeederHelpers
{
    /** @var array<int, string> raw image bytes downloaded once and reused across listings */
    private array $imagePool = [];

    private int $poolCursor = 0;

    private function downloadImagePool(): void
    {
        foreach (range(1, 16) as $seed) {
            try {
                $response = Http::timeout(15)->get("https://picsum.photos/seed/sarepundi{$seed}/1000/700");
                if ($response->successful()) {
                    $this->imagePool[] = $response->body();
                }
            } catch (\Throwable $e) {
                // Network unavailable on this server — listings created
                // without images are still fully functional, just less
                // visually complete. Not worth failing the whole seed.
            }
        }

        if (empty($this->imagePool)) {
            $this->command?->warn('Tidak bisa mengunduh gambar demo (cek koneksi internet server). Listing akan dibuat tanpa foto.');
        }
    }

    private function nextImage(): ?string
    {
        if (empty($this->imagePool)) {
            return null;
        }

        $image = $this->imagePool[$this->poolCursor % count($this->imagePool)];
        $this->poolCursor++;

        return $image;
    }

    private function storeImage(string $directory): ?string
    {
        $bytes = $this->nextImage();
        if ($bytes === null) {
            return null;
        }

        $path = $directory.'/'.Str::random(20).'.jpg';
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    private function attachFacilities($listing, $facilityIds): void
    {
        $ids = collect($facilityIds)->values()->shuffle()->take(rand(3, 5))->all();
        $listing->facilities()->syncWithoutDetaching($ids);
    }

    private function attachImages($listing, string $directory, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $path = $this->storeImage($directory);
            if ($path === null) {
                continue;
            }
            $listing->images()->create([
                'image_url' => $path,
                'is_primary' => $i === 0,
                'sort_order' => $i,
            ]);
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int} [total, commission, mitra payout]
     */
    private function pricing(int $pricePerUnit, int $units): array
    {
        $total = $pricePerUnit * $units;
        $commission = (int) round($total * 0.1);

        return [$total, $commission, $total - $commission];
    }

    private function makeBooking(array $attributes): Booking
    {
        return Booking::create(array_merge([
            'booking_code' => 'BK'.strtoupper(Str::random(10)),
            // None of the demo bookings apply a coupon, so subtotal is
            // always the same as total_price unless a call site overrides it.
            'subtotal' => $attributes['total_price'],
        ], $attributes));
    }

    private function makePayment(Booking $booking, string $status, ?\DateTimeInterface $paidAt = null): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'xendit_invoice_id' => 'demo_inv_'.Str::random(12),
            'invoice_url' => null,
            'amount' => $booking->total_price,
            'status' => $status,
            'paid_at' => $paidAt,
        ]);
    }

    /**
     * The 8 standard demo guest accounts — firstOrCreate so calling this
     * from a standalone category seeder is safe whether or not
     * DemoDataSeeder has already created them.
     *
     * @return array<int, User>
     */
    private function demoUsers(): array
    {
        $names = [
            'Dewi Anggraini', 'Budi Santoso', 'Siti Rahma', 'Andi Prasetyo',
            'Rina Wijaya', 'Fajar Hidayat', 'Maya Kusuma', 'Rizky Ramadhan',
        ];

        return collect($names)->map(function (string $name, int $index) {
            $user = User::firstOrCreate(
                ['email' => 'user'.($index + 1).'@sarepundi.demo'],
                [
                    'name' => $name,
                    'password' => bcrypt('password'),
                    'phone' => '0812'.rand(10000000, 99999999),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            if (! $user->hasRole('user')) {
                $user->assignRole('user');
            }

            return $user;
        })->all();
    }

    private function demoAdmin(): User
    {
        return User::where('email', 'admin@bookingvilla.test')->firstOrFail();
    }
}
