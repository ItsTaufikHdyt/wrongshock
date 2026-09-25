<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\WasteBankAccount;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WithdrawalServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $districtId = DB::table('districts')->insertGetId([
            'name' => 'Withdrawal District',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => 'Withdrawal Subdistrict',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->user = User::create([
            'name' => 'Withdrawal User',
            'number' => 'WITHDRAWAL-'.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => 'password',
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => 1,
        ]);
        $this->user->forceFill(['balance' => 100000])->save();
        $this->user->assignRole(Role::findOrCreate('user', 'web'));

        $this->admin = User::create([
            'name' => 'Withdrawal Admin',
            'number' => 'WITHDRAWAL-ADMIN-'.uniqid(),
            'email' => uniqid().'@admin.example.test',
            'password' => 'password',
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => 1,
        ]);
        $this->admin->assignRole(Role::findOrCreate('admin', 'web'));
        $bank = $this->assignDefaultWasteBank($this->admin);
        $this->user->bankMemberships()->create([
            'waste_bank_id' => $bank->id,
            'joined_at' => now(),
            'status' => 'active',
        ]);
        $account = new WasteBankAccount;
        $account->forceFill(['user_id' => $this->user->id, 'waste_bank_id' => $bank->id, 'balance' => 100000])->save();
        Auth::login($this->admin);
    }

    public function test_request_is_pending_and_has_no_financial_effect(): void
    {
        $withdrawal = app(WithdrawalService::class)->request($this->user->id, 40000, $this->admin->id, 'Need cash');

        $this->assertSame('pending', $withdrawal->status);
        $this->assertSame(40000, $withdrawal->amount);
        $this->assertNotNull($withdrawal->requested_date);
        $this->assertSame('Need cash', $withdrawal->note);
        $this->assertSame(100000, $this->user->refresh()->balance);
        $this->assertDatabaseCount('account_ledger_entries', 0);
    }

    public function test_request_rejects_zero_negative_decimal_and_float_amounts(): void
    {
        foreach ([0, -1, '40000.50', 40000.5] as $amount) {
            try {
                app(WithdrawalService::class)->request($this->user->id, $amount);
                $this->fail('Expected amount validation to fail.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseCount('withdrawals', 0);
    }

    public function test_request_rejects_amount_above_cached_balance(): void
    {
        $this->expectException(ValidationException::class);

        app(WithdrawalService::class)->request($this->user->id, 150000);
    }

    public function test_request_requires_an_existing_user(): void
    {
        $this->expectException(ModelNotFoundException::class);

        app(WithdrawalService::class)->request(999999, 1000);
    }

    public function test_amount_is_stored_as_integer_rupiah(): void
    {
        $withdrawal = app(WithdrawalService::class)->request($this->user->id, '40000');

        $this->assertIsInt($withdrawal->amount);
        $this->assertSame(40000, $withdrawal->amount);
    }

    public function test_approve_creates_one_debit_and_decrements_cached_balance(): void
    {
        $withdrawal = app(WithdrawalService::class)->request($this->user->id, 40000);

        $approved = app(WithdrawalService::class)->approve($withdrawal, $this->admin->id);

        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->processed_date);
        $this->assertSame(60000, $this->user->refresh()->balance);
        $this->assertDatabaseHas('account_ledger_entries', [
            'user_id' => $this->user->id,
            'type' => 'withdrawal_debit',
            'direction' => 'debit',
            'amount' => 40000,
            'reference_type' => Withdrawal::class,
            'reference_id' => $withdrawal->id,
            'created_by' => $this->admin->id,
        ]);
        $this->assertSame(1, LedgerEntry::query()
            ->where('reference_type', Withdrawal::class)
            ->where('reference_id', $withdrawal->id)
            ->where('type', 'withdrawal_debit')
            ->count());
    }

    public function test_approve_cannot_be_repeated(): void
    {
        $withdrawal = app(WithdrawalService::class)->request($this->user->id, 40000);
        app(WithdrawalService::class)->approve($withdrawal);

        $this->expectException(LogicException::class);
        app(WithdrawalService::class)->approve($withdrawal);
    }

    public function test_approval_rechecks_current_cached_balance(): void
    {
        $withdrawal = app(WithdrawalService::class)->request($this->user->id, 40000);
        WasteBankAccount::query()->where('user_id', $this->user->id)->update(['balance' => 30000]);
        $this->user->forceFill(['balance' => 30000])->save();

        try {
            app(WithdrawalService::class)->approve($withdrawal);
            $this->fail('Expected approval to fail for insufficient balance.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('pending', $withdrawal->refresh()->status);
        $this->assertSame(30000, $this->user->refresh()->balance);
        $this->assertDatabaseCount('account_ledger_entries', 0);
    }

    public function test_multiple_pending_requests_are_checked_again_at_approval(): void
    {
        $first = app(WithdrawalService::class)->request($this->user->id, 80000);
        $second = app(WithdrawalService::class)->request($this->user->id, 80000);

        app(WithdrawalService::class)->approve($first);

        try {
            app(WithdrawalService::class)->approve($second);
            $this->fail('Expected the second approval to fail.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(20000, $this->user->refresh()->balance);
        $this->assertSame('pending', $second->refresh()->status);
        $this->assertSame(1, LedgerEntry::query()->where('type', 'withdrawal_debit')->count());
    }

    public function test_reject_preserves_reason_without_financial_effect(): void
    {
        $withdrawal = app(WithdrawalService::class)->request($this->user->id, 40000, null, 'Original note');

        $rejected = app(WithdrawalService::class)->reject($withdrawal, 'Data rekening tidak lengkap', $this->admin->id);

        $this->assertSame('rejected', $rejected->status);
        $this->assertNotNull($rejected->processed_date);
        $this->assertSame('Data rekening tidak lengkap', $rejected->note);
        $this->assertSame(100000, $this->user->refresh()->balance);
        $this->assertDatabaseCount('account_ledger_entries', 0);
    }

    public function test_approve_rejected_withdrawal_fails(): void
    {
        $withdrawal = app(WithdrawalService::class)->request($this->user->id, 40000);
        app(WithdrawalService::class)->reject($withdrawal, 'Rejected');

        $this->expectException(LogicException::class);
        app(WithdrawalService::class)->approve($withdrawal);
    }

    public function test_reject_approved_withdrawal_fails(): void
    {
        $withdrawal = app(WithdrawalService::class)->request($this->user->id, 40000);
        app(WithdrawalService::class)->approve($withdrawal);

        $this->expectException(LogicException::class);
        app(WithdrawalService::class)->reject($withdrawal, 'Too late');
    }

    public function test_reject_cannot_be_repeated(): void
    {
        $withdrawal = app(WithdrawalService::class)->request($this->user->id, 40000);
        app(WithdrawalService::class)->reject($withdrawal, 'Rejected');

        $this->expectException(LogicException::class);
        app(WithdrawalService::class)->reject($withdrawal, 'Rejected again');
    }

    public function test_rejection_reason_is_required(): void
    {
        $withdrawal = app(WithdrawalService::class)->request($this->user->id, 40000);

        $this->expectException(ValidationException::class);
        app(WithdrawalService::class)->reject($withdrawal, '   ');
    }

    public function test_approval_rolls_back_when_ledger_creation_fails(): void
    {
        $withdrawal = app(WithdrawalService::class)->request($this->user->id, 40000);
        LedgerEntry::creating(function (): void {
            throw new RuntimeException('forced ledger failure');
        });

        $this->expectException(RuntimeException::class);
        try {
            app(WithdrawalService::class)->approve($withdrawal);
        } finally {
            LedgerEntry::flushEventListeners();
            $this->assertSame('pending', $withdrawal->refresh()->status);
            $this->assertSame(100000, $this->user->refresh()->balance);
            $this->assertDatabaseCount('account_ledger_entries', 0);
        }
    }

    public function test_submitted_withdrawals_cannot_be_deleted(): void
    {
        $pending = app(WithdrawalService::class)->request($this->user->id, 1000);
        $this->expectException(LogicException::class);
        $pending->delete();
    }

    public function test_approved_and_rejected_withdrawals_remain_history(): void
    {
        $approved = app(WithdrawalService::class)->request($this->user->id, 40000);
        app(WithdrawalService::class)->approve($approved);
        $rejected = app(WithdrawalService::class)->request($this->user->id, 10000);
        app(WithdrawalService::class)->reject($rejected, 'Rejected');

        try {
            $approved->delete();
            $this->fail('Expected approved deletion to fail.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }
        try {
            $rejected->delete();
            $this->fail('Expected rejected deletion to fail.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('withdrawals', 2);
    }
}
