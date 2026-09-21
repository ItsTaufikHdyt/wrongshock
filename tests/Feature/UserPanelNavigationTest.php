<?php

namespace Tests\Feature;

use App\Filament\UserPanel\Pages\UserDashboard;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserPanelNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_reaches_the_single_user_dashboard_from_panel_home(): void
    {
        $user = $this->createUser('user');
        $before = $this->financialCounts();

        $this->actingAs($user)
            ->get('/user')
            ->assertRedirect('/user/user-dashboard');

        $this->actingAs($user)
            ->get('/user/user-dashboard')
            ->assertOk();

        $this->assertContains(UserDashboard::class, Filament::getPanel('userPanel')->getPages());
        $this->assertNotContains(\Filament\Pages\Dashboard::class, Filament::getPanel('userPanel')->getPages());
        $this->assertSame([], Filament::getPanel('userPanel')->getWidgets());
        $this->assertSame($before, $this->financialCounts());
    }

    public function test_inactive_user_cannot_open_the_user_panel(): void
    {
        $user = $this->createUser('user', 0);

        $this->actingAs($user)->get('/user')->assertForbidden();
    }

    public function test_non_user_role_cannot_open_the_user_panel(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)->get('/user')->assertForbidden();
    }

    /** @return array<string, int> */
    private function financialCounts(): array
    {
        return [
            'users' => User::query()->count(),
            'ledger' => \DB::table('account_ledger_entries')->count(),
            'deposits' => \DB::table('waste_deposits')->count(),
            'items' => \DB::table('waste_deposit_items')->count(),
            'withdrawals' => \DB::table('withdrawals')->count(),
        ];
    }

    private function createUser(string $role, int $status = 1): User
    {
        $districtId = \DB::table('districts')->insertGetId([
            'name' => 'Navigation District '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = \DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => 'Navigation Subdistrict '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->create([
            'name' => ucfirst($role).' User',
            'number' => strtoupper($role).'-'.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => Hash::make('password123'),
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => $status,
        ]);
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user->refresh();
    }
}
