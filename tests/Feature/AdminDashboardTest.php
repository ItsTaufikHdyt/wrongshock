<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_view_operational_dashboard(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Selamat datang kembali')
            ->assertSee('Angka Utama')
            ->assertSee('Aktivitas Setoran')
            ->assertSee('Setoran Terbaru');
    }

    public function test_ordinary_inactive_and_revoked_admins_are_denied(): void
    {
        $user = $this->createUser('user');
        $inactiveAdmin = $this->createUser('admin', 0);
        $revokedAdmin = $this->createUser('admin');
        $revokedAdmin->removeRole('admin');

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($inactiveAdmin)->get('/admin')->assertForbidden();
        $this->actingAs($revokedAdmin)->get('/admin')->assertForbidden();
    }

    public function test_dashboard_counts_members_and_posted_monthly_financial_metrics_only(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('user');
        $this->createUser('user', 0);
        $this->createDeposit($member, 'posted', 125000, now()->toDateString());
        $this->createDeposit($member, 'cancelled', 900000, now()->toDateString());
        $this->createDeposit($member, 'draft', 800000, now()->toDateString());

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('>2<', false)
            ->assertSee('Rp 125.000')
            ->assertSee('Rp 900.000')
            ->assertSee('Dibatalkan')
            ->assertSee('Draft');
    }

    public function test_dashboard_uses_snapshot_category_after_master_item_is_deleted(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('user');
        $itemId = DB::table('waste_items')->insertGetId([
            'category' => 'Plastik Lama',
            'output' => 'Kriya',
            'unit' => 'Kilogram (Kg)',
            'price' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $depositId = $this->createDeposit($member, 'posted', 50000, now()->toDateString());
        DB::table('waste_deposit_items')->insert([
            'waste_deposit_id' => $depositId,
            'waste_item_id' => $itemId,
            'waste_name_snapshot' => 'Plastik Snapshot',
            'category_snapshot' => 'Plastik Snapshot',
            'unit_snapshot' => 'Kilogram (Kg)',
            'unit_price_snapshot' => 1000,
            'quantity' => 50,
            'subtotal' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('waste_items')->where('id', $itemId)->delete();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Plastik Snapshot')
            ->assertSee('Rp 50.000');
    }

    public function test_mixed_units_are_not_aggregated_as_a_fake_quantity_kpi(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('user');
        $depositId = $this->createDeposit($member, 'posted', 30000, now()->toDateString());
        DB::table('waste_deposit_items')->insert([
            'waste_deposit_id' => $depositId,
            'waste_item_id' => null,
            'category_snapshot' => 'Campuran',
            'unit_snapshot' => 'Kilogram (Kg)',
            'quantity' => 2,
            'subtotal' => 30000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Komposisi Setoran')
            ->assertDontSee('Sampah Terkumpul');
    }

    public function test_dashboard_surfaces_pending_withdrawals_and_inactive_members(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('user', 0);
        DB::table('withdrawals')->insert([
            'user_id' => $member->id,
            'amount' => 10000,
            'status' => 'pending',
            'withdrawal_date' => now()->toDateString(),
            'requested_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('1 akun anggota belum aktif')
            ->assertSee('1 penarikan menunggu proses');
    }

    public function test_dashboard_is_read_only(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('user');
        $depositId = $this->createDeposit($member, 'posted', 25000, now()->toDateString());
        $before = [
            'users' => DB::table('users')->count(),
            'deposits' => DB::table('waste_deposits')->count(),
            'items' => DB::table('waste_deposit_items')->count(),
            'ledger' => DB::table('account_ledger_entries')->count(),
        ];

        $this->actingAs($admin)->get('/admin')->assertOk();

        $this->assertSame($before, [
            'users' => DB::table('users')->count(),
            'deposits' => DB::table('waste_deposits')->count(),
            'items' => DB::table('waste_deposit_items')->count(),
            'ledger' => DB::table('account_ledger_entries')->count(),
        ]);
        $this->assertDatabaseHas('waste_deposits', ['id' => $depositId, 'status' => 'posted']);
    }

    private function createUser(string $role, int $status = 1): User
    {
        $districtId = DB::table('districts')->insertGetId([
            'name' => ucfirst($role).' Dashboard District '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => ucfirst($role).' Dashboard Subdistrict '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::query()->create([
            'name' => ucfirst($role).' Dashboard User',
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

    private function createDeposit(User $user, string $status, int $amount, string $date): int
    {
        return DB::table('waste_deposits')->insertGetId([
            'user_id' => $user->id,
            'deposit_date' => $date,
            'total_amount' => $amount,
            'status' => $status,
            'posted_at' => $status === 'posted' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
