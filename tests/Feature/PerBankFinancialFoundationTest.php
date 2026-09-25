<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteBankAccount;
use App\Models\WasteBankMember;
use App\Models\WasteDeposit;
use App\Models\Withdrawal;
use App\Services\BalanceReconciliationService;
use App\Services\LedgerBankAttributionService;
use App\Services\WasteBankAccountBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerBankFinancialFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_model_relationships_balance_default_and_unique_pair(): void
    {
        [$user, $bank] = $this->userAndBank();
        $account = new WasteBankAccount;
        $account->forceFill(['user_id' => $user->id, 'waste_bank_id' => $bank->id])->save();

        $this->assertSame(0, $account->refresh()->balance);
        $this->assertTrue($account->user->is($user));
        $this->assertTrue($account->wasteBank->is($bank));
        $this->assertTrue($user->wasteBankAccounts->sole()->is($account));
        $this->assertTrue($bank->accounts->sole()->is($account));

        $duplicate = new WasteBankAccount;
        $duplicate->forceFill(['user_id' => $user->id, 'waste_bank_id' => $bank->id, 'balance' => 1]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $duplicate->save();
    }

    public function test_deposit_credit_and_reversal_derive_the_original_bank(): void
    {
        [$user, $bank] = $this->userAndBank();
        $deposit = $this->deposit($user, $bank, 100);
        $credit = $this->ledger($user, 'deposit_credit', 'credit', 100, WasteDeposit::class, $deposit->id);
        $reversal = $this->ledger($user, 'deposit_reversal', 'debit', 100, WasteDeposit::class, $deposit->id);
        $service = app(LedgerBankAttributionService::class);

        $this->assertSame($bank->id, $service->analyze($credit)['waste_bank_id']);
        $this->assertSame('DERIVABLE', $service->analyze($reversal)['status']);
        $this->assertSame($bank->id, $service->analyze($reversal)['waste_bank_id']);
    }

    public function test_withdrawal_debit_derives_its_bank(): void
    {
        [$user, $bank] = $this->userAndBank();
        $withdrawal = new Withdrawal;
        $withdrawal->forceFill([
            'user_id' => $user->id,
            'waste_bank_id' => $bank->id,
            'amount' => 25,
            'status' => 'approved',
            'withdrawal_date' => today(),
        ])->save();
        $withdrawal = $withdrawal->refresh();
        $entry = $this->ledger($user, 'withdrawal_debit', 'debit', 25, Withdrawal::class, $withdrawal->id);

        $result = app(LedgerBankAttributionService::class)->analyze($entry);

        $this->assertSame('DERIVABLE', $result['status']);
        $this->assertSame($bank->id, $result['waste_bank_id']);
    }

    public function test_bank_owned_ledger_source_integrity_is_checked(): void
    {
        [$user, $bank] = $this->userAndBank();
        $service = app(LedgerBankAttributionService::class);
        $opening = $this->ledger($user, 'opening_balance', 'credit', 100, null, null, $bank);
        $missingSource = $this->ledger($user, 'deposit_credit', 'credit', 100, WasteDeposit::class, 999999, $bank);
        $otherUser = $this->userAndBank()[0];
        $mismatchSource = $this->deposit($otherUser, $bank, 100);
        $mismatch = $this->ledger($user, 'deposit_credit', 'credit', 100, WasteDeposit::class, $mismatchSource->id, $bank);

        $this->assertSame('DERIVABLE', $service->analyze($opening)['status']);
        $this->assertSame('MISSING', $service->analyze($missingSource)['status']);
        $this->assertSame('MISMATCH', $service->analyze($mismatch)['status']);
    }

    public function test_ledger_bank_backfill_is_safe_and_idempotent(): void
    {
        [$user, $bank] = $this->userAndBank();
        $deposit = $this->deposit($user, $bank, 100);
        $this->ledger($user, 'deposit_credit', 'credit', 100, WasteDeposit::class, $deposit->id);
        $service = app(\App\Services\LedgerBankBackfillService::class);

        $first = $service->backfill();
        $second = $service->backfill();

        $this->assertSame(['analyzed' => 1, 'updated' => 0, 'unchanged' => 1], $first);
        $this->assertSame(['analyzed' => 1, 'updated' => 0, 'unchanged' => 1], $second);
        $this->assertDatabaseHas('account_ledger_entries', ['waste_bank_id' => $bank->id, 'amount' => 100]);
    }

    public function test_bankless_ledger_creation_is_rejected(): void
    {
        [$user] = $this->userAndBank();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Ledger entries require explicit Waste Bank ownership.');
        $this->ledger($user, 'opening_balance', 'credit', 100, null, null, null);
    }

    public function test_account_backfill_uses_ledger_ownership_without_splitting_balances(): void
    {
        [$user, $bankA] = $this->userAndBank();
        $bankB = $this->bank('BS002');
        WasteBankMember::query()->create(['user_id' => $user->id, 'waste_bank_id' => $bankB->id, 'status' => 'active']);
        $depositA = $this->deposit($user, $bankA, 150000);
        $depositB = $this->deposit($user, $bankB, 200000);
        $this->ledger($user, 'deposit_credit', 'credit', 150000, WasteDeposit::class, $depositA->id, $bankA);
        $this->ledger($user, 'deposit_reversal', 'debit', 50000, WasteDeposit::class, $depositA->id, $bankA);
        $this->ledger($user, 'deposit_credit', 'credit', 200000, WasteDeposit::class, $depositB->id, $bankB);

        $result = app(WasteBankAccountBackfillService::class)->backfill();

        $this->assertSame(2, $result['created']);
        $this->assertSame(100000, (int) WasteBankAccount::query()->where('user_id', $user->id)->where('waste_bank_id', $bankA->id)->value('balance'));
        $this->assertSame(200000, (int) WasteBankAccount::query()->where('user_id', $user->id)->where('waste_bank_id', $bankB->id)->value('balance'));
        $this->assertSame(300000, (int) WasteBankAccount::query()->where('user_id', $user->id)->sum('balance'));
    }

    public function test_account_backfill_is_idempotent_and_existing_mismatch_blocks(): void
    {
        [$user, $bank] = $this->userAndBank();
        $deposit = $this->deposit($user, $bank, 100);
        $this->ledger($user, 'deposit_credit', 'credit', 100, WasteDeposit::class, $deposit->id);

        $first = app(WasteBankAccountBackfillService::class)->backfill();
        $second = app(WasteBankAccountBackfillService::class)->backfill();
        $this->assertSame(1, $first['created']);
        $this->assertSame(1, $second['unchanged']);

        WasteBankAccount::query()->where('user_id', $user->id)->update(['balance' => 99]);
        $this->expectException(LogicException::class);
        app(WasteBankAccountBackfillService::class)->backfill();
    }

    public function test_inactive_membership_preserves_account_and_reactivation_reuses_it(): void
    {
        [$user, $bank] = $this->userAndBank();
        $membership = WasteBankMember::query()->where('user_id', $user->id)->where('waste_bank_id', $bank->id)->firstOrFail();
        $account = new WasteBankAccount;
        $account->forceFill(['user_id' => $user->id, 'waste_bank_id' => $bank->id, 'balance' => 250])->save();

        $membership->update(['status' => 'inactive']);
        $membership->update(['status' => 'active']);

        $this->assertDatabaseHas('waste_bank_accounts', ['id' => $account->id, 'balance' => 250]);
        $this->assertSame($account->id, WasteBankAccount::query()->where('user_id', $user->id)->where('waste_bank_id', $bank->id)->value('id'));
    }

    public function test_per_bank_and_aggregate_reconciliation_match(): void
    {
        [$user, $bank] = $this->userAndBank();
        $deposit = $this->deposit($user, $bank, 300);
        $this->ledger($user, 'deposit_credit', 'credit', 300, WasteDeposit::class, $deposit->id);
        $user->forceFill(['balance' => 300])->save();
        app(\App\Services\LedgerBankBackfillService::class)->backfill();
        app(WasteBankAccountBackfillService::class)->backfill();
        $service = app(BalanceReconciliationService::class);

        $this->assertSame('MATCH', $service->reconcileUserByBank($user)[0]['status']);
        $aggregate = $service->reconcileAggregate($user);
        $this->assertSame('MATCH', $aggregate['account_status']);
        $this->assertSame('MATCH', $aggregate['legacy_status']);
    }

    public function test_negative_per_bank_ledger_blocks_account_backfill(): void
    {
        [$user, $bank] = $this->userAndBank();
        $deposit = $this->deposit($user, $bank, 100);
        $withdrawal = new Withdrawal;
        $withdrawal->forceFill([
            'user_id' => $user->id,
            'waste_bank_id' => $bank->id,
            'amount' => 200,
            'status' => 'approved',
            'withdrawal_date' => today(),
        ])->save();
        $withdrawal = $withdrawal->refresh();
        $this->ledger($user, 'deposit_credit', 'credit', 100, WasteDeposit::class, $deposit->id);
        $this->ledger($user, 'withdrawal_debit', 'debit', 200, Withdrawal::class, $withdrawal->id);

        $this->expectException(LogicException::class);
        app(WasteBankAccountBackfillService::class)->backfill();
    }

    public function test_ledger_is_append_only_after_creation(): void
    {
        [$user, $bank] = $this->userAndBank();
        $entry = $this->ledger($user, 'opening_balance', 'credit', 100, null, null, $bank);

        try {
            $entry->forceFill(['amount' => 101])->save();
            $this->fail('Ledger values must be immutable.');
        } catch (LogicException $exception) {
            $this->assertSame('Ledger financial ownership and values are immutable.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Ledger history cannot be deleted.');
        $entry->delete();
    }

    public function test_account_cannot_become_negative(): void
    {
        [$user, $bank] = $this->userAndBank();
        $account = new WasteBankAccount;
        $account->forceFill(['user_id' => $user->id, 'waste_bank_id' => $bank->id, 'balance' => -1]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Waste Bank account balance cannot be negative.');
        $account->save();
    }

    public function test_bank_owned_financial_rows_require_a_bank_at_database_boundary(): void
    {
        [$user] = $this->userAndBank();

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('account_ledger_entries')->insert([
            'user_id' => $user->id,
            'type' => 'opening_balance',
            'direction' => 'credit',
            'amount' => 100,
            'waste_bank_id' => null,
        ]);
    }

    public function test_posted_financial_transaction_bank_ownership_is_immutable(): void
    {
        [$user, $bank] = $this->userAndBank();
        $otherBank = $this->bank('BS'.uniqid());
        $deposit = $this->deposit($user, $bank, 100);
        $withdrawal = new Withdrawal;
        $withdrawal->forceFill([
            'user_id' => $user->id,
            'waste_bank_id' => $bank->id,
            'amount' => 25,
            'status' => 'pending',
            'withdrawal_date' => today(),
            'requested_date' => today(),
        ])->save();

        try {
            $deposit->forceFill(['waste_bank_id' => $otherBank->id])->save();
            $this->fail('Posted deposit bank ownership must be immutable.');
        } catch (LogicException $exception) {
            $this->assertSame('Posted deposit bank ownership cannot be changed.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Withdrawal bank ownership cannot be changed.');
        $withdrawal->forceFill(['waste_bank_id' => $otherBank->id])->save();
    }

    private function userAndBank(): array
    {
        [$districtId, $subDistrictId] = $this->region();
        $user = User::query()->create([
            'name' => 'Foundation User',
            'number' => 'FOUNDATION-'.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => 'password',
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => 1,
        ]);
        $user->assignRole(Role::findOrCreate('user', 'web'));
        $bank = $this->bank('BS'.uniqid());
        WasteBankMember::query()->create(['user_id' => $user->id, 'waste_bank_id' => $bank->id, 'status' => 'active']);

        return [$user->refresh(), $bank];
    }

    private function bank(string $code): WasteBank
    {
        return WasteBank::query()->create(['code' => $code, 'name' => $code, 'status' => true]);
    }

    private function deposit(User $user, ?WasteBank $bank, int $total): WasteDeposit
    {
        $deposit = new WasteDeposit;
        $deposit->forceFill([
            'user_id' => $user->id,
            'waste_bank_id' => $bank?->id,
            'deposit_date' => today(),
            'total_amount' => $total,
            'status' => 'posted',
        ])->save();

        return $deposit->refresh();
    }

    private function ledger(User $user, string $type, string $direction, int $amount, ?string $sourceType = null, ?int $sourceId = null, WasteBank|false|null $bank = false): LedgerEntry
    {
        $entry = new LedgerEntry;
        $entry->forceFill([
            'user_id' => $user->id,
            'waste_bank_id' => $bank === false ? $user->bankMemberships()->value('waste_bank_id') : $bank?->id,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'reference_type' => $sourceType,
            'reference_id' => $sourceId,
        ])->save();

        return $entry->refresh();
    }

    /** @return array{0:int,1:int} */
    private function region(): array
    {
        $districtId = DB::table('districts')->insertGetId([
            'name' => 'Foundation District '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => 'Foundation Subdistrict '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$districtId, $subDistrictId];
    }
}
