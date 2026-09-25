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

class UserDepositHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_view_paginated_owned_history_in_newest_first_order(): void
    {
        $user = $this->createUser('user');
        $otherUser = $this->createUser('user');

        foreach (range(1, 11) as $number) {
            $this->createDeposit($user, sprintf('2026-09-%02d', $number), 'posted', 'Deposit '.str_pad($number, 2, '0', STR_PAD_LEFT));
        }
        $this->createDeposit($otherUser, '2026-09-30', 'posted', 'Private Deposit');

        $firstPage = $this->actingAs($user)->get('/user/setoran');
        $secondPage = $this->actingAs($user)->get('/user/setoran?page=2');

        $firstPage->assertOk()
            ->assertSee('Deposit 11')
            ->assertSee('Deposit 02')
            ->assertDontSee('Deposit 01')
            ->assertDontSee('Private Deposit')
            ->assertSee('Lihat detail');
        $secondPage->assertOk()->assertSee('Deposit 01');
    }

    public function test_status_filters_and_filtered_empty_state_work(): void
    {
        $user = $this->createUser('user');
        $this->createDeposit($user, '2026-09-21', 'posted', 'Berhasil Deposit');
        $this->createDeposit($user, '2026-09-20', 'cancelled', 'Cancelled Deposit', cancellationReason: 'Kesalahan penimbangan');
        $this->createDeposit($user, '2026-09-19', 'draft', 'Draft Deposit');

        $posted = $this->actingAs($user)->get('/user/setoran?status=posted');
        $cancelled = $this->actingAs($user)->get('/user/setoran?status=cancelled');
        $empty = $this->actingAs($user)->get('/user/setoran?status=cancelled&page=2');

        $posted->assertOk()
            ->assertSee('Berhasil Deposit')
            ->assertDontSee('Cancelled Deposit')
            ->assertDontSee('Draft Deposit');
        $cancelled->assertOk()
            ->assertSee('Cancelled Deposit')
            ->assertSee('Dibatalkan')
            ->assertDontSee('Berhasil Deposit');
        $empty->assertOk()->assertSee('Tidak ada setoran dengan status ini.');
    }

    public function test_history_and_detail_use_snapshots_after_master_changes_and_deletion(): void
    {
        $user = $this->createUser('user');
        $item = WasteItem::query()->create([
            'category' => 'Current Master Name',
            'output' => 'Kriya',
            'unit' => 'Kilogram (Kg)',
            'price' => 999999,
        ]);
        $deposit = $this->createDeposit($user, '2026-09-21', 'posted', 'Historical Bottle', $item, 3000, '2.500', 7500);

        $item->update(['category' => 'Renamed Current Master', 'price' => 888888]);
        $item->delete();

        $history = $this->actingAs($user)->get('/user/setoran');
        $detail = $this->actingAs($user)->get('/user/setoran/'.$deposit->id);

        $history->assertOk()
            ->assertSee('Historical Bottle')
            ->assertSee('2.500 Kg')
            ->assertDontSee('Renamed Current Master');
        $detail->assertOk()
            ->assertSee('Setoran Sampah')
            ->assertSee('21 September 2026')
            ->assertSee('Historical Bottle')
            ->assertSee('2.500 Kg')
            ->assertSee('Rp3.000 / Kg')
            ->assertSee('Rp7.500')
            ->assertDontSee('Rp888.888');
    }

    public function test_cancelled_deposit_detail_shows_reason_without_positive_income(): void
    {
        $user = $this->createUser('user');
        $deposit = $this->createDeposit(
            $user,
            '2026-09-18',
            'cancelled',
            'Kardus',
            null,
            1500,
            '3.000',
            4500,
            'Kesalahan penimbangan',
        );

        $response = $this->actingAs($user)->get('/user/setoran/'.$deposit->id);

        $response->assertOk()
            ->assertSee('Dibatalkan')
            ->assertSee('Alasan:')
            ->assertSee('Kesalahan penimbangan')
            ->assertSee('Setoran ini telah dibatalkan dan tidak lagi diperhitungkan ke saldo Anda.')
            ->assertSee('Rp4.500')
            ->assertDontSee('+Rp4.500');
    }

    public function test_empty_history_has_friendly_state_and_no_fake_create_action(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->get('/user/setoran');

        $response->assertOk()
            ->assertSee('Belum ada setoran')
            ->assertSee('Riwayat setoran sampah Anda akan muncul di sini setelah transaksi dicatat oleh petugas.')
            ->assertDontSee('Setor sekarang');
    }

    public function test_inactive_user_is_denied_and_other_users_detail_is_not_found(): void
    {
        $inactive = $this->createUser('user', 0);
        $user = $this->createUser('user');
        $otherUser = $this->createUser('user');
        $deposit = $this->createDeposit($otherUser, '2026-09-21', 'posted', 'Private Detail');

        $this->actingAs($inactive)->get('/user/setoran')->assertForbidden();
        $this->actingAs($user)->get('/user/setoran/'.$deposit->id)->assertNotFound();
    }

    public function test_history_and_detail_do_not_mutate_financial_data(): void
    {
        $user = $this->createUser('user');
        $deposit = $this->createDeposit($user, '2026-09-21', 'posted', 'Read Only Deposit');
        $before = $this->financialState();

        $this->actingAs($user)->get('/user/setoran');
        $this->actingAs($user)->get('/user/setoran/'.$deposit->id);

        $this->assertSame($before, $this->financialState());
    }

    public function test_long_transaction_content_remains_available_in_history_and_detail(): void
    {
        $user = $this->createUser('user');
        $longName = str_repeat('Kemasan Plastik Campuran Dengan Nama Panjang ', 5);
        $longReason = str_repeat('Catatan koreksi penimbangan yang perlu dijelaskan kepada anggota. ', 6);
        $deposit = $this->createDeposit(
            $user,
            '2026-09-21',
            'cancelled',
            $longName,
            unitPrice: 3000000,
            quantity: '999999.999',
            subtotal: 9999999999,
            cancellationReason: $longReason,
        );

        $history = $this->actingAs($user)->get('/user/setoran');
        $detail = $this->actingAs($user)->get('/user/setoran/'.$deposit->id);

        $history->assertOk()
            ->assertSee($longName)
            ->assertSee($longReason)
            ->assertSee('Rp9.999.999.999');
        $detail->assertOk()
            ->assertSee($longName)
            ->assertSee($longReason)
            ->assertSee('999999.999 Kg')
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
        ?string $snapshotName,
        ?WasteItem $wasteItem = null,
        ?int $unitPrice = null,
        ?string $quantity = '1.000',
        int $subtotal = 3000,
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

        if ($snapshotName !== null) {
            $item = new WasteDepositItem;
            $item->forceFill([
                'waste_deposit_id' => $deposit->id,
                'waste_item_id' => $wasteItem?->id,
                'waste_name_snapshot' => $snapshotName,
                'category_snapshot' => null,
                'unit_snapshot' => 'Kilogram (Kg)',
                'unit_price_snapshot' => $unitPrice,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ])->save();
        }

        return $deposit;
    }

    private function createUser(string $role, int $status = 1): User
    {
        $districtId = DB::table('districts')->insertGetId([
            'name' => 'History District '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => 'History Subdistrict '.uniqid(),
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
