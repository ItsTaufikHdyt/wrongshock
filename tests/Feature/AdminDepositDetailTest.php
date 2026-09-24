<?php

namespace Tests\Feature;

use App\Filament\Resources\WasteDepositResource\Pages\ListWasteDeposits;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDepositDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_deposit_view_action(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('user');
        $depositId = $this->createDeposit($member, 'posted', 7500);
        $this->createItem($depositId, 'Botol Plastik', 'Kriya', 'Kilogram (Kg)', 3000, '2.500', 7500);
        Filament::setCurrentPanel(Filament::getPanel('adminPanel'));

        Livewire::actingAs($admin)
            ->test(ListWasteDeposits::class)
            ->callTableAction('view', $depositId)
            ->assertSee('Informasi Setoran')
            ->assertSee('Detail Sampah')
            ->assertSee('Botol Plastik')
            ->assertSee('Kilogram (Kg)')
            ->assertSee('Rp3.000')
            ->assertSee('Rp7.500')
            ->assertSee('Berhasil')
            ->assertSee('2,5');
    }

    public function test_ordinary_inactive_and_revoked_admins_cannot_open_admin_deposit_list(): void
    {
        $user = $this->createUser('user');
        $inactiveAdmin = $this->createUser('admin', 0);
        $revokedAdmin = $this->createUser('admin');
        $revokedAdmin->removeRole('admin');

        $this->actingAs($user)->get('/admin/waste-deposits')->assertForbidden();
        $this->actingAs($inactiveAdmin)->get('/admin/waste-deposits')->assertForbidden();
        $this->actingAs($revokedAdmin)->get('/admin/waste-deposits')->assertForbidden();
    }

    public function test_cancelled_and_draft_details_render_their_status_safely(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('user');
        $cancelledId = $this->createDeposit($member, 'cancelled', 1500, 'Setoran dibatalkan');
        $draftId = $this->createDeposit($member, 'draft', 0);

        $this->openDetail($admin, $cancelledId)
            ->assertSee('Dibatalkan')
            ->assertSee('Setoran dibatalkan');
        $this->openDetail($admin, $draftId)->assertSee('Draft');
    }

    public function test_multiple_items_and_historical_price_render_from_snapshots(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('user');
        $firstMasterId = $this->createWasteMaster('Botol Plastik', 3000);
        $secondMasterId = $this->createWasteMaster('Kardus', 2000);
        $depositId = $this->createDeposit($member, 'posted', 12000);
        $this->createItem($depositId, 'Botol Plastik Snapshot', 'Kriya', 'Kilogram (Kg)', 3000, '2', 6000, $firstMasterId);
        $this->createItem($depositId, 'Kardus Snapshot', 'Kriya', 'Kilogram (Kg)', 2000, '3', 6000, $secondMasterId);
        DB::table('waste_items')->where('id', $firstMasterId)->update(['price' => 9000]);

        $this->openDetail($admin, $depositId)
            ->assertSee('Botol Plastik Snapshot')
            ->assertSee('Kardus Snapshot')
            ->assertSee('Rp3.000')
            ->assertSee('Rp2.000')
            ->assertSee('Rp12.000')
            ->assertDontSee('Rp9.000');
    }

    public function test_deleted_waste_master_does_not_break_snapshot_detail(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('user');
        $masterId = $this->createWasteMaster('Master Lama', 1100);
        $depositId = $this->createDeposit($member, 'posted', 2200);
        $this->createItem($depositId, 'Snapshot Tetap Ada', 'Kriya', 'Pcs', 1100, '2', 2200, $masterId);
        DB::table('waste_items')->where('id', $masterId)->delete();

        $this->openDetail($admin, $depositId)
            ->assertSee('Snapshot Tetap Ada')
            ->assertSee('Pcs')
            ->assertSee('Rp1.100')
            ->assertSee('Rp2.200');
    }

    public function test_deposit_without_items_shows_safe_empty_state(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('user');
        $depositId = $this->createDeposit($member, 'draft', 0);

        $this->openDetail($admin, $depositId)
            ->assertSee('Detail item setoran tidak tersedia.');
    }

    public function test_opening_detail_has_no_financial_side_effects(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('user');
        $depositId = $this->createDeposit($member, 'posted', 7500);
        $this->createItem($depositId, 'Snapshot Read Only', 'Kriya', 'Kilogram (Kg)', 3000, '2.500', 7500);
        $before = [
            'balance' => DB::table('users')->where('id', $member->id)->value('balance'),
            'deposits' => DB::table('waste_deposits')->count(),
            'items' => DB::table('waste_deposit_items')->count(),
            'ledger_count' => DB::table('account_ledger_entries')->count(),
            'ledger_net' => DB::table('account_ledger_entries')->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) AS net")->value('net'),
        ];

        $this->openDetail($admin, $depositId);

        $this->assertSame($before, [
            'balance' => DB::table('users')->where('id', $member->id)->value('balance'),
            'deposits' => DB::table('waste_deposits')->count(),
            'items' => DB::table('waste_deposit_items')->count(),
            'ledger_count' => DB::table('account_ledger_entries')->count(),
            'ledger_net' => DB::table('account_ledger_entries')->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) AS net")->value('net'),
        ]);
    }

    private function openDetail(User $admin, int $depositId)
    {
        Filament::setCurrentPanel(Filament::getPanel('adminPanel'));

        return Livewire::actingAs($admin)
            ->test(ListWasteDeposits::class)
            ->callTableAction('view', $depositId);
    }

    private function createUser(string $role, int $status = 1): User
    {
        $districtId = DB::table('districts')->insertGetId([
            'name' => ucfirst($role).' Detail District '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => ucfirst($role).' Detail Subdistrict '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::query()->create([
            'name' => ucfirst($role).' Detail User',
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

    private function createDeposit(User $user, string $status, int $total, ?string $reason = null): int
    {
        return DB::table('waste_deposits')->insertGetId([
            'user_id' => $user->id,
            'waste_bank_id' => DB::table('waste_banks')->where('code', 'BS001')->value('id'),
            'deposit_date' => now()->toDateString(),
            'total_amount' => $total,
            'status' => $status,
            'posted_at' => $status === 'posted' ? now() : null,
            'cancellation_reason' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createItem(int $depositId, string $name, string $category, string $unit, int $price, string $quantity, int $subtotal, ?int $masterId = null): void
    {
        DB::table('waste_deposit_items')->insert([
            'waste_deposit_id' => $depositId,
            'waste_item_id' => $masterId,
            'waste_name_snapshot' => $name,
            'category_snapshot' => $category,
            'unit_snapshot' => $unit,
            'unit_price_snapshot' => $price,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createWasteMaster(string $category, int $price): int
    {
        return DB::table('waste_items')->insertGetId([
            'category' => $category,
            'output' => 'Kriya',
            'unit' => 'Kilogram (Kg)',
            'price' => $price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
