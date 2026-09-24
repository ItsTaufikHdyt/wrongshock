<?php

namespace Tests\Feature;

use App\Filament\Resources\WasteBankResource;
use App\Filament\Resources\WasteBankStaffResource;
use App\Models\User;
use App\Models\WasteBank;
use App\Services\AdminMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndBankAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_access_distinguishes_platform_and_bank_admins(): void
    {
        $super = $this->user('super_admin');
        $bankAdmin = $this->user('admin');
        $this->assignDefaultWasteBank($bankAdmin);
        $unassigned = $this->user('admin');
        $ambiguous = $this->user('admin');
        $ambiguous->wasteBanks()->attach(WasteBank::factory()->create(['code' => 'BS100']));
        $ambiguous->wasteBanks()->attach(WasteBank::factory()->create(['code' => 'BS101']));

        $panel = \Filament\Facades\Filament::getPanel('adminPanel');
        $this->assertTrue($super->canAccessPanel($panel));
        $this->assertTrue($bankAdmin->canAccessPanel($panel));
        $this->assertFalse($unassigned->canAccessPanel($panel));
        $this->assertFalse($ambiguous->canAccessPanel($panel));
    }

    public function test_super_admin_can_access_platform_resources_but_bank_admin_cannot(): void
    {
        $super = $this->user('super_admin');
        $bankAdmin = $this->user('admin');
        $this->assignDefaultWasteBank($bankAdmin);

        $this->actingAs($super)
            ->get(WasteBankResource::getUrl('index', panel: 'adminPanel'))
            ->assertOk()
            ->assertSee('Bank Sampah');
        $this->actingAs($super)
            ->get(WasteBankStaffResource::getUrl('index', panel: 'adminPanel'))
            ->assertOk();

        $this->actingAs($bankAdmin)
            ->get(WasteBankResource::getUrl('index', panel: 'adminPanel'))
            ->assertRedirect();
        $response = $this->actingAs($bankAdmin)->get(WasteBankStaffResource::getUrl('index', panel: 'adminPanel'));
        $this->assertContains($response->status(), [302, 403]);
    }

    public function test_super_admin_can_create_bank_and_invalid_location_is_rejected(): void
    {
        $super = $this->user('super_admin');
        [$districtA, $subDistrictA] = $this->region('A');
        [$districtB] = $this->region('B');

        $bank = WasteBank::create([
            'code' => 'BS100',
            'name' => 'Bank Baru',
            'district_id' => $districtA,
            'sub_district_id' => $subDistrictA,
        ]);
        $this->assertDatabaseHas('waste_banks', ['id' => $bank->id, 'code' => 'BS100']);

        $this->actingAs($super);
        $this->expectException(ValidationException::class);
        WasteBank::create([
            'code' => 'BS101',
            'name' => 'Bank Invalid',
            'district_id' => $districtB,
            'sub_district_id' => $subDistrictA,
        ]);
    }

    public function test_bank_admin_assignment_is_transactional_and_unique(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');
        $bank = WasteBank::factory()->create(['code' => 'BS100']);

        $assignment = app(AdminMembershipService::class)->assignBankAdmin($super, $admin, $bank);
        $this->assertSame($admin->id, $assignment->user_id);
        $this->assertSame($bank->id, $assignment->waste_bank_id);

        $this->expectException(ValidationException::class);
        app(AdminMembershipService::class)->assignBankAdmin($super, $admin, $bank);
    }

    public function test_bank_admin_cannot_assign_or_modify_global_master_data(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');
        $this->assignDefaultWasteBank($admin);
        $wasteItem = new \App\Models\WasteItem;
        $district = new \App\Models\District;

        $this->assertTrue($super->can('update', $wasteItem));
        $this->assertFalse($admin->can('update', $wasteItem));
        $this->assertTrue($super->can('update', $district));
        $this->assertFalse($admin->can('update', $district));
    }

    public function test_role_transitions_remove_ambiguous_bank_admin_state(): void
    {
        $super = $this->user('super_admin');
        $citizen = $this->user('user');
        $bank = WasteBank::factory()->create(['code' => 'BS100']);

        app(AdminMembershipService::class)->promoteToBankAdmin($super, $citizen, $bank);
        $this->assertTrue($citizen->refresh()->isBankAdmin());
        $this->assertCount(1, $citizen->wasteBanks);

        app(AdminMembershipService::class)->promoteToSuperAdmin($super, $citizen);
        $this->assertTrue($citizen->refresh()->isPlatformAdmin());
        $this->assertCount(0, $citizen->wasteBanks);

        app(AdminMembershipService::class)->demoteToCitizen($super, $citizen);
        $this->assertTrue($citizen->refresh()->hasRole('user'));
        $this->assertFalse($citizen->hasAnyRole(['admin', 'super_admin']));
    }

    public function test_super_admin_dashboard_is_platform_scoped_and_not_bank_bs001(): void
    {
        $super = $this->user('super_admin');

        $this->actingAs($super)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Ringkasan Platform Wrongshock')
            ->assertSee('Semua Bank Sampah');
    }

    public function test_inactive_super_admin_is_denied(): void
    {
        $super = $this->user('super_admin', 0);

        $this->assertFalse($super->canAccessPanel(\Filament\Facades\Filament::getPanel('adminPanel')));
        $this->actingAs($super)->get('/admin')->assertForbidden();
    }

    private function user(string $role, int $status = 1): User
    {
        [$districtId, $subDistrictId] = $this->region(uniqid());
        $user = User::create([
            'name' => ucfirst($role).' Test',
            'number' => strtoupper($role).'-'.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => 'password',
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => $status,
        ]);
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user->refresh();
    }

    /** @return array{0:int,1:int} */
    private function region(string $suffix): array
    {
        $districtId = DB::table('districts')->insertGetId(['name' => 'District '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
        $subDistrictId = DB::table('sub_districts')->insertGetId(['district_id' => $districtId, 'name' => 'Subdistrict '.$suffix, 'created_at' => now(), 'updated_at' => now()]);

        return [$districtId, $subDistrictId];
    }
}
