<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteBankMember;
use App\Models\WasteItem;
use App\Services\DepositService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerBankFinancialCutoverTest extends TestCase
{
    use RefreshDatabase;

    private User $citizen;

    private User $adminOne;

    private User $adminTwo;

    private WasteBank $bankOne;

    private WasteBank $bankTwo;

    private WasteItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $district = DB::table('districts')->insertGetId(['name' => 'Multi-bank district', 'created_at' => now(), 'updated_at' => now()]);
        $subDistrict = DB::table('sub_districts')->insertGetId(['district_id' => $district, 'name' => 'Multi-bank subdistrict', 'created_at' => now(), 'updated_at' => now()]);
        $this->bankOne = WasteBank::create(['code' => 'P2A001', 'name' => 'Bank Satu', 'status' => true]);
        $this->bankTwo = WasteBank::create(['code' => 'P2A002', 'name' => 'Bank Dua', 'status' => true]);
        $this->citizen = $this->user('Citizen', 'citizen');
        $this->adminOne = $this->user('Admin One', 'admin-one', 'admin');
        $this->adminTwo = $this->user('Admin Two', 'admin-two', 'admin');
        $this->adminOne->wasteBanksAsStaff()->attach($this->bankOne->id);
        $this->adminTwo->wasteBanksAsStaff()->attach($this->bankTwo->id);
        WasteBankMember::query()->insert([
            ['waste_bank_id' => $this->bankOne->id, 'user_id' => $this->citizen->id, 'joined_at' => now(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['waste_bank_id' => $this->bankTwo->id, 'user_id' => $this->citizen->id, 'joined_at' => now(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->item = WasteItem::create(['category' => 'Plastic', 'output' => 'Kriya', 'unit' => 'Kilogram (Kg)', 'price' => 100]);
    }

    #[Test]
    public function deposits_and_withdrawals_are_isolated_per_bank(): void
    {
        $depositOne = app(DepositService::class)->post($this->citizen->id, '2026-09-25', [['waste_item_id' => $this->item->id, 'quantity' => '1000.000']], $this->adminOne->id, $this->bankOne);
        $depositTwo = app(DepositService::class)->post($this->citizen->id, '2026-09-25', [['waste_item_id' => $this->item->id, 'quantity' => '2000.000']], $this->adminTwo->id, $this->bankTwo);

        $this->assertDatabaseHas('waste_bank_accounts', ['user_id' => $this->citizen->id, 'waste_bank_id' => $this->bankOne->id, 'balance' => 100000]);
        $this->assertDatabaseHas('waste_bank_accounts', ['user_id' => $this->citizen->id, 'waste_bank_id' => $this->bankTwo->id, 'balance' => 200000]);
        $this->assertSame(300000, $this->citizen->refresh()->balance);
        $this->assertDatabaseHas('account_ledger_entries', ['reference_id' => $depositOne->id, 'waste_bank_id' => $this->bankOne->id]);
        $this->assertDatabaseHas('account_ledger_entries', ['reference_id' => $depositTwo->id, 'waste_bank_id' => $this->bankTwo->id]);

        $withdrawal = app(WithdrawalService::class)->request($this->citizen->id, 50000, $this->adminOne->id);
        app(WithdrawalService::class)->approve($withdrawal, $this->adminOne->id);

        $this->assertDatabaseHas('waste_bank_accounts', ['user_id' => $this->citizen->id, 'waste_bank_id' => $this->bankOne->id, 'balance' => 50000]);
        $this->assertDatabaseHas('waste_bank_accounts', ['user_id' => $this->citizen->id, 'waste_bank_id' => $this->bankTwo->id, 'balance' => 200000]);
        $this->assertSame(250000, $this->citizen->refresh()->balance);
        $this->assertDatabaseHas('account_ledger_entries', ['reference_type' => $withdrawal::class, 'reference_id' => $withdrawal->id, 'waste_bank_id' => $this->bankOne->id]);

        $this->expectException(ValidationException::class);
        app(WithdrawalService::class)->request($this->citizen->id, 60000, $this->adminOne->id);
    }

    #[Test]
    public function cancellation_changes_only_the_original_bank(): void
    {
        $depositOne = app(DepositService::class)->post($this->citizen->id, '2026-09-25', [['waste_item_id' => $this->item->id, 'quantity' => '1000.000']], $this->adminOne->id, $this->bankOne);
        app(DepositService::class)->post($this->citizen->id, '2026-09-25', [['waste_item_id' => $this->item->id, 'quantity' => '2000.000']], $this->adminTwo->id, $this->bankTwo);

        app(DepositService::class)->cancel($depositOne, 'Correction', $this->adminOne->id);

        $this->assertDatabaseHas('waste_bank_accounts', ['user_id' => $this->citizen->id, 'waste_bank_id' => $this->bankOne->id, 'balance' => 0]);
        $this->assertDatabaseHas('waste_bank_accounts', ['user_id' => $this->citizen->id, 'waste_bank_id' => $this->bankTwo->id, 'balance' => 200000]);
        $this->assertSame(200000, $this->citizen->refresh()->balance);
        $this->assertDatabaseHas('account_ledger_entries', ['type' => 'deposit_reversal', 'reference_id' => $depositOne->id, 'waste_bank_id' => $this->bankOne->id]);
    }

    #[Test]
    public function inactive_membership_preserves_account_but_blocks_new_operations(): void
    {
        app(DepositService::class)->post($this->citizen->id, '2026-09-25', [['waste_item_id' => $this->item->id, 'quantity' => '1000.000']], $this->adminOne->id, $this->bankOne);
        WasteBankMember::query()->where('user_id', $this->citizen->id)->where('waste_bank_id', $this->bankOne->id)->update(['status' => 'inactive']);

        try {
            app(DepositService::class)->post($this->citizen->id, '2026-09-25', [['waste_item_id' => $this->item->id, 'quantity' => '1.000']], $this->adminOne->id, $this->bankOne);
            $this->fail('Inactive membership should block deposits.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        try {
            app(WithdrawalService::class)->request($this->citizen->id, 1, $this->adminOne->id);
            $this->fail('Inactive membership should block withdrawals.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseHas('waste_bank_accounts', ['user_id' => $this->citizen->id, 'waste_bank_id' => $this->bankOne->id, 'balance' => 100000]);
        $this->assertSame(100000, $this->citizen->refresh()->balance);
    }

    private function user(string $name, string $key, string $role = 'user'): User
    {
        $user = User::create([
            'name' => $name,
            'number' => strtoupper($key),
            'email' => $key.'@example.test',
            'password' => 'password',
            'district_id' => DB::table('districts')->value('id'),
            'sub_district_id' => DB::table('sub_districts')->value('id'),
            'status' => 1,
        ]);
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }
}
