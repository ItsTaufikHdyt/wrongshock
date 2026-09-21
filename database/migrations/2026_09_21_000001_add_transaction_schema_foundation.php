<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_districts', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
            $table->foreign('district_id')
                ->references('id')
                ->on('districts')
                ->restrictOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
            $table->dropForeign(['sub_district_id']);
            $table->foreign('district_id')
                ->references('id')
                ->on('districts')
                ->restrictOnDelete();
            $table->foreign('sub_district_id')
                ->references('id')
                ->on('sub_districts')
                ->restrictOnDelete();
        });

        Schema::table('waste_deposits', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->enum('status', ['draft', 'posted', 'cancelled'])
                ->default('draft')
                ->after('total_amount');
            $table->timestamp('posted_at')->nullable()->after('status');
            $table->timestamp('cancelled_at')->nullable()->after('posted_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->foreignId('created_by')->nullable()->after('cancellation_reason')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('waste_deposit_items', function (Blueprint $table) {
            $table->dropForeign(['waste_item_id']);
            $table->unsignedBigInteger('waste_item_id')->nullable()->change();
            $table->decimal('quantity', 12, 3)->change();
            $table->string('waste_name_snapshot')->nullable()->after('waste_item_id');
            $table->string('category_snapshot')->nullable()->after('waste_name_snapshot');
            $table->string('unit_snapshot')->nullable()->after('category_snapshot');
            $table->bigInteger('unit_price_snapshot')->nullable()->after('unit_snapshot');
            $table->foreign('waste_item_id')
                ->references('id')
                ->on('waste_items')
                ->nullOnDelete();
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::create('account_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('type', [
                'opening_balance',
                'deposit_credit',
                'withdrawal_debit',
                'deposit_reversal',
                'withdrawal_reversal',
                'adjustment',
            ]);
            $table->enum('direction', ['credit', 'debit']);
            $table->unsignedBigInteger('amount');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->unique(
                ['reference_type', 'reference_id', 'type'],
                'ledger_reference_type_id_type_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_ledger_entries');

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::table('waste_deposit_items', function (Blueprint $table) {
            $table->dropForeign(['waste_item_id']);
            $table->unsignedBigInteger('waste_item_id')->nullable(false)->change();
            $table->bigInteger('quantity')->change();
            $table->foreign('waste_item_id')
                ->references('id')
                ->on('waste_items')
                ->cascadeOnDelete();
            $table->dropColumn([
                'waste_name_snapshot',
                'category_snapshot',
                'unit_snapshot',
                'unit_price_snapshot',
            ]);
        });

        Schema::table('waste_deposits', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'status',
                'posted_at',
                'cancelled_at',
                'cancellation_reason',
                'created_by',
                'updated_by',
            ]);
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
            $table->dropForeign(['sub_district_id']);
            $table->foreign('district_id')
                ->references('id')
                ->on('districts')
                ->cascadeOnDelete();
            $table->foreign('sub_district_id')
                ->references('id')
                ->on('sub_districts')
                ->cascadeOnDelete();
        });

        Schema::table('sub_districts', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
            $table->foreign('district_id')
                ->references('id')
                ->on('districts')
                ->cascadeOnDelete();
        });
    }
};
