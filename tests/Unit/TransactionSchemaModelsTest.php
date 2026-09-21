<?php

namespace Tests\Unit;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\WasteDeposit;
use App\Models\WasteDepositItem;
use App\Models\Withdrawal;
use Tests\TestCase;

class TransactionSchemaModelsTest extends TestCase
{
    public function test_transaction_models_expose_the_schema_contract(): void
    {
        $item = new WasteDepositItem;
        $deposit = new WasteDeposit;
        $withdrawal = new Withdrawal;
        $ledger = new LedgerEntry;
        $user = new User;

        self::assertSame('waste_deposit_id', $item->wasteDeposit()->getForeignKeyName());
        self::assertSame('decimal:3', $item->getCasts()['quantity']);
        self::assertNotContains('unit_price_snapshot', $item->getFillable());
        self::assertSame('date', $withdrawal->getCasts()['withdrawal_date']);
        self::assertFalse(method_exists($withdrawal, 'scopeCompleted'));
        self::assertSame('account_ledger_entries', $ledger->getTable());
        self::assertSame('user_id', $ledger->user()->getForeignKeyName());
        self::assertSame('created_by', $ledger->createdBy()->getForeignKeyName());
        self::assertNotContains('status', $deposit->getFillable());
        self::assertSame('user_id', $user->ledgerEntries()->getForeignKeyName());
    }
}
