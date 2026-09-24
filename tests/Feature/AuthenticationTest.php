<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login as AdminLogin;
use App\Filament\UserPanel\Pages\Auth\Login as UserLogin;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_reaches_the_user_dashboard(): void
    {
        $user = $this->createUser('user');

        $login = $this->loginComponent('userPanel', UserLogin::class, $user->email, 'password123');

        $login->assertRedirect('/user/user-dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get('/user/user-dashboard')->assertOk();
    }

    public function test_user_login_rejects_invalid_inactive_and_wrong_role_accounts(): void
    {
        $user = $this->createUser('user');
        $inactiveUser = $this->createUser('user', 0);
        $admin = $this->createUser('admin');

        $invalidLogin = $this->loginComponent('userPanel', UserLogin::class, $user->email, 'wrong-password')
            ->assertHasErrors(['data.email']);
        $this->assertSame('Email atau password tidak sesuai.', $invalidLogin->errors()->first('data.email'));
        $this->loginComponent('userPanel', UserLogin::class, $inactiveUser->email, 'password123')
            ->assertHasErrors(['data.email']);
        $this->loginComponent('userPanel', UserLogin::class, $admin->email, 'password123')
            ->assertHasErrors(['data.email']);

        $this->assertGuest();
    }

    public function test_active_admin_can_login_and_reaches_the_admin_dashboard(): void
    {
        $admin = $this->createUser('admin');

        $login = $this->loginComponent('adminPanel', AdminLogin::class, $admin->email, 'password123');

        $login->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk();
    }

    public function test_admin_login_rejects_ordinary_and_inactive_admin_accounts(): void
    {
        $user = $this->createUser('user');
        $inactiveAdmin = $this->createUser('admin', 0);

        $this->loginComponent('adminPanel', AdminLogin::class, $user->email, 'password123')
            ->assertHasErrors(['data.email']);
        $this->loginComponent('adminPanel', AdminLogin::class, $inactiveAdmin->email, 'password123')
            ->assertHasErrors(['data.email']);

        $this->assertGuest();
    }

    public function test_each_panel_logout_requires_csrf_and_returns_to_its_own_login(): void
    {
        $user = $this->createUser('user');
        $admin = $this->createUser('admin');

        $this->actingAs($user)->post('/user/logout')->assertStatus(419);
        $this->withSession(['_token' => 'user-token'])
            ->post('/user/logout', ['_token' => 'user-token'])
            ->assertRedirect('/user/login');
        $this->assertGuest();

        $this->actingAs($admin)->post('/admin/logout')->assertStatus(419);
        $this->withSession(['_token' => 'admin-token'])
            ->post('/admin/logout', ['_token' => 'admin-token'])
            ->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_login_rate_limiting_remains_enabled(): void
    {
        $user = $this->createUser('user');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->loginComponent('userPanel', UserLogin::class, $user->email, 'wrong-password')
                ->assertHasErrors(['data.email']);
        }

        $this->loginComponent('userPanel', UserLogin::class, $user->email, 'wrong-password')
            ->assertNotified('Terlalu banyak percobaan masuk.');
    }

    public function test_auth_stylesheet_does_not_leak_into_the_user_panel(): void
    {
        $user = $this->createUser('user');

        $this->get('/user/login')->assertSee('/css/auth.css', false);

        $this->actingAs($user)
            ->get('/user/user-dashboard')
            ->assertOk()
            ->assertDontSee('/css/auth.css', false);
    }

    public function test_auth_stylesheet_does_not_leak_into_the_admin_panel(): void
    {
        $admin = $this->createUser('admin');

        $this->get('/admin/login')->assertSee('/css/auth.css', false);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertDontSee('/css/auth.css', false);
    }

    private function loginComponent(string $panel, string $component, string $email, string $password): \Livewire\Features\SupportTesting\Testable
    {
        Filament::setCurrentPanel(Filament::getPanel($panel));

        return Livewire::test($component)
            ->set('data.email', $email)
            ->set('data.password', $password)
            ->call('authenticate');
    }

    private function createUser(string $role, int $status = 1): User
    {
        $districtId = \DB::table('districts')->insertGetId([
            'name' => 'Authentication District '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = \DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => 'Authentication Subdistrict '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->create([
            'name' => ucfirst($role).' Auth User',
            'number' => strtoupper($role).'-'.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => Hash::make('password123'),
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => $status,
        ]);
        $user->assignRole(Role::findOrCreate($role, 'web'));
        if ($role === 'admin') {
            $this->assignDefaultWasteBank($user);
        }

        return $user->refresh();
    }
}
