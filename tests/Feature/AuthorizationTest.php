<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureActiveUser;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteBankMember;
use App\Models\WasteDeposit;
use App\Models\WasteItem;
use App\Models\Withdrawal;
use App\Services\DepositService;
use App\Services\WithdrawalService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_access_requires_the_matching_active_role(): void
    {
        $admin = $this->createUser('admin');
        $user = $this->createUser('user');
        $inactiveAdmin = $this->createUser('admin', 0);

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('adminPanel')));
        $this->assertFalse($admin->canAccessPanel(Filament::getPanel('userPanel')));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('adminPanel')));
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('userPanel')));
        $this->assertFalse($inactiveAdmin->canAccessPanel(Filament::getPanel('adminPanel')));
    }

    public function test_role_revocation_removes_panel_access_without_relogin(): void
    {
        $admin = $this->createUser('admin');
        $panel = Filament::getPanel('adminPanel');

        $this->assertTrue($admin->canAccessPanel($panel));
        $admin->removeRole('admin');

        $this->assertFalse($admin->refresh()->canAccessPanel($panel));
    }

    public function test_role_revoked_admin_cannot_execute_a_financial_action(): void
    {
        $admin = $this->createUser('admin');
        $user = $this->createUser('user', 1, 100000);
        $item = $this->createWasteItem();
        $admin->removeRole('admin');

        $this->expectException(AuthorizationException::class);
        try {
            app(DepositService::class)->post($user->id, today()->toDateString(), [
                ['waste_item_id' => $item->id, 'quantity' => '1.000'],
            ], $admin->id);
        } finally {
            $this->assertDatabaseCount('waste_deposits', 0);
            $this->assertDatabaseCount('waste_deposit_items', 0);
            $this->assertDatabaseCount('account_ledger_entries', 0);
            $this->assertSame(100000, $user->refresh()->balance);
        }
    }

    public function test_inactive_authenticated_user_is_denied_on_subsequent_request(): void
    {
        $user = $this->createUser('user', 0);
        Auth::login($user);
        $request = Request::create('/user', 'GET');
        $request->setUserResolver(fn (): User => $user);
        $request->setLaravelSession(app('session')->driver());

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Akun Anda tidak aktif.');
        (new EnsureActiveUser)->handle($request, fn () => response('ok'));
    }

    public function test_user_policy_is_owner_scoped(): void
    {
        $first = $this->createUser('user');
        $second = $this->createUser('user');

        $this->assertTrue($first->can('view', $first));
        $this->assertTrue($first->can('update', $first));
        $this->assertFalse($first->can('view', $second));
        $this->assertFalse($first->can('update', $second));
    }

    public function test_financial_history_policies_are_owner_scoped(): void
    {
        $first = $this->createUser('user');
        $second = $this->createUser('user');
        $deposit = new WasteDeposit;
        $deposit->forceFill([
            'user_id' => $second->id,
            'deposit_date' => today(),
            'total_amount' => 100,
            'status' => 'posted',
            'posted_at' => now(),
        ])->save();
        $withdrawal = new Withdrawal;
        $withdrawal->forceFill([
            'user_id' => $second->id,
            'amount' => 100,
            'status' => 'pending',
            'withdrawal_date' => today(),
            'requested_date' => today(),
        ])->save();

        $this->assertFalse($first->can('view', $deposit));
        $this->assertFalse($first->can('view', $withdrawal));
        $this->assertTrue($second->can('view', $deposit));
        $this->assertTrue($second->can('view', $withdrawal));
    }

    public function test_normal_user_cannot_post_deposit_and_financial_state_is_unchanged(): void
    {
        $admin = $this->createUser('admin');
        $user = $this->createUser('user', 1, 100000);
        $item = $this->createWasteItem();

        $this->expectException(AuthorizationException::class);
        try {
            app(DepositService::class)->post($user->id, today()->toDateString(), [
                ['waste_item_id' => $item->id, 'quantity' => '1.000'],
            ], $user->id);
        } finally {
            $this->assertSame(100000, $user->refresh()->balance);
            $this->assertDatabaseCount('waste_deposits', 0);
            $this->assertDatabaseCount('account_ledger_entries', 0);
            $this->assertTrue($admin->exists);
        }
    }

    public function test_normal_user_cannot_cancel_a_deposit(): void
    {
        $admin = $this->createUser('admin');
        $user = $this->createUser('user', 1, 100000);
        Auth::login($admin);
        $deposit = app(DepositService::class)->post($user->id, today()->toDateString(), [
            ['waste_item_id' => $this->createWasteItem()->id, 'quantity' => '1.000'],
        ], $admin->id);

        $this->expectException(AuthorizationException::class);
        try {
            app(DepositService::class)->cancel($deposit, 'Forged cancellation', $user->id);
        } finally {
            $this->assertSame('posted', $deposit->refresh()->status);
            $this->assertSame(1, LedgerEntry::query()->count());
            $this->assertSame(103000, $user->refresh()->balance);
        }
    }

    public function test_normal_user_cannot_approve_or_reject_withdrawal(): void
    {
        $admin = $this->createUser('admin');
        $user = $this->createUser('user', 1, 100000);
        Auth::login($admin);
        $withdrawal = app(WithdrawalService::class)->request($user->id, 40000, $admin->id);

        foreach (['approve', 'reject'] as $action) {
            try {
                if ($action === 'approve') {
                    app(WithdrawalService::class)->approve($withdrawal, $user->id);
                } else {
                    app(WithdrawalService::class)->reject($withdrawal, 'Forged rejection', $user->id);
                }
                $this->fail('Unauthorized withdrawal action should fail.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('pending', $withdrawal->refresh()->status);
        $this->assertSame(100000, $user->refresh()->balance);
        $this->assertDatabaseCount('account_ledger_entries', 0);
    }

    public function test_forged_user_id_cannot_create_withdrawal_for_another_account(): void
    {
        $admin = $this->createUser('admin');
        $user = $this->createUser('user', 1, 100000);

        $this->expectException(AuthorizationException::class);
        try {
            app(WithdrawalService::class)->request($user->id, 40000, $user->id);
        } finally {
            $this->assertDatabaseCount('withdrawals', 0);
            $this->assertSame(100000, $user->refresh()->balance);
            $this->assertTrue($admin->exists);
        }
    }

    public function test_inactive_admin_cannot_execute_financial_action(): void
    {
        $inactiveAdmin = $this->createUser('admin', 0);
        $user = $this->createUser('user', 1, 100000);
        $item = $this->createWasteItem();

        $this->expectException(AuthorizationException::class);
        try {
            app(DepositService::class)->post($user->id, today()->toDateString(), [
                ['waste_item_id' => $item->id, 'quantity' => '1.000'],
            ], $inactiveAdmin->id);
        } finally {
            $this->assertDatabaseCount('waste_deposits', 0);
            $this->assertSame(100000, $user->refresh()->balance);
        }
    }

    public function test_ledger_policy_disallows_ordinary_mutations(): void
    {
        $admin = $this->createUser('admin');
        $entry = new LedgerEntry;
        $entry->forceFill([
            'user_id' => $admin->id,
            'type' => 'opening_balance',
            'direction' => 'credit',
            'amount' => 100,
        ])->save();

        $this->assertFalse($admin->can('create', LedgerEntry::class));
        $this->assertFalse($admin->can('update', $entry));
        $this->assertFalse($admin->can('delete', $entry));
    }

    public function test_balance_is_not_mass_assignable(): void
    {
        $user = $this->createUser('user');
        $user->fill(['balance' => 999999]);

        $this->assertSame(0, $user->balance);
        $this->assertFalse(in_array('balance', $user->getFillable(), true));
    }

    public function test_registration_creates_inactive_user_with_zero_balance_and_user_role(): void
    {
        $this->withoutMiddleware();
        [$districtId, $subDistrictId] = $this->region();
        $bank = WasteBank::query()->firstOrCreate(
            ['code' => 'BS001'],
            ['name' => 'Test Bank Sampah', 'status' => true]
        );

        $response = $this->post('/storeRegister', [
            'name' => 'New Member',
            'email' => 'new-member@example.test',
            'address' => 'Address',
            'district' => $districtId,
            'sub_district' => $subDistrictId,
            'waste_bank_id' => $bank->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'balance' => 999999,
            'status' => 1,
            'role' => 'admin',
        ]);

        $response->assertRedirect('/register');
        $user = User::query()->where('email', 'new-member@example.test')->firstOrFail();
        $this->assertSame(0, $user->balance);
        $this->assertSame(0, (int) $user->status);
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_registration_rejects_mismatched_district_and_subdistrict(): void
    {
        $this->withoutMiddleware();
        [$firstDistrict, $firstSubDistrict] = $this->region();
        [$secondDistrict] = $this->region();
        $bank = WasteBank::query()->firstOrCreate(
            ['code' => 'BS001'],
            ['name' => 'Test Bank Sampah', 'status' => true]
        );

        $response = $this->from('/register')->post('/storeRegister', [
            'name' => 'Invalid Region',
            'email' => 'invalid-region@example.test',
            'address' => 'Address',
            'district' => $secondDistrict,
            'sub_district' => $firstSubDistrict,
            'waste_bank_id' => $bank->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/register')->assertSessionHasErrors('sub_district');
        $this->assertDatabaseMissing('users', ['email' => 'invalid-region@example.test']);
        $this->assertTrue($firstDistrict > 0);
    }

    private function createUser(string $role, int $status = 1, int $balance = 0): User
    {
        [$districtId, $subDistrictId] = $this->region();
        $user = User::query()->create([
            'name' => ucfirst($role).' User',
            'number' => strtoupper($role).'-'.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => Hash::make('password123'),
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => $status,
        ]);
        $user->forceFill(['balance' => $balance])->save();
        $user->assignRole(Role::findOrCreate($role, 'web'));
        if ($role === 'admin') {
            $this->assignDefaultWasteBank($user);
        } else {
            $bank = WasteBank::query()->firstOrCreate(
                ['code' => 'BS001'],
                ['name' => 'Test Bank Sampah', 'status' => true]
            );
            WasteBankMember::create([
                'waste_bank_id' => $bank->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'status' => 'active',
            ]);
        }

        return $user->refresh();
    }

    private function createWasteItem(): WasteItem
    {
        return WasteItem::query()->create([
            'category' => 'Plastic',
            'output' => 'Kriya',
            'unit' => 'Kilogram (Kg)',
            'price' => 3000,
        ]);
    }

    /** @return array{0:int,1:int} */
    private function region(): array
    {
        $districtId = DB::table('districts')->insertGetId([
            'name' => 'Auth District '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => 'Auth Subdistrict '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$districtId, $subDistrictId];
    }
}
