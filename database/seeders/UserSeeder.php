<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\SubDistrict;
use App\Models\User;
use App\Models\WasteBank;
use App\Services\AdminMembershipService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */
        Role::findOrCreate('super_admin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('user', 'web');

        /*
        |--------------------------------------------------------------------------
        | Waste Bank
        |--------------------------------------------------------------------------
        */
        $bank = WasteBank::query()
            ->where('code', 'BS001')
            ->first();

        if (! $bank) {
            throw new RuntimeException(
                'UserSeeder membutuhkan waste bank dengan code BS001.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | District & Sub District
        |--------------------------------------------------------------------------
        */
        $district = District::query()
            ->orderBy('id')
            ->first();

        if (! $district) {
            throw new RuntimeException(
                'UserSeeder membutuhkan data district.'
            );
        }

        $subDistricts = SubDistrict::query()
            ->where('district_id', $district->id)
            ->orderBy('id')
            ->get();

        $subDistrict = $subDistricts->first();

        if (! $subDistrict) {
            throw new RuntimeException(
                'UserSeeder membutuhkan data sub district.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */
        $superAdmin = $this->createUser(
            'superadmin@gmail.com',
            [
                'name' => 'Super Admin',
                'number' => '001010120251233',
                'district_id' => $district->id,
                'sub_district_id' => $subDistrict->id,
                'address' => 'Jl. Admin No. 1',
                'password' => Hash::make('admin123'),
            ],
            'super_admin'
        );

        // Super admin bersifat global
        $superAdmin->wasteBanksAsStaff()->detach();

        /*
        |--------------------------------------------------------------------------
        | Admin Bank Sampah
        |--------------------------------------------------------------------------
        */
        $admin = $this->createUser(
            'admin@gmail.com',
            [
                'name' => 'Admin',
                'number' => '001010120251234',
                'district_id' => $district->id,
                'sub_district_id' => $subDistrict->id,
                'address' => 'Jl. Admin No. 1',
                'password' => Hash::make('admin123'),
            ],
            'admin'
        );

        // Admin hanya menangani BS001
        if (! $admin->wasteBanksAsStaff()->whereKey($bank->id)->exists()) {
            app(AdminMembershipService::class)->assignBankAdmin($superAdmin, $admin, $bank);
        }

        /*
        |--------------------------------------------------------------------------
        | User / Anggota
        |--------------------------------------------------------------------------
        */
        $userSubDistrict = $subDistricts->get(1) ?? $subDistrict;

        $user = $this->createUser(
            'user@gmail.com',
            [
                'name' => 'taufikhdyt',
                'number' => '001010220254321',
                'district_id' => $district->id,
                'sub_district_id' => $userSubDistrict->id,
                'address' => 'Jl. Admin No. 2',
                'password' => Hash::make('user123'),
            ],
            'user'
        );

        // User bukan staff bank
        $user->wasteBanksAsStaff()->detach();
    }

    /**
     * Membuat atau memperbarui user lalu menyinkronkan role.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(
        string $email,
        array $attributes,
        string $role
    ): User {
        $user = User::query()->firstOrCreate(
            [
                'email' => $email,
            ],
            [
                ...$attributes,
                'status' => 1,
                'balance' => 0,
            ]
        );

        $user->syncRoles([
            Role::findOrCreate($role, 'web'),
        ]);

        return $user->refresh();
    }
}
