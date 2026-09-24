<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'super_admin')->where('guard_name', 'web')->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'super_admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $legacyAdminId = DB::table('users')
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', 'App\\Models\\User');
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('users.email', 'admin@gmail.com')
            ->where('roles.name', 'admin')
            ->where('roles.guard_name', 'web')
            ->value('users.id');

        if ($legacyAdminId !== null) {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $roleId,
                'model_type' => 'App\\Models\\User',
                'model_id' => $legacyAdminId,
            ]);

            $adminRoleId = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->value('id');
            if ($adminRoleId !== null) {
                DB::table('model_has_roles')
                    ->where('role_id', $adminRoleId)
                    ->where('model_type', 'App\\Models\\User')
                    ->where('model_id', $legacyAdminId)
                    ->delete();
            }

            DB::table('waste_bank_staff')->where('user_id', $legacyAdminId)->delete();
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', 'super_admin')->where('guard_name', 'web')->value('id');

        if ($roleId !== null) {
            DB::table('model_has_roles')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
