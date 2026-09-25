<?php

namespace Tests\Feature;

use App\Filament\Resources\PlatformUserResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\WasteBankResource;
use App\Filament\Resources\WasteBankStaffResource;
use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteBankMember;
use App\Services\AdminMembershipService;
use App\Services\BankMembershipService;
use App\Services\UserRoleService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BankMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_can_be_a_member_of_two_banks_but_not_twice_in_one(): void
    {
        $bankA = WasteBank::factory()->create(['code' => 'BS100']);
        $bankB = WasteBank::factory()->create(['code' => 'BS101']);
        $citizen = $this->makeUser('user');

        WasteBankMember::create(['waste_bank_id' => $bankA->id, 'user_id' => $citizen->id, 'status' => 'active']);
        WasteBankMember::create(['waste_bank_id' => $bankB->id, 'user_id' => $citizen->id, 'status' => 'active']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        WasteBankMember::create(['waste_bank_id' => $bankA->id, 'user_id' => $citizen->id, 'status' => 'active']);
    }

    public function test_membership_is_separate_from_staff_and_has_no_financial_effect(): void
    {
        $admin = $this->makeUser('admin');
        $bank = $this->assignDefaultWasteBank($admin);
        $citizen = $this->makeUser('user');
        $before = [User::sum('balance'), DB::table('account_ledger_entries')->count()];

        app(BankMembershipService::class)->addMember($admin, $citizen);

        $this->assertDatabaseHas('waste_bank_members', [
            'waste_bank_id' => $bank->id,
            'user_id' => $citizen->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('waste_bank_staff', ['user_id' => $citizen->id]);
        $this->assertSame($before, [User::sum('balance'), DB::table('account_ledger_entries')->count()]);
    }

    public function test_admin_cannot_be_added_as_member(): void
    {
        $super = $this->makeUser('super_admin');
        $bank = WasteBank::factory()->create(['code' => 'BS100']);
        $this->expectException(ValidationException::class);
        app(BankMembershipService::class)->addMember($super, $this->makeUser('admin'), $bank);
    }

    public function test_super_admin_cannot_be_added_as_member(): void
    {
        $super = $this->makeUser('super_admin');
        $bank = WasteBank::factory()->create(['code' => 'BS100']);

        $this->expectException(ValidationException::class);
        app(BankMembershipService::class)->addMember($super, $this->makeUser('super_admin'), $bank);
    }

    public function test_inactive_citizen_cannot_be_added_as_member(): void
    {
        $super = $this->makeUser('super_admin');
        $bank = WasteBank::factory()->create(['code' => 'BS100']);
        $inactive = $this->makeUser('user');
        $inactive->update(['status' => 0]);

        $this->expectException(ValidationException::class);
        app(BankMembershipService::class)->addMember($super, $inactive, $bank);
    }

    public function test_membership_can_be_deactivated_and_reactivated_without_duplicate_or_financial_change(): void
    {
        $super = $this->makeUser('super_admin');
        $citizen = $this->makeUser('user');
        $bankA = WasteBank::factory()->create(['code' => 'BS100']);
        $bankB = WasteBank::factory()->create(['code' => 'BS101']);
        $membershipA = app(BankMembershipService::class)->addMember($super, $citizen, $bankA);
        app(BankMembershipService::class)->addMember($super, $citizen, $bankB);
        $citizen->update(['balance' => 120000]);
        $before = [
            $citizen->balance,
            DB::table('account_ledger_entries')->count(),
            DB::table('waste_deposits')->count(),
            DB::table('withdrawals')->count(),
        ];

        app(BankMembershipService::class)->deactivateMembership($super, $citizen, $bankA);
        $this->assertSame('inactive', $membershipA->refresh()->status);
        $this->assertSame('active', $citizen->bankMemberships()->where('waste_bank_id', $bankB->id)->value('status'));

        app(BankMembershipService::class)->reactivateMembership($super, $citizen, $bankA);
        $this->assertSame('active', $membershipA->refresh()->status);
        $this->assertSame(2, $citizen->bankMemberships()->count());
        $this->assertSame($before, [
            $citizen->refresh()->balance,
            DB::table('account_ledger_entries')->count(),
            DB::table('waste_deposits')->count(),
            DB::table('withdrawals')->count(),
        ]);
    }

    public function test_bank_admin_membership_actions_are_scoped_to_current_bank(): void
    {
        $bankA = WasteBank::factory()->create(['code' => 'BS100']);
        $bankB = WasteBank::factory()->create(['code' => 'BS101']);
        $admin = $this->makeUser('admin');
        $admin->wasteBanksAsStaff()->attach($bankA);
        $citizen = $this->makeUser('user');
        $super = $this->makeUser('super_admin');
        app(BankMembershipService::class)->addMember($super, $citizen, $bankA);
        app(BankMembershipService::class)->addMember($super, $citizen, $bankB);

        app(BankMembershipService::class)->deactivateMembership($admin, $citizen, $bankB);

        $this->assertSame('inactive', $citizen->bankMemberships()->where('waste_bank_id', $bankA->id)->value('status'));
        $this->assertSame('active', $citizen->bankMemberships()->where('waste_bank_id', $bankB->id)->value('status'));
    }

    public function test_editing_member_profile_preserves_memberships_and_unchanged_number(): void
    {
        $super = $this->makeUser('super_admin');
        $citizen = $this->makeUser('user');
        $bank = WasteBank::factory()->create(['code' => 'BS100']);
        $membership = app(BankMembershipService::class)->addMember($super, $citizen, $bank);

        Filament::setCurrentPanel(Filament::getPanel('adminPanel'));
        $this->actingAs($super);
        Livewire::test(EditUser::class, ['record' => $citizen->getRouteKey()])
            ->set('data.name', 'Updated Citizen')
            ->set('data.address', 'Updated address')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated Citizen', $citizen->refresh()->name);
        $this->assertDatabaseHas('waste_bank_members', ['id' => $membership->id, 'status' => 'active']);
    }

    public function test_editing_member_cannot_change_member_number(): void
    {
        $super = $this->makeUser('super_admin');
        $citizen = $this->makeUser('user');
        $other = $this->makeUser('user');

        Filament::setCurrentPanel(Filament::getPanel('adminPanel'));
        $this->actingAs($super);
        Livewire::test(EditUser::class, ['record' => $citizen->getRouteKey()])
            ->set('data.number', $other->number)
            ->call('save')
            ->assertHasNoFormErrors(['number']);

        $this->assertNotSame($other->number, $citizen->refresh()->number);
    }

    public function test_member_workflow_does_not_expose_global_user_delete(): void
    {
        $super = $this->makeUser('super_admin');
        $citizen = $this->makeUser('user');

        $this->actingAs($super)
            ->get(UserResource::getUrl('edit', ['record' => $citizen], panel: 'adminPanel'))
            ->assertOk()
            ->assertDontSee('Hapus Anggota');

        $this->actingAs($super)
            ->get(UserResource::getUrl('index', panel: 'adminPanel'))
            ->assertOk()
            ->assertSee('Tambah Keanggotaan');
    }

    public function test_removing_staff_preserves_user_membership_and_financial_state(): void
    {
        $super = $this->makeUser('super_admin');
        $admin = $this->makeUser('admin');
        $bank = WasteBank::factory()->create(['code' => 'BS100']);
        $member = $this->makeUser('user');
        $membership = app(BankMembershipService::class)->addMember($super, $member, $bank);
        $assignment = app(AdminMembershipService::class)->assignBankAdmin($super, $admin, $bank);
        $before = [User::sum('balance'), DB::table('account_ledger_entries')->count()];

        app(AdminMembershipService::class)->removeBankAdmin($super, $assignment);

        $this->assertDatabaseMissing('waste_bank_staff', ['id' => $assignment->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('waste_bank_members', ['id' => $membership->id]);
        $this->assertSame($before, [User::sum('balance'), DB::table('account_ledger_entries')->count()]);
    }

    public function test_bank_admin_sees_only_current_bank_membership(): void
    {
        $bankA = WasteBank::factory()->create(['code' => 'BS100']);
        $bankB = WasteBank::factory()->create(['code' => 'BS101']);
        $adminA = $this->makeUser('admin');
        $adminB = $this->makeUser('admin');
        $adminA->wasteBanksAsStaff()->attach($bankA);
        $adminB->wasteBanksAsStaff()->attach($bankB);
        $memberA = $this->makeUser('user');
        $memberB = $this->makeUser('user');
        $shared = $this->makeUser('user');
        WasteBankMember::insert([
            ['waste_bank_id' => $bankA->id, 'user_id' => $memberA->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['waste_bank_id' => $bankB->id, 'user_id' => $memberB->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['waste_bank_id' => $bankA->id, 'user_id' => $shared->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['waste_bank_id' => $bankB->id, 'user_id' => $shared->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Filament::setCurrentPanel(Filament::getPanel('adminPanel'));
        $this->actingAs($adminA);
        $idsA = UserResource::getEloquentQuery()->pluck('users.id')->all();
        $this->actingAs($adminB);
        $idsB = UserResource::getEloquentQuery()->pluck('users.id')->all();

        $this->assertContains($memberA->id, $idsA);
        $this->assertContains($shared->id, $idsA);
        $this->assertNotContains($memberB->id, $idsA);
        $this->assertContains($memberB->id, $idsB);
        $this->assertContains($shared->id, $idsB);
        $this->assertNotContains($memberA->id, $idsB);
    }

    public function test_bank_admin_create_adds_membership_immediately_and_ignores_bank_input(): void
    {
        $bankA = WasteBank::factory()->create(['code' => 'BS100']);
        $bankB = WasteBank::factory()->create(['code' => 'BS101']);
        $admin = $this->makeUser('admin');
        $admin->wasteBanksAsStaff()->attach($bankA);

        $citizen = app(BankMembershipService::class)->createMember($admin, [
            'name' => 'Citizen A',
            'number' => 'MEMBER-'.Str::upper(Str::random(8)),
            'email' => 'citizen-a@example.test',
            'password' => 'password',
            'district_id' => $admin->district_id,
            'sub_district_id' => $admin->sub_district_id,
            'address' => 'Address',
            'waste_bank_id' => $bankB->id,
            'role' => 'admin',
            'balance' => 999999,
        ]);

        $this->assertTrue($citizen->hasRole('user'));
        $this->assertFalse($citizen->hasAnyRole(['admin', 'super_admin']));
        $this->assertDatabaseHas('waste_bank_members', ['waste_bank_id' => $bankA->id, 'user_id' => $citizen->id]);
        $this->assertDatabaseMissing('waste_bank_members', ['waste_bank_id' => $bankB->id, 'user_id' => $citizen->id]);
        $this->assertSame(0, $citizen->balance);
        $this->assertDatabaseMissing('waste_bank_staff', ['user_id' => $citizen->id]);
    }

    public function test_super_admin_can_create_new_bank_admin_without_membership(): void
    {
        $super = $this->makeUser('super_admin');
        $bank = WasteBank::factory()->create(['code' => 'BS100']);

        $assignment = app(AdminMembershipService::class)->createBankAdmin($super, $bank, [
            'name' => 'New Bank Admin',
            'number' => 'ADMIN-'.Str::upper(Str::random(8)),
            'email' => 'new-admin@example.test',
            'password' => 'password',
            'district_id' => $super->district_id,
            'sub_district_id' => $super->sub_district_id,
            'status' => 1,
        ]);

        $admin = User::whereEmail('new-admin@example.test')->firstOrFail();
        $this->assertSame($admin->id, $assignment->user_id);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertFalse($admin->hasRole('user'));
        $this->assertDatabaseHas('waste_bank_staff', ['waste_bank_id' => $bank->id, 'user_id' => $admin->id]);
        $this->assertDatabaseMissing('waste_bank_members', ['user_id' => $admin->id]);
        $this->assertSame(0, $admin->balance);
    }

    public function test_super_admin_has_bank_admin_create_and_bank_detail_actions(): void
    {
        $super = $this->makeUser('super_admin');
        $bank = WasteBank::factory()->create(['code' => 'BS100']);
        $admins = [$this->makeUser('admin'), $this->makeUser('admin'), $this->makeUser('admin')];
        foreach ($admins as $admin) {
            app(AdminMembershipService::class)->assignBankAdmin($super, $admin, $bank);
        }

        $this->actingAs($super)
            ->get(WasteBankStaffResource::getUrl('index', panel: 'adminPanel'))
            ->assertOk()
            ->assertSee('Tambah Admin');

        $this->actingAs($super)
            ->get(WasteBankResource::getUrl('edit', ['record' => $bank], panel: 'adminPanel'))
            ->assertOk()
            ->assertSee('Admin Bank Sampah')
            ->assertSee($admins[0]->name)
            ->assertSee($admins[1]->name)
            ->assertSee($admins[2]->name);
    }

    public function test_super_admin_can_open_global_user_management(): void
    {
        $super = $this->makeUser('super_admin');

        $this->actingAs($super)
            ->get(PlatformUserResource::getUrl('index', panel: 'adminPanel'))
            ->assertOk()
            ->assertSee('Pengguna')
            ->assertSee('Tambah Pengguna');
    }

    public function test_bank_admin_management_is_super_admin_only(): void
    {
        $admin = $this->makeUser('admin');
        $this->assignDefaultWasteBank($admin);
        $this->assertFalse($admin->can('create', \App\Models\WasteBankStaff::class));
        $this->assertFalse($this->makeUser('user')->can('create', \App\Models\WasteBankStaff::class));
    }

    public function test_role_transitions_preserve_membership_and_financial_state(): void
    {
        $super = $this->makeUser('super_admin');
        $citizen = $this->makeUser('user');
        $bank = WasteBank::factory()->create(['code' => 'BS100']);
        WasteBankMember::create(['waste_bank_id' => $bank->id, 'user_id' => $citizen->id, 'status' => 'active']);
        $before = [User::sum('balance'), DB::table('account_ledger_entries')->count()];

        app(UserRoleService::class)->changeRole($super, $citizen, 'admin', $bank);
        $this->assertTrue($citizen->refresh()->isBankAdmin());
        $this->assertDatabaseHas('waste_bank_staff', ['user_id' => $citizen->id, 'waste_bank_id' => $bank->id]);
        $this->assertDatabaseHas('waste_bank_members', ['user_id' => $citizen->id, 'waste_bank_id' => $bank->id]);

        app(UserRoleService::class)->changeRole($super, $citizen->refresh(), 'user');
        $this->assertTrue($citizen->refresh()->hasRole('user'));
        $this->assertDatabaseMissing('waste_bank_staff', ['user_id' => $citizen->id]);
        $this->assertDatabaseHas('waste_bank_members', ['user_id' => $citizen->id, 'waste_bank_id' => $bank->id]);
        $this->assertSame($before, [User::sum('balance'), DB::table('account_ledger_entries')->count()]);
    }

    public function test_last_active_super_admin_cannot_be_demoted(): void
    {
        $super = $this->makeUser('super_admin');
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(UserRoleService::class)->changeRole($super, $super, 'admin', WasteBank::factory()->create(['code' => 'BS100']));
    }

    private function makeUser(string $role): User
    {
        $district = DB::table('districts')->insertGetId(['name' => Str::random(8), 'created_at' => now(), 'updated_at' => now()]);
        $subDistrict = DB::table('sub_districts')->insertGetId(['district_id' => $district, 'name' => Str::random(8), 'created_at' => now(), 'updated_at' => now()]);
        $user = User::create([
            'name' => ucfirst($role),
            'number' => Str::upper($role).'-'.Str::upper(Str::random(8)),
            'email' => Str::lower(Str::random(12)).'@example.test',
            'password' => 'password',
            'district_id' => $district,
            'sub_district_id' => $subDistrict,
            'status' => 1,
        ]);
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user->refresh();
    }
}
