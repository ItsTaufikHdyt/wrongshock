<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $nullDeposits = DB::table('waste_deposits')->whereNull('waste_bank_id')->pluck('id')->all();
        $nullWithdrawals = DB::table('withdrawals')->whereNull('waste_bank_id')->pluck('id')->all();
        $nullLedger = DB::table('account_ledger_entries')->whereNull('waste_bank_id')->pluck('id')->all();

        if ($nullDeposits !== [] || $nullWithdrawals !== [] || $nullLedger !== []) {
            throw new \RuntimeException(sprintf(
                'Financial hardening aborted: null bank ownership deposits=%s withdrawals=%s ledger=%s.',
                implode(',', $nullDeposits),
                implode(',', $nullWithdrawals),
                implode(',', $nullLedger),
            ));
        }

        $orphanAccounts = DB::table('waste_bank_accounts')
            ->leftJoin('users', 'users.id', '=', 'waste_bank_accounts.user_id')
            ->leftJoin('waste_banks', 'waste_banks.id', '=', 'waste_bank_accounts.waste_bank_id')
            ->where(fn ($query) => $query->whereNull('users.id')->orWhereNull('waste_banks.id'))
            ->pluck('waste_bank_accounts.id')
            ->all();

        if ($orphanAccounts !== []) {
            throw new \RuntimeException('Financial hardening aborted: orphan accounts '.implode(',', $orphanAccounts).'.');
        }

        Schema::table('waste_deposits', function (Blueprint $table): void {
            $table->unsignedBigInteger('waste_bank_id')->nullable(false)->change();
        });

        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->unsignedBigInteger('waste_bank_id')->nullable(false)->change();
        });

        Schema::table('account_ledger_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('waste_bank_id')->nullable(false)->change();
        });

        Schema::table('waste_bank_accounts', function (Blueprint $table): void {
            $table->bigInteger('balance')->default(0)->nullable(false)->change();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE waste_bank_accounts ADD CONSTRAINT waste_bank_accounts_balance_nonnegative CHECK (balance >= 0)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE waste_bank_accounts DROP CHECK waste_bank_accounts_balance_nonnegative');
        }

        Schema::table('waste_bank_accounts', function (Blueprint $table): void {
            $table->bigInteger('balance')->default(0)->nullable(false)->change();
        });

        Schema::table('account_ledger_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('waste_bank_id')->nullable()->change();
        });

        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->unsignedBigInteger('waste_bank_id')->nullable()->change();
        });

        Schema::table('waste_deposits', function (Blueprint $table): void {
            $table->unsignedBigInteger('waste_bank_id')->nullable()->change();
        });
    }
};
