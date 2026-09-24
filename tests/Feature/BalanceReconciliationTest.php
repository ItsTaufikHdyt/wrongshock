<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\WasteDeposit;
use App\Models\WasteItem;
use App\Models\Withdrawal;
use App\Services\BalanceReconciliationService;
use App\Services\DepositService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BalanceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private BalanceReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BalanceReconciliationService::class);
    }

    public function test_reconciled_opening_balance_is_a_match(): void
    {
        $user = $this->createUser(100000);
        $this->createLedger([
            'user_id' => $user->id,
            'type' => 'opening_balance',
            'direction' => 'credit',
            'amount' => 100000,
            'reference_type' => User::class,
            'reference_id' => $user->id,
            'description' => 'Opening balance',
        ]);

        $result = $this->service->reconcileUser($user);

        $this->assertSame(100000, $result['ledger_net']);
        $this->assertSame(0, $result['difference']);
        $this->assertSame('MATCH', $result['status']);
    }

    public function test_positive_legacy_cached_balance_is_an_opening_candidate(): void
    {
        $result = $this->service->reconcileUser($this->createUser(100000));

        $this->assertSame(0, $result['ledger_net']);
        $this->assertSame(100000, $result['difference']);
        $this->assertSame('OPENING_BALANCE_CANDIDATE', $result['status']);
    }

    public function test_opening_balance_creation_does_not_change_cached_balance(): void
    {
        $user = $this->createUser(100000);

        $summary = $this->service->createOpeningBalances();

        $this->assertSame(['created' => 1, 'skipped' => 0, 'review' => 0], $summary);
        $this->assertSame(100000, $user->refresh()->balance);
        $this->assertDatabaseHas('account_ledger_entries', [
            'user_id' => $user->id,
            'type' => 'opening_balance',
            'direction' => 'credit',
            'amount' => 100000,
            'reference_type' => User::class,
            'reference_id' => $user->id,
        ]);
    }

    public function test_opening_balance_creation_is_idempotent(): void
    {
        $user = $this->createUser(100000);

        $this->service->createOpeningBalances();
        $second = $this->service->createOpeningBalances();

        $this->assertSame(['created' => 0, 'skipped' => 1, 'review' => 0], $second);
        $this->assertSame(1, LedgerEntry::query()
            ->where('user_id', $user->id)
            ->where('type', 'opening_balance')
            ->count());
        $this->assertSame(100000, $user->refresh()->balance);
    }

    public function test_zero_balance_is_a_match_without_zero_entry(): void
    {
        $user = $this->createUser(0);

        $result = $this->service->reconcileUser($user);
        $summary = $this->service->createOpeningBalances();

        $this->assertSame('MATCH', $result['status']);
        $this->assertSame(['created' => 0, 'skipped' => 1, 'review' => 0], $summary);
        $this->assertDatabaseCount('account_ledger_entries', 0);
    }

    public function test_existing_deposit_history_requires_review(): void
    {
        $user = $this->createUser(100000);
        $deposit = new WasteDeposit;
        $deposit->forceFill([
            'user_id' => $user->id,
            'deposit_date' => '2026-09-21',
            'total_amount' => 100,
            'status' => 'posted',
            'posted_at' => now(),
        ]);
        $deposit->save();

        $result = $this->service->reconcileUser($user);
        $summary = $this->service->createOpeningBalances();

        $this->assertSame('MISMATCH_REQUIRES_REVIEW', $result['status']);
        $this->assertSame(['created' => 0, 'skipped' => 0, 'review' => 1], $summary);
    }

    public function test_existing_withdrawal_history_requires_review(): void
    {
        $user = $this->createUser(100000);
        $withdrawal = new Withdrawal;
        $withdrawal->forceFill([
            'user_id' => $user->id,
            'amount' => 100,
            'status' => 'rejected',
            'withdrawal_date' => today(),
            'requested_date' => today(),
            'processed_date' => today(),
            'note' => 'Rejected',
        ]);
        $withdrawal->save();

        $result = $this->service->reconcileUser($user);

        $this->assertSame('MISMATCH_REQUIRES_REVIEW', $result['status']);
    }

    public function test_existing_ledger_mismatch_requires_review(): void
    {
        $user = $this->createUser(100000);
        $this->createLedger([
            'user_id' => $user->id,
            'type' => 'deposit_credit',
            'direction' => 'credit',
            'amount' => 50000,
            'description' => 'Existing ledger',
        ]);

        $result = $this->service->reconcileUser($user);
        $summary = $this->service->createOpeningBalances();

        $this->assertSame('MISMATCH_REQUIRES_REVIEW', $result['status']);
        $this->assertSame(['created' => 0, 'skipped' => 0, 'review' => 1], $summary);
    }

    public function test_default_command_is_read_only(): void
    {
        $user = $this->createUser(100000);

        $this->artisan('finance:reconcile')->assertExitCode(0);

        $this->assertSame(100000, $user->refresh()->balance);
        $this->assertDatabaseCount('account_ledger_entries', 0);
    }

    public function test_explicit_cache_repair_uses_existing_ledger_without_new_entry(): void
    {
        $user = $this->createUser(90000);
        $this->createLedger([
            'user_id' => $user->id,
            'type' => 'opening_balance',
            'direction' => 'credit',
            'amount' => 100000,
            'reference_type' => User::class,
            'reference_id' => $user->id,
            'description' => 'Opening balance',
        ]);

        $summary = $this->service->repairCache();

        $this->assertSame(['repaired' => 1, 'skipped' => 0, 'review' => 0], $summary);
        $this->assertSame(100000, $user->refresh()->balance);
        $this->assertDatabaseCount('account_ledger_entries', 1);
    }

    public function test_negative_ledger_is_not_repaired_automatically(): void
    {
        $user = $this->createUser(0);
        $this->createLedger([
            'user_id' => $user->id,
            'type' => 'withdrawal_debit',
            'direction' => 'debit',
            'amount' => 10000,
            'description' => 'Negative ledger test',
        ]);

        $result = $this->service->reconcileUser($user);
        $summary = $this->service->repairCache();

        $this->assertSame('MISMATCH_REQUIRES_REVIEW', $result['status']);
        $this->assertSame(['repaired' => 0, 'skipped' => 0, 'review' => 1], $summary);
        $this->assertSame(0, $user->refresh()->balance);
    }

    public function test_core_services_preserve_reconciled_financial_invariant(): void
    {
        $user = $this->createUser(100000);
        Auth::login($user);
        $item = WasteItem::query()->create([
            'category' => 'Plastic',
            'output' => 'Kriya',
            'unit' => 'Kilogram (Kg)',
            'price' => 25000,
        ]);

        $this->service->createOpeningBalances();
        $first = app(DepositService::class)->post($user->id, '2026-09-21', [
            ['waste_item_id' => $item->id, 'quantity' => '1.000'],
        ]);
        app(DepositService::class)->cancel($first, 'Correction');
        app(DepositService::class)->post($user->id, '2026-09-21', [
            ['waste_item_id' => $item->id, 'quantity' => '1.600'],
        ]);
        $withdrawal = app(WithdrawalService::class)->request($user->id, 20000);
        app(WithdrawalService::class)->approve($withdrawal);

        $result = $this->service->reconcileUser($user->refresh());

        $this->assertSame(120000, $user->balance);
        $this->assertSame(120000, $result['ledger_net']);
        $this->assertSame(0, $result['difference']);
        $this->assertSame('MATCH', $result['status']);
        $this->assertSame(5, LedgerEntry::query()->where('user_id', $user->id)->count());
    }

    public function test_full_financial_lifecycle_reconciles_after_every_operation(): void
    {
        $user = $this->createUser(100000);
        Auth::login($user);
        $item = WasteItem::query()->create([
            'category' => 'Plastic',
            'output' => 'Kriya',
            'unit' => 'Kilogram (Kg)',
            'price' => 10000,
        ]);

        $this->service->createOpeningBalances();
        $this->assertReconciled($user, 100000);

        $depositA = app(DepositService::class)->post($user->id, today()->toDateString(), [
            ['waste_item_id' => $item->id, 'quantity' => '2.500'],
        ], $user->id);
        $this->assertSame(25000, $depositA->total_amount);
        $this->assertReconciled($user, 125000);

        $cancelled = app(DepositService::class)->cancel($depositA, 'Lifecycle correction', $user->id);
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame(1, $cancelled->items()->count());
        $this->assertReconciled($user, 100000);

        $item->update(['price' => 8000]);
        $depositB = app(DepositService::class)->post($user->id, today()->toDateString(), [
            ['waste_item_id' => $item->id, 'quantity' => '5.000'],
        ], $user->id);
        $this->assertSame(40000, $depositB->total_amount);
        $this->assertReconciled($user, 140000);

        $withdrawal = app(WithdrawalService::class)->request($user->id, 30000, $user->id);
        $this->assertSame('pending', $withdrawal->status);
        $this->assertReconciled($user, 140000);

        $approved = app(WithdrawalService::class)->approve($withdrawal, $user->id);
        $this->assertSame('approved', $approved->status);
        $this->assertReconciled($user, 110000);

        $this->assertSame(1, LedgerEntry::query()->where('type', 'opening_balance')->count());
        $this->assertSame(2, LedgerEntry::query()->where('type', 'deposit_credit')->count());
        $this->assertSame(1, LedgerEntry::query()->where('type', 'deposit_reversal')->count());
        $this->assertSame(1, LedgerEntry::query()->where('type', 'withdrawal_debit')->count());
        $this->assertSame(10000, $depositA->items()->first()->unit_price_snapshot);
        $this->assertSame(8000, $depositB->items()->first()->unit_price_snapshot);
    }

    public function test_rejected_withdrawal_preserves_balance_and_reconciliation(): void
    {
        $user = $this->createUser(100000);
        Auth::login($user);
        $this->service->createOpeningBalances();

        $withdrawal = app(WithdrawalService::class)->request($user->id, 40000, $user->id);
        $rejected = app(WithdrawalService::class)->reject($withdrawal, 'Incomplete account details', $user->id);

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame(100000, $user->refresh()->balance);
        $this->assertSame(100000, $this->service->reconcileUser($user)['ledger_net']);
        $this->assertSame(0, LedgerEntry::query()->where('type', 'withdrawal_debit')->count());
    }

    private function createUser(int $balance): User
    {
        $districtId = DB::table('districts')->insertGetId([
            'name' => 'Reconciliation District '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => 'Reconciliation Subdistrict '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->create([
            'name' => 'Reconciliation User',
            'number' => 'RECON-'.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => 'password',
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => 1,
        ]);

        $user->forceFill(['balance' => $balance])->save();
        $user->assignRole(Role::findOrCreate('admin', 'web'));
        $this->assignDefaultWasteBank($user);

        return $user;
    }

    private function createLedger(array $attributes): LedgerEntry
    {
        $entry = new LedgerEntry;
        $entry->forceFill($attributes)->save();

        return $entry;
    }

    private function assertReconciled(User $user, int $expectedBalance): void
    {
        $result = $this->service->reconcileUser($user->refresh());

        $this->assertSame($expectedBalance, $user->balance);
        $this->assertSame($expectedBalance, $result['ledger_net']);
        $this->assertSame(0, $result['difference']);
        $this->assertSame('MATCH', $result['status']);
    }
}
