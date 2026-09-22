<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_is_public_and_integrated_with_public_surfaces(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Buat Akun Anggota')
            ->assertSee('Informasi Pribadi')
            ->assertSee('Lokasi')
            ->assertSee('Keamanan Akun')
            ->assertSee('Nama Lengkap')
            ->assertSee('Kecamatan')
            ->assertSee('Kelurahan')
            ->assertSee('Alamat Lengkap')
            ->assertSee('Daftar Sekarang')
            ->assertSee('/user/login', false)
            ->assertSee('/', false)
            ->assertSee('autocomplete="name"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee('Tampilkan password')
            ->assertDontSee('admin/login')
            ->assertDontSee('balance')
            ->assertDontSee('role');
    }

    public function test_valid_registration_creates_hashed_inactive_member_with_zero_balance(): void
    {
        [$districtId, $subDistrictId] = $this->region();

        $this->withoutMiddleware()->post('/storeRegister', [
            'name' => 'New Member',
            'email' => 'new-member@example.test',
            'address' => 'Jalan Lingkungan 1',
            'district' => $districtId,
            'sub_district' => $subDistrictId,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/register')->assertSessionHas('success');

        $user = User::query()->where('email', 'new-member@example.test')->firstOrFail();

        $this->assertSame('New Member', $user->name);
        $this->assertNotSame('password123', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('password123', $user->getRawOriginal('password')));
        $this->assertSame(0, (int) $user->status);
        $this->assertSame(0, (int) $user->balance);
        $this->assertNotEmpty($user->number);
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasRole('admin'));
        $this->get('/register')->assertSee('menunggu aktivasi');
    }

    public function test_registration_validation_preserves_safe_input_but_never_passwords(): void
    {
        [$districtId, $subDistrictId] = $this->region();

        $this->withoutMiddleware()->from('/register')->post('/storeRegister', [
            'name' => 'Safe Name',
            'email' => 'safe@example.test',
            'address' => 'Safe Address',
            'district' => $districtId,
            'sub_district' => $subDistrictId,
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertRedirect('/register')->assertSessionHasErrors(['password']);

        $this->assertSame('Safe Name', session()->getOldInput('name'));
        $this->assertSame('safe@example.test', session()->getOldInput('email'));
        $this->assertSame($districtId, (int) session()->getOldInput('district'));
        $this->assertSame($subDistrictId, (int) session()->getOldInput('sub_district'));
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertFalse(session()->hasOldInput('password_confirmation'));
    }

    public function test_registration_rejects_duplicate_email_required_fields_and_invalid_regions(): void
    {
        $this->withoutMiddleware();
        [$districtId, $subDistrictId] = $this->region();
        $existing = $this->createExistingUser('existing@example.test', $districtId, $subDistrictId);
        [$otherDistrict] = $this->region();

        $this->from('/register')->post('/storeRegister', [
            'name' => 'Duplicate',
            'email' => $existing->email,
            'address' => 'Address',
            'district' => $districtId,
            'sub_district' => $subDistrictId,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/register')->assertSessionHasErrors('email');

        $this->from('/register')->post('/storeRegister', [])->assertRedirect('/register')
            ->assertSessionHasErrors(['name', 'email', 'address', 'district', 'sub_district', 'password']);

        $this->from('/register')->post('/storeRegister', [
            'name' => 'Wrong Region',
            'email' => 'wrong-region@example.test',
            'address' => 'Address',
            'district' => $otherDistrict,
            'sub_district' => $subDistrictId,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/register')->assertSessionHasErrors('sub_district');

        $this->from('/register')->post('/storeRegister', [
            'name' => 'Invalid Region',
            'email' => 'invalid-region@example.test',
            'address' => 'Address',
            'district' => 999999,
            'sub_district' => 999999,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/register')->assertSessionHasErrors(['district', 'sub_district']);
    }

    public function test_registration_ignores_protected_fields_and_requires_csrf(): void
    {
        [$districtId, $subDistrictId] = $this->region();

        $this->post('/storeRegister', [
            'name' => 'No Forgery',
            'email' => 'no-forgery@example.test',
            'address' => 'Address',
            'district' => $districtId,
            'sub_district' => $subDistrictId,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'balance' => 999999,
            'status' => 1,
            'role' => 'admin',
            'number' => 'FORGED-NUMBER',
        ])->assertStatus(419);

        $this->withoutMiddleware()->post('/storeRegister', [
            'name' => 'No Forgery',
            'email' => 'no-forgery@example.test',
            'address' => 'Address',
            'district' => $districtId,
            'sub_district' => $subDistrictId,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'balance' => 999999,
            'status' => 1,
            'role' => 'admin',
            'number' => 'FORGED-NUMBER',
        ]);

        $user = User::query()->where('email', 'no-forgery@example.test')->firstOrFail();
        $this->assertSame(0, (int) $user->balance);
        $this->assertSame(0, (int) $user->status);
        $this->assertNotSame('FORGED-NUMBER', $user->number);
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_public_location_endpoints_return_only_required_location_data(): void
    {
        [$districtId, $subDistrictId] = $this->region();
        $otherDistrict = $this->region()[0];

        $districts = $this->getJson('/api/districts')->assertOk();
        $districts->assertJsonPath((string) $districtId, fn ($name) => is_string($name));

        $subDistricts = $this->getJson('/api/subdistricts?district_id='.$districtId)
            ->assertOk()
            ->json();

        $this->assertArrayHasKey((string) $subDistrictId, $subDistricts);
        $this->assertNotContains((string) $otherDistrict, array_keys($subDistricts));
        $this->assertArrayNotHasKey('email', $subDistricts);
        $this->assertArrayNotHasKey('balance', $subDistricts);
    }

    public function test_registration_route_retains_existing_throttle(): void
    {
        $route = Route::getRoutes()->getByName('user.register');

        $this->assertNotNull($route);
        $this->assertContains('throttle:6,1', $route->middleware());
    }

    /** @return array{0: int, 1: int} */
    private function region(): array
    {
        $districtId = \DB::table('districts')->insertGetId([
            'name' => 'Registration District '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = \DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => 'Registration Subdistrict '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$districtId, $subDistrictId];
    }

    private function createExistingUser(string $email, int $districtId, int $subDistrictId): User
    {
        return User::query()->create([
            'name' => 'Existing Member',
            'number' => 'EXISTING-'.uniqid(),
            'email' => $email,
            'password' => Hash::make('password123'),
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => 0,
            'balance' => 0,
        ]);
    }
}
