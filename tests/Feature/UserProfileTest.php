<?php

namespace Tests\Feature;

use App\Filament\UserPanel\Pages\Auth\Profile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('userPanel'));
    }

    public function test_profile_opens_directly_without_user_crud_and_legacy_routes_redirect(): void
    {
        $user = $this->createUser('user');
        $other = $this->createUser('user');

        $this->actingAs($user)->get('/user/profile')
            ->assertOk()
            ->assertSee('Profil Saya')
            ->assertSee('Informasi Pribadi')
            ->assertSee('Keamanan Akun')
            ->assertSee('Nama Lengkap')
            ->assertSee('Alamat Lengkap')
            ->assertSee('Upload Foto')
            ->assertSee('Simpan Perubahan')
            ->assertDontSee('Edit User')
            ->assertDontSee('Create User')
            ->assertDontSee('Delete');

        $this->actingAs($user)->get('/user/users')->assertRedirect('/user/profile');
        $this->actingAs($user)->get('/user/users/'.$other->id.'/edit')->assertRedirect('/user/profile');
        $this->actingAs($user)->get('/user/profile')->assertDontSee($other->email);
    }

    public function test_inactive_and_role_revoked_users_are_denied(): void
    {
        $inactive = $this->createUser('user', status: 0);
        $revoked = $this->createUser('user');
        $revoked->syncRoles([]);

        $this->actingAs($inactive)->get('/user/profile')->assertForbidden();
        $this->actingAs($revoked)->get('/user/profile')->assertForbidden();
    }

    public function test_profile_updates_allowed_fields_but_not_system_or_financial_fields(): void
    {
        $user = $this->createUser('user', balance: 125000);
        [$districtId, $subDistrictId] = $this->createLocation('New');
        $before = $this->financialState();

        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('data.name', 'Nama Baru')
            ->set('data.email', 'nama.baru@example.test')
            ->set('data.district_id', $districtId)
            ->set('data.sub_district_id', $subDistrictId)
            ->set('data.address', 'Jalan Lingkungan Nomor 10')
            ->set('data.number', 'HACKED-NUMBER')
            ->set('data.balance', 999999999)
            ->set('data.status', 0)
            ->set('data.roles', ['admin'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Profil berhasil diperbarui.');

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('nama.baru@example.test', $user->email);
        $this->assertSame($districtId, $user->district_id);
        $this->assertSame($subDistrictId, $user->sub_district_id);
        $this->assertSame('Jalan Lingkungan Nomor 10', $user->address);
        $this->assertNotSame('HACKED-NUMBER', $user->number);
        $this->assertSame(125000, $user->balance);
        $this->assertSame(1, $user->status);
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertSame($before, $this->financialState());
    }

    public function test_duplicate_email_and_invalid_location_are_rejected(): void
    {
        $user = $this->createUser('user');
        $other = $this->createUser('user');
        [$districtId] = $this->createLocation('Mismatch A');
        [, $wrongSubDistrictId] = $this->createLocation('Mismatch B');

        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('data.email', $other->email)
            ->set('data.district_id', $districtId)
            ->set('data.sub_district_id', $wrongSubDistrictId)
            ->call('save')
            ->assertHasFormErrors(['email', 'sub_district_id']);

        $user->refresh();
        $this->assertNotSame($other->email, $user->email);
        $this->assertNotSame($districtId, $user->district_id);
    }

    public function test_empty_password_is_preserved_and_confirmed_password_is_hashed(): void
    {
        $user = $this->createUser('user');
        $originalHash = $user->password;
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('data.name', 'Tanpa Ganti Password')
            ->set('data.password', null)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($originalHash, $user->fresh()->password);

        Livewire::test(Profile::class)
            ->set('data.password', 'password-baru-aman')
            ->set('data.passwordConfirmation', 'tidak-sama')
            ->call('save')
            ->assertHasFormErrors(['password']);

        $this->assertSame($originalHash, $user->fresh()->password);

        Livewire::test(Profile::class)
            ->set('data.password', 'password-baru-aman')
            ->set('data.passwordConfirmation', 'password-baru-aman')
            ->call('save')
            ->assertHasNoFormErrors();

        $newHash = $user->fresh()->password;
        $this->assertNotSame('password-baru-aman', $newHash);
        $this->assertTrue(Hash::check('password-baru-aman', $newHash));
    }

    public function test_profile_photo_is_optional_and_existing_photo_remains_without_replacement(): void
    {
        Storage::fake('public');
        $user = $this->createUser('user');
        $path = 'profile-images/'.$user->id.'/existing.jpg';
        Storage::disk('public')->put($path, 'existing-image');
        $user->forceFill(['image' => $path])->save();
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('data.name', 'Foto Tetap')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($path, $user->fresh()->image);
        Storage::disk('public')->assertExists($path);
    }

    public function test_jpeg_png_and_webp_profile_photos_are_accepted(): void
    {
        Storage::fake('public');
        $user = $this->createUser('user');
        $this->actingAs($user);

        $files = [
            UploadedFile::fake()->image('avatar.jpg', 120, 120)->size(500),
            UploadedFile::fake()->image('avatar.png', 120, 120)->size(500),
        ];

        if (function_exists('imagewebp')) {
            $files[] = UploadedFile::fake()->image('avatar.webp', 120, 120)->size(500);
        }

        foreach ($files as $file) {
            Livewire::test(Profile::class)
                ->fillForm(['image' => $file])
                ->call('save')
                ->assertHasNoFormErrors();

            $path = $user->fresh()->image;
            $this->assertStringStartsWith('profile-images/'.$user->id.'/', $path);
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_invalid_or_oversized_photo_is_rejected_without_removing_existing_photo(): void
    {
        Storage::fake('public');
        $user = $this->createUser('user');
        $path = 'profile-images/'.$user->id.'/current.jpg';
        Storage::disk('public')->put($path, 'current-image');
        $user->forceFill(['image' => $path])->save();
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->fillForm(['image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')])
            ->call('save');

        Livewire::test(Profile::class)
            ->fillForm(['image' => UploadedFile::fake()->image('large.jpg')->size(2049)])
            ->call('save');

        $this->assertSame($path, $user->fresh()->image);
        Storage::disk('public')->assertExists($path);
    }

    public function test_user_cannot_assign_another_users_profile_photo_path(): void
    {
        Storage::fake('public');
        $user = $this->createUser('user');
        $other = $this->createUser('user');
        $otherPath = 'profile-images/'.$other->id.'/private.jpg';
        Storage::disk('public')->put($otherPath, 'private-image');
        $other->forceFill(['image' => $otherPath])->save();
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('data.image', [$otherPath])
            ->call('save')
            ->assertForbidden();

        $this->assertNull($user->fresh()->image);
        $this->assertSame($otherPath, $other->fresh()->image);
        Storage::disk('public')->assertExists($otherPath);
    }

    /** @return array<string, int> */
    private function financialState(): array
    {
        return [
            'balance' => (int) User::query()->sum('balance'),
            'ledger' => DB::table('account_ledger_entries')->count(),
            'deposits' => DB::table('waste_deposits')->count(),
            'items' => DB::table('waste_deposit_items')->count(),
            'withdrawals' => DB::table('withdrawals')->count(),
        ];
    }

    /** @return array{int, int} */
    private function createLocation(string $prefix): array
    {
        $districtId = DB::table('districts')->insertGetId([
            'name' => $prefix.' District '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => $prefix.' Subdistrict '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$districtId, $subDistrictId];
    }

    private function createUser(string $role, int $status = 1, int $balance = 0): User
    {
        [$districtId, $subDistrictId] = $this->createLocation(ucfirst($role));

        $user = User::query()->create([
            'name' => ucfirst($role).' Profile',
            'number' => strtoupper($role).'-'.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => Hash::make('password123'),
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'address' => 'Alamat awal',
            'status' => $status,
        ]);
        $user->forceFill(['balance' => $balance])->save();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user->refresh();
    }
}
