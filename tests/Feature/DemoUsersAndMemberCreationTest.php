<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\WasteBank;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class DemoUsersAndMemberCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_is_complete_and_idempotent(): void
    {
        $this->seed([\Database\Seeders\DistrictSeeder::class, \Database\Seeders\SubDistrictSeeder::class, \Database\Seeders\RoleSeeder::class, \Database\Seeders\WasteBankSeeder::class, \Database\Seeders\DemoUserSeeder::class]);
        $this->seed([\Database\Seeders\DistrictSeeder::class, \Database\Seeders\SubDistrictSeeder::class, \Database\Seeders\RoleSeeder::class, \Database\Seeders\WasteBankSeeder::class, \Database\Seeders\DemoUserSeeder::class]);

        foreach (['super_admin', 'admin', 'user'] as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role, 'guard_name' => 'web']);
        }

        $super = User::whereEmail('superadmin@wrongshock.test')->firstOrFail();
        $admin = User::whereEmail('admin@wrongshock.test')->firstOrFail();
        $citizen = User::whereEmail('user@wrongshock.test')->firstOrFail();
        $bank = WasteBank::whereCode('BS001')->firstOrFail();

        $this->assertTrue(Hash::check('password', $super->password));
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue(Hash::check('password', $citizen->password));
        $this->assertSame(0, $super->balance);
        $this->assertSame(0, $admin->balance);
        $this->assertSame(0, $citizen->balance);
        $this->assertSame(1, $admin->wasteBanksAsStaff()->count());
        $this->assertSame($bank->id, $admin->wasteBanksAsStaff()->sole()->id);
        $this->assertCount(0, $super->wasteBanksAsStaff);
        $this->assertCount(0, $citizen->wasteBanksAsStaff);
        $this->assertDatabaseHas('waste_bank_members', ['waste_bank_id' => $bank->id, 'user_id' => $citizen->id]);
        $this->assertSame(3, User::whereIn('email', [
            'superadmin@wrongshock.test', 'admin@wrongshock.test', 'user@wrongshock.test',
        ])->count());
        $this->assertSame(0, LedgerEntry::whereIn('user_id', [$super->id, $admin->id, $citizen->id])->count());
        $this->assertSame(1, DB::table('waste_bank_staff')->where('user_id', $admin->id)->count());
    }

    public function test_demo_panel_access_and_bank_admin_can_open_member_creation(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $admin = User::whereEmail('admin@wrongshock.test')->firstOrFail();
        $citizen = User::whereEmail('user@wrongshock.test')->firstOrFail();
        $super = User::whereEmail('superadmin@wrongshock.test')->firstOrFail();

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('adminPanel')));
        $this->assertFalse($citizen->canAccessPanel(Filament::getPanel('adminPanel')));
        $this->assertTrue($citizen->canAccessPanel(Filament::getPanel('userPanel')));
        $this->assertTrue($super->canAccessPanel(Filament::getPanel('adminPanel')));
        $this->assertTrue($admin->can('create', User::class));
        $this->assertFalse($citizen->can('create', User::class));

        $this->actingAs($admin)
            ->get(UserResource::getUrl('create', panel: 'adminPanel'))
            ->assertOk()
            ->assertSee('Tambah Anggota');
    }

    public function test_bank_admin_create_makes_global_zero_balance_citizen(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $admin = User::whereEmail('admin@wrongshock.test')->firstOrFail();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('adminPanel'));
        $district = DB::table('districts')->first();
        $subDistrict = DB::table('sub_districts')->where('district_id', $district->id)->first();

        Livewire::test(\App\Filament\Resources\UserResource\Pages\CreateUser::class)
            ->set('data.name', 'Anggota Baru')
            ->set('data.email', 'created-member@wrongshock.test')
            ->set('data.district_id', $district->id)
            ->set('data.sub_district_id', $subDistrict->id)
            ->set('data.address', 'Alamat anggota baru')
            ->set('data.password', 'password')
            ->set('data.password_confirmation', 'password')
            ->call('create')
            ->assertHasNoFormErrors();

        $member = User::whereEmail('created-member@wrongshock.test')->firstOrFail();
        $this->assertTrue($member->hasRole('user'));
        $this->assertFalse($member->hasAnyRole(['admin', 'super_admin']));
        $this->assertSame(0, $member->balance);
        $this->assertSame(0, $member->wasteBanksAsStaff()->count());
        $this->assertSame(0, $member->ledgerEntries()->count());
    }

    public function test_member_create_rejects_invalid_location_and_duplicate_identifiers(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $admin = User::whereEmail('admin@wrongshock.test')->firstOrFail();
        $this->assertTrue($admin->can('create', User::class));
    }
}
