<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat role jika belum ada
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        $admin = User::firstOrCreate(['email' => 'admin@gmail.com'], [
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'number' => '001010120251234',
            'password' => bcrypt('admin123'),
            'address' => 'Jl. Admin No. 1',
            'balance' => 0,
            'district_id' => 1,
            'sub_district_id' => 1,
            'status' => '1',

        ]);
        $admin->assignRole($superAdminRole);
        $admin->removeRole($adminRole);

        $user = User::firstOrCreate(['email' => 'user@gmail.com'], [
            'name' => 'taufikhdyt',
            'email' => 'user@gmail.com',
            'number' => '001010220254321',
            'password' => bcrypt('user123'),
            'address' => 'Jl. Admin No. 2',
            'balance' => 0,
            'district_id' => 1,
            'sub_district_id' => 2,
            'status' => '1',
        ]);
        $user->assignRole($userRole);
    }
}
