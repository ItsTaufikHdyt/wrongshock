<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WasteBank;
use Illuminate\Database\Seeder;

class WasteBankSeeder extends Seeder
{
    public function run(): void
    {
        $bank = WasteBank::query()->firstOrCreate(
            ['code' => 'BS001'],
            ['name' => 'Wrongshock Bank Sampah Utama', 'status' => true]
        );

        User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->get()
            ->each(fn (User $admin) => $admin->wasteBanks()->syncWithoutDetaching([$bank->id]));
    }
}
