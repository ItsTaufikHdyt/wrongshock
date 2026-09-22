<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WasteItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicHomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_is_public_and_contains_truthful_public_sections(): void
    {
        WasteItem::query()->create([
            'category' => 'Botol Plastik',
            'output' => 'Kriya',
            'unit' => 'Kilogram (Kg)',
            'price' => 1300,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Ubah Sampah')
            ->assertSee('Cara Kerja Wrongshock')
            ->assertSee('Harga Sampah Terbaru')
            ->assertSee('Botol Plastik')
            ->assertSee('Rp1.300')
            ->assertSee('Sampah Bersih')
            ->assertSee('Kenapa Wrongshock?')
            ->assertSee('Wrongshock — Bank Sampah Digital')
            ->assertSee('/user/login', false)
            ->assertSee('/register', false)
            ->assertDontSee('/admin', false)
            ->assertDontSee('10.000 pengguna')
            ->assertDontSee('50 ton sampah')
            ->assertDontSee('WhatsApp')
            ->assertDontSee('Ibu Siti');
    }

    public function test_empty_waste_master_has_a_friendly_state(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Informasi harga sampah belum tersedia.')
            ->assertSee('Harga dapat berubah sesuai pembaruan dari pengelola.');
    }

    public function test_homepage_never_exposes_member_private_data(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Buka Beranda')
            ->assertDontSee($user->name)
            ->assertDontSee($user->email)
            ->assertDontSee((string) $user->number)
            ->assertDontSee('Rp1.000.000')
            ->assertDontSee('/admin', false);
    }

    public function test_admin_does_not_receive_member_dashboard_cta_or_admin_navigation(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)->get('/')
            ->assertOk()
            ->assertDontSee('Buka Beranda')
            ->assertDontSee('/admin', false)
            ->assertSee('Harga Sampah Terbaru');
    }

    public function test_homepage_price_query_is_limited_and_deterministic(): void
    {
        foreach (range(1, 9) as $index) {
            WasteItem::query()->create([
                'category' => sprintf('Sampah %02d', $index),
                'output' => 'Kriya',
                'unit' => 'Kilogram (Kg)',
                'price' => $index * 100,
            ]);
        }

        $response = $this->get('/')->assertOk();

        $response->assertSee('Sampah 01')->assertSee('Sampah 08')->assertDontSee('Sampah 09');
    }

    private function createUser(string $role = 'user'): User
    {
        $districtId = \DB::table('districts')->insertGetId([
            'name' => 'Homepage District '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = \DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => 'Homepage Subdistrict '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->create([
            'name' => ucfirst($role).' Homepage User',
            'number' => strtoupper($role).'-'.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => Hash::make('password123'),
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => 1,
            'balance' => $role === 'user' ? 1000000 : 0,
        ]);
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user->refresh();
    }
}
