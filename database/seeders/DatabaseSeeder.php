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
            RoleSeeder::class,
            DistrictSeeder::class,
            SubDistrictSeeder::class,
            UserSeeder::class,
            WasteBankSeeder::class,
            WasteItemSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoUserSeeder::class);
        }
        // Contoh: $this->call(AnotherSeeder::class);
    }
}
