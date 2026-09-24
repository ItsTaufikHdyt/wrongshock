<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WasteBank;
use App\Services\AdminMembershipService;
use Illuminate\Database\Seeder;

class WasteBankSeeder extends Seeder
{
    public function run(): void
    {
        $bank = WasteBank::query()->firstOrCreate(
            ['code' => 'BS001'],
            ['name' => 'Wrongshock Bank Sampah Utama', 'status' => true]
        );

        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'super_admin'))->first();

        if ($actor) {
            User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'super_admin'))
                ->get()
                ->each(function (User $admin) use ($actor, $bank): void {
                    if (! $admin->wasteBanksAsStaff()->whereKey($bank->id)->exists()) {
                        app(AdminMembershipService::class)->assignBankAdmin($actor, $admin, $bank);
                    }
                });
        }
    }
}
