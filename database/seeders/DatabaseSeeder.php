<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            DistrictSeeder::class,
            SubDistrictSeeder::class,
            UserSeeder::class,
            WasteBankSeeder::class,
            WasteItemSeeder::class,
        ]);
        // Contoh: $this->call(AnotherSeeder::class);
    }
}
