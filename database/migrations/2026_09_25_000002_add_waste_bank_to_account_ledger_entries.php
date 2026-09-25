<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_ledger_entries', function (Blueprint $table): void {
            $table->foreignId('waste_bank_id')->nullable()->after('user_id')
                ->constrained('waste_banks')->restrictOnDelete();
            $table->index(['user_id', 'waste_bank_id'], 'ledger_user_bank_index');
            $table->index(['waste_bank_id', 'created_at'], 'ledger_bank_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('account_ledger_entries', function (Blueprint $table): void {
            $table->dropIndex('ledger_user_bank_index');
            $table->dropIndex('ledger_bank_created_at_index');
            $table->dropForeign(['waste_bank_id']);
            $table->dropColumn('waste_bank_id');
        });
    }
};
