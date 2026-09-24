<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $superAdminId = DB::table('users')
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', 'App\\Models\\User');
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('users.email', 'admin@gmail.com')
            ->where('roles.name', 'super_admin')
            ->where('roles.guard_name', 'web')
            ->value('users.id');

        if ($superAdminId !== null) {
            DB::table('waste_bank_staff')->where('user_id', $superAdminId)->delete();
        }
    }

    public function down(): void {}
};
