<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteDeposit;
use App\Models\WasteDepositItem;
use App\Models\WasteItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_own_balance_and_safe_profile_action(): void
    {
        $user = $this->createUser('user', balance: 1017800);
        $before = $this->financialState();

        $response = $this->actingAs($user)->get('/user/user-dashboard');

        $response->assertOk()
            ->assertSee('Halo, '.$user->name)
            ->assertSee('Anggota #'.$user->number)
            ->assertSee('Rp1.017.800')
            ->assertSee('Profil saya')
            ->assertSee('/user/profile');
        $this->assertSame($before, $this->financialState());
    }

    public function test_dashboard_uses_historical_snapshots_and_only_shows_own_deposits(): void
    {
        $user = $this->createUser('user');
        $otherUser = $this->createUser('user');
        $item = WasteItem::query()->create([
            'category' => 'Current Master Name',
            'output' => 'Kriya',
            'unit' => 'Kilogram (Kg)',
            'price' => 999999,
        ]);
        $this->createDeposit($user, '2026-09-21', 'posted', $item, 'Historical Bottle', 3000, '2.500', 7500);
        $this->createDeposit($otherUser, '2026-09-22', 'posted', $item, 'Private Deposit', 3000, '1.000', 3000);

        $item->update(['category' => 'Renamed Current Master', 'price' => 888888]);

        $response = $this->actingAs($user)->get('/user/user-dashboard');

        $response->assertOk()
            ->assertSee('Historical Bottle')
            ->assertSee('2.500 Kg')
            ->assertSee('Rp3.000 / Kg')
            ->assertSee('Rp7.500')
            ->assertDontSee('Renamed Current Master')
            ->assertDontSee('Private Deposit')
            ->assertDontSee('Rp888.888');
    }

    public function test_cancelled_deposit_is_presented_as_cancelled_not_active_income(): void
    {
        $user = $this->createUser('user');
        $this->createDeposit(
            $user,
            '2026-09-21',
            'cancelled',
            null,
            null,
            null,
            null,
            4500,
            'Kesalahan penimbangan',
        );

        $response = $this->actingAs($user)->get('/user/user-dashboard');

        $response->assertOk()
            ->assertSee('Dibatalkan')
            ->assertSee('Setoran ini telah dibatalkan.')
            ->assertSee('Alasan: Kesalahan penimbangan')
            ->assertSee('Rp4.500')
            ->assertDontSee('+Rp4.500');
    }

    public function test_dashboard_handles_deposit_without_items(): void
    {
        $user = $this->createUser('user');
        $this->createDeposit($user, '2026-09-21', 'posted', null, null, null, null, 0);

        $response = $this->actingAs($user)->get('/user/user-dashboard');

        $response->assertOk()
            ->assertSee('Detail item setoran belum tersedia.')
            ->assertDontSee('No records found');
    }

    public function test_dashboard_shows_friendly_empty_state(): void
    {
        $user = $this->createUser('user');
        $emptyResponse = $this->actingAs($user)->get('/user/user-dashboard');

        $emptyResponse->assertOk()
            ->assertSee('Belum ada setoran')
            ->assertSee('Riwayat setoran sampah Anda akan muncul di sini setelah transaksi dicatat oleh petugas.');
    }

    public function test_dashboard_limits_recent_deposits_to_five(): void
    {
        $user = $this->createUser('user');

        foreach (range(1, 6) as $number) {
            $this->createDeposit(
                $user,
                sprintf('2026-09-%02d', $number),
                'posted',
                null,
                'Setoran '.$number,
                null,
                '1.000',
                $number,
            );
        }

        $response = $this->actingAs($user)->get('/user/user-dashboard');

        $response->assertOk()
            ->assertSee('Setoran 6')
            ->assertSee('Setoran 2')
            ->assertDontSee('Setoran 1');
    }

    public function test_dashboard_renders_long_identity_and_large_balance_without_losing_information(): void
    {
        $user = $this->createUser('user', balance: 9999999999);
        $user->forceFill([
            'name' => str_repeat('Nama Pengguna Sangat Panjang ', 5),
            'number' => 'ANGGOTA-'.str_repeat('1234567890', 5),
        ])->save();

        $response = $this->actingAs($user)->get('/user/user-dashboard');

        $response->assertOk()
            ->assertSee($user->name)
            ->assertSee('Anggota #'.$user->number)
            ->assertSee('Rp9.999.999.999');
    }

    /** @return array<string, int> */
    private function financialState(): array
    {
        return [
            'balance' => (int) DB::table('users')->sum('balance'),
            'ledger' => DB::table('account_ledger_entries')->count(),
            'deposits' => DB::table('waste_deposits')->count(),
            'items' => DB::table('waste_deposit_items')->count(),
            'withdrawals' => DB::table('withdrawals')->count(),
        ];
    }

    private function createDeposit(
        User $user,
        string $date,
        string $status,
        ?WasteItem $wasteItem,
        ?string $snapshotName,
        ?int $unitPrice,
        ?string $quantity,
        int $subtotal,
        ?string $cancellationReason = null,
    ): WasteDeposit {
        $deposit = new WasteDeposit;
        $deposit->forceFill([
            'user_id' => $user->id,
            'waste_bank_id' => WasteBank::factory()->create()->id,
            'deposit_date' => $date,
            'total_amount' => $subtotal,
            'status' => $status,
            'posted_at' => $status === 'posted' ? now() : null,
            'cancelled_at' => $status === 'cancelled' ? now() : null,
            'cancellation_reason' => $cancellationReason,
        ])->save();

        if ($wasteItem || $snapshotName !== null) {
            $item = new WasteDepositItem;
            $item->forceFill([
                'waste_deposit_id' => $deposit->id,
                'waste_item_id' => $wasteItem?->id,
                'waste_name_snapshot' => $snapshotName,
                'category_snapshot' => null,
                'unit_snapshot' => $wasteItem ? 'Kilogram (Kg)' : null,
                'unit_price_snapshot' => $unitPrice,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ])->save();
        }

        return $deposit;
    }

    private function createUser(string $role, int $status = 1, int $balance = 0): User
    {
        $districtId = DB::table('districts')->insertGetId([
            'name' => 'Dashboard District '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => 'Dashboard Subdistrict '.uniqid(),
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
        $user->forceFill(['balance' => $balance])->save();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user->refresh();
    }
}
