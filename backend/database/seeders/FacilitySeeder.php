<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Seeder;

class FacilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $facilities = [
            ['name' => 'Kolam Renang', 'icon' => 'pool', 'category' => 'outdoor'],
            ['name' => 'AC', 'icon' => 'snowflake', 'category' => 'room'],
            ['name' => 'WiFi', 'icon' => 'wifi', 'category' => 'general'],
            ['name' => 'Dapur', 'icon' => 'utensils', 'category' => 'general'],
            ['name' => 'Parkir Mobil', 'icon' => 'car', 'category' => 'outdoor'],
            ['name' => 'TV', 'icon' => 'tv', 'category' => 'room'],
            ['name' => 'Water Heater', 'icon' => 'droplet', 'category' => 'room'],
            ['name' => 'BBQ Area', 'icon' => 'flame', 'category' => 'outdoor'],
            ['name' => 'Mesin Cuci', 'icon' => 'washing-machine', 'category' => 'general'],
            ['name' => 'Karaoke', 'icon' => 'mic', 'category' => 'entertainment'],
        ];

        foreach ($facilities as $facility) {
            Facility::firstOrCreate(['name' => $facility['name']], $facility);
        }
    }
}
