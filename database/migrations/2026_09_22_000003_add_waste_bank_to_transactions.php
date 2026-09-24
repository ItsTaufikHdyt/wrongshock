<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waste_deposits', function (Blueprint $table): void {
            $table->foreignId('waste_bank_id')->nullable()->after('user_id');
            $table->index(['waste_bank_id', 'deposit_date']);
            $table->index(['waste_bank_id', 'status']);
        });

        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->foreignId('waste_bank_id')->nullable()->after('user_id');
            $table->index(['waste_bank_id', 'status']);
        });

        $bankId = DB::table('waste_banks')->where('code', 'BS001')->value('id');

        if ($bankId === null) {
            $bankId = DB::table('waste_banks')->insertGetId([
                'code' => 'BS001',
                'name' => 'Wrongshock Bank Sampah Utama',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('waste_deposits')->whereNull('waste_bank_id')->update(['waste_bank_id' => $bankId]);
        DB::table('withdrawals')->whereNull('waste_bank_id')->update(['waste_bank_id' => $bankId]);

        $adminIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'admin')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->pluck('model_id');

        foreach ($adminIds as $userId) {
            DB::table('waste_bank_staff')->insertOrIgnore([
                'waste_bank_id' => $bankId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('waste_deposits', function (Blueprint $table): void {
            $table->foreign('waste_bank_id')->references('id')->on('waste_banks')->restrictOnDelete();
        });

        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->foreign('waste_bank_id')->references('id')->on('waste_banks')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->dropForeign(['waste_bank_id']);
            $table->dropIndex(['waste_bank_id', 'status']);
            $table->dropColumn('waste_bank_id');
        });

        Schema::table('waste_deposits', function (Blueprint $table): void {
            $table->dropForeign(['waste_bank_id']);
            $table->dropIndex(['waste_bank_id', 'deposit_date']);
            $table->dropIndex(['waste_bank_id', 'status']);
            $table->dropColumn('waste_bank_id');
        });
    }
};
