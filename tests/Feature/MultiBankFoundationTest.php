<?php

namespace Tests\Feature;

use App\Filament\Resources\WasteDepositResource;
use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteDeposit;
use App\Models\WasteItem;
use App\Services\DepositService;
use App\Services\WithdrawalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiBankFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_banks_validate_locations_and_staff_assignments_are_unique(): void
    {
        [$districtA, $subDistrictA] = $this->region('A');
        [$districtB] = $this->region('B');

        $bank = WasteBank::create([
            'code' => 'BS100',
            'name' => 'Bank A',
            'district_id' => $districtA,
            'sub_district_id' => $subDistrictA,
        ]);
        $admin = $this->admin('admin@example.test', $bank);

        try {
            WasteBank::create([
                'code' => 'BS101',
                'name' => 'Invalid Bank',
                'district_id' => $districtB,
                'sub_district_id' => $subDistrictA,
            ]);
            $this->fail('Invalid location pairing should fail.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            DB::table('waste_bank_staff')->insert([
                'waste_bank_id' => $bank->id,
                'user_id' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Duplicate staff assignments should fail.');
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $this->assertTrue(true);
        }
    }

    public function test_each_admin_only_operates_its_assigned_bank(): void
    {
        $bankA = WasteBank::create(['code' => 'BS100', 'name' => 'Bank A']);
        $bankB = WasteBank::create(['code' => 'BS101', 'name' => 'Bank B']);
        $adminA = $this->admin('a@example.test', $bankA);
        $adminB = $this->admin('b@example.test', $bankB);
        $citizen = $this->citizen();
        $item = $this->item();

        $depositA = app(DepositService::class)->post($citizen->id, today()->toDateString(), [
            ['waste_item_id' => $item->id, 'quantity' => '1.000'],
        ], $adminA->id);
        $depositB = app(DepositService::class)->post($citizen->id, today()->toDateString(), [
            ['waste_item_id' => $item->id, 'quantity' => '2.000'],
        ], $adminB->id);

        Auth::login($adminA);
        $visible = WasteDepositResource::getEloquentQuery()->pluck('id')->all();
        $this->assertSame([$depositA->id], $visible);
        $this->assertNotContains($depositB->id, $visible);
        $this->get(WasteDepositResource::getUrl('edit', ['record' => $depositB], panel: 'adminPanel'))
            ->assertNotFound();

        $this->expectException(AuthorizationException::class);
        app(DepositService::class)->cancel($depositA, 'Wrong operator', $adminB->id);
    }

    public function test_forged_bank_context_is_rejected(): void
    {
        $bankA = WasteBank::create(['code' => 'BS100', 'name' => 'Bank A']);
        $bankB = WasteBank::create(['code' => 'BS101', 'name' => 'Bank B']);
        $adminA = $this->admin('a@example.test', $bankA);
        $citizen = $this->citizen();
        $item = $this->item();

        $this->expectException(AuthorizationException::class);
        app(DepositService::class)->post(
            $citizen->id,
            today()->toDateString(),
            [['waste_item_id' => $item->id, 'quantity' => '1.000']],
            $adminA->id,
            $bankB
        );
    }

    public function test_citizen_history_and_global_balance_cross_bank(): void
    {
        $bankA = WasteBank::create(['code' => 'BS100', 'name' => 'Bank A']);
        $bankB = WasteBank::create(['code' => 'BS101', 'name' => 'Bank B']);
        $adminA = $this->admin('a@example.test', $bankA);
        $adminB = $this->admin('b@example.test', $bankB);
        $citizen = $this->citizen();
        $item = $this->item();

        app(DepositService::class)->post($citizen->id, today()->toDateString(), [
            ['waste_item_id' => $item->id, 'quantity' => '1.000'],
        ], $adminA->id);
        app(DepositService::class)->post($citizen->id, today()->toDateString(), [
            ['waste_item_id' => $item->id, 'quantity' => '2.000'],
        ], $adminB->id);

        Auth::login($citizen);
        $this->assertCount(2, WasteDeposit::query()->where('user_id', $citizen->id)->get());
        $this->assertSame(9000, (int) $citizen->refresh()->balance);
        $this->assertSame('MATCH', app(\App\Services\BalanceReconciliationService::class)->reconcileUser($citizen)['status']);
    }

    public function test_inactive_bank_rejects_new_transactions_but_preserves_history(): void
    {
        $bank = WasteBank::create(['code' => 'BS100', 'name' => 'Bank A']);
        $admin = $this->admin('a@example.test', $bank);
        $citizen = $this->citizen();
        $item = $this->item();
        $deposit = app(DepositService::class)->post($citizen->id, today()->toDateString(), [
            ['waste_item_id' => $item->id, 'quantity' => '1.000'],
        ], $admin->id);

        $bank->update(['status' => false]);
        try {
            app(DepositService::class)->post($citizen->id, today()->toDateString(), [
                ['waste_item_id' => $item->id, 'quantity' => '1.000'],
            ], $admin->id);
            $this->fail('Inactive banks must reject new transactions.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('waste_deposits', ['id' => $deposit->id, 'waste_bank_id' => $bank->id]);
    }

    public function test_withdrawal_processing_bank_is_preserved_without_changing_ledger_rules(): void
    {
        $bankA = WasteBank::create(['code' => 'BS100', 'name' => 'Bank A']);
        $bankB = WasteBank::create(['code' => 'BS101', 'name' => 'Bank B']);
        $adminA = $this->admin('a@example.test', $bankA);
        $adminB = $this->admin('b@example.test', $bankB);
        $citizen = $this->citizen(100000);

        $withdrawal = app(WithdrawalService::class)->request($citizen->id, 10000, $adminA->id);
        $this->assertSame($bankA->id, $withdrawal->waste_bank_id);

        $this->expectException(AuthorizationException::class);
        app(WithdrawalService::class)->approve($withdrawal, $adminB->id);
    }

    private function admin(string $email, WasteBank $bank): User
    {
        $admin = $this->citizen(0, $email);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $admin->wasteBanksAsStaff()->attach($bank);

        return $admin->refresh();
    }

    private function citizen(int $balance = 0, ?string $email = null): User
    {
        [$districtId, $subDistrictId] = $this->region(uniqid());

        $user = User::create([
            'name' => 'Test Person',
            'number' => 'TEST-'.uniqid(),
            'email' => $email ?? uniqid().'@example.test',
            'password' => 'password',
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => 1,
        ]);
        $user->forceFill(['balance' => $balance])->save();
        $user->assignRole(Role::findOrCreate('user', 'web'));

        return $user;
    }

    private function item(): WasteItem
    {
        return WasteItem::create([
            'category' => 'Plastic',
            'output' => 'Kriya',
            'unit' => 'Kilogram (Kg)',
            'price' => 3000,
        ]);
    }

    /** @return array{0:int,1:int} */
    private function region(string $suffix): array
    {
        $districtId = DB::table('districts')->insertGetId([
            'name' => 'District '.$suffix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => 'Subdistrict '.$suffix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$districtId, $subDistrictId];
    }
}
