<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\SubDistrict;
use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteBankMember;
use App\Services\AdminMembershipService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $bank = WasteBank::query()->where('code', 'BS001')->first();
        if (! $bank) {
            throw new RuntimeException('Demo users require the existing BS001 waste bank.');
        }

        $district = District::query()->orderBy('id')->first();
        $subDistrict = $district
            ? SubDistrict::query()->where('district_id', $district->id)->orderBy('id')->first()
            : null;

        if (! $district || ! $subDistrict) {
            throw new RuntimeException('Demo users require a seeded district and sub-district.');
        }

        $superAdmin = $this->user('superadmin@wrongshock.test', [
            'name' => 'Super Admin Wrongshock',
            'number' => 'DEMO-SUPER-ADMIN',
            'district_id' => $district->id,
            'sub_district_id' => $subDistrict->id,
            'address' => 'Demo platform account',
            'password' => Hash::make('password'),
        ], 'super_admin');
        $superAdmin->wasteBanksAsStaff()->detach();

        $bankAdmin = $this->user('admin@wrongshock.test', [
            'name' => 'Admin Bank Sampah',
            'number' => 'DEMO-BANK-ADMIN',
            'district_id' => $district->id,
            'sub_district_id' => $subDistrict->id,
            'address' => 'Demo bank admin account',
            'password' => Hash::make('password'),
        ], 'admin');
        if (! $bankAdmin->wasteBanksAsStaff()->whereKey($bank->id)->exists()) {
            app(AdminMembershipService::class)->assignBankAdmin($superAdmin, $bankAdmin, $bank);
        }

        $citizen = $this->user('user@wrongshock.test', [
            'name' => 'Anggota Demo',
            'number' => 'DEMO-MEMBER-001',
            'district_id' => $district->id,
            'sub_district_id' => $subDistrict->id,
            'address' => 'Demo member account',
            'password' => Hash::make('password'),
        ], 'user');
        $citizen->wasteBanksAsStaff()->detach();
        WasteBankMember::query()->firstOrCreate(
            ['waste_bank_id' => $bank->id, 'user_id' => $citizen->id],
            ['joined_at' => now(), 'status' => 'active'],
        );
    }

    /** @param array<string, mixed> $attributes */
    private function user(string $email, array $attributes, string $role): User
    {
        $user = User::query()->firstOrCreate(['email' => $email], [
            ...$attributes,
            'status' => 1,
            'balance' => 0,
        ]);

        $user->syncRoles(Role::findOrCreate($role, 'web'));

        return $user->refresh();
    }
}
