<?php

namespace Tests\Feature;

use App\Filament\Resources\WasteDepositResource;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\WasteDeposit;
use App\Models\WasteItem;
use App\Services\DepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DepositServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_tests_use_isolated_sqlite_connection(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    private User $user;

    private User $admin;

    private WasteItem $wasteItem;

    protected function setUp(): void
    {
        parent::setUp();

        $districtId = DB::table('districts')->insertGetId([
            'name' => 'Test District',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => 'Test Subdistrict',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'number' => 'TEST-'.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => 'password',
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => 1,
        ]);
        $this->user->forceFill(['balance' => 0])->save();
        $this->user->assignRole(Role::findOrCreate('user', 'web'));

        $this->admin = User::create([
            'name' => 'Test Admin',
            'number' => 'ADMIN-'.uniqid(),
            'email' => uniqid().'@admin.example.test',
            'password' => 'password',
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => 1,
        ]);
        $this->admin->assignRole(Role::findOrCreate('admin', 'web'));
        $bank = $this->assignDefaultWasteBank($this->admin);
        \App\Models\WasteBankMember::create([
            'waste_bank_id' => $bank->id,
            'user_id' => $this->user->id,
            'joined_at' => now(),
            'status' => 'active',
        ]);
        Auth::login($this->admin);

        $this->wasteItem = WasteItem::create([
            'category' => 'Plastic',
            'output' => 'Kriya',
            'unit' => 'Kilogram (Kg)',
            'price' => 3000,
        ]);
    }

    public function test_posts_a_deposit_with_server_calculated_snapshot_and_credit(): void
    {
        $deposit = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '1.250'],
        ], $this->admin->id);

        $item = $deposit->items->sole();

        $this->assertSame(3750, $deposit->total_amount);
        $this->assertSame('posted', $deposit->status);
        $this->assertNotNull($deposit->posted_at);
        $this->assertSame('1.250', $item->quantity);
        $this->assertSame(3000, $item->unit_price_snapshot);
        $this->assertSame(3750, $item->subtotal);
        $this->assertDatabaseHas('account_ledger_entries', [
            'user_id' => $this->user->id,
            'type' => 'deposit_credit',
            'direction' => 'credit',
            'amount' => 3750,
            'reference_id' => $deposit->id,
        ]);
        $this->assertSame(3750, $this->user->refresh()->balance);
    }

    public function test_multiple_items_create_one_credit_for_the_authoritative_total(): void
    {
        $secondItem = WasteItem::create([
            'category' => 'Cardboard',
            'output' => 'Kriya',
            'unit' => 'Kilogram (Kg)',
            'price' => 2000,
        ]);

        $deposit = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '1.500'],
            ['waste_item_id' => $secondItem->id, 'quantity' => '2.250'],
        ]);

        $this->assertSame(9000, $deposit->total_amount);
        $this->assertSame([4500, 4500], $deposit->items->pluck('subtotal')->all());
        $this->assertSame(1, LedgerEntry::query()->where('reference_id', $deposit->id)->count());
        $this->assertSame(9000, $this->user->refresh()->balance);
    }

    public function test_master_price_wins_over_untrusted_item_values(): void
    {
        $deposit = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            [
                'waste_item_id' => $this->wasteItem->id,
                'quantity' => '1.000',
                'price' => 1,
                'subtotal' => 1,
                'total_amount' => 1,
            ],
        ]);

        $this->assertSame(3000, $deposit->total_amount);
        $this->assertSame(3000, $deposit->items->sole()->unit_price_snapshot);
    }

    public function test_snapshot_survives_master_changes(): void
    {
        $deposit = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '1.000'],
        ]);

        $this->wasteItem->update([
            'category' => 'Changed category',
            'output' => 'Changed output',
            'unit' => 'Kilogram (Kg)',
            'price' => 9999,
        ]);

        $item = $deposit->items->sole()->fresh();
        $this->assertSame('Plastic', $item->waste_name_snapshot);
        $this->assertSame('Kriya', $item->category_snapshot);
        $this->assertSame(3000, $item->unit_price_snapshot);
        $this->assertSame(3000, $item->subtotal);
    }

    public function test_fractional_quantities_and_half_up_rounding_are_exact(): void
    {
        $minimum = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '0.001'],
        ]);
        $first = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '0.250'],
        ]);
        $second = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '1.125'],
        ]);
        $maximum = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '999.999'],
        ]);

        $this->assertSame(3, $minimum->total_amount);
        $this->assertSame(750, $first->total_amount);
        $this->assertSame('0.250', $first->items->sole()->quantity);
        $this->assertSame(3375, $second->total_amount);
        $this->assertSame('999.999', $maximum->items->sole()->quantity);
        $this->assertSame(2999997, $maximum->total_amount);

        $this->wasteItem->update(['price' => 1]);
        $rounded = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '0.500'],
        ]);
        $this->assertSame(1, $rounded->total_amount);
    }

    public function test_invalid_quantity_creates_no_financial_records(): void
    {
        $this->expectException(ValidationException::class);

        try {
            app(DepositService::class)->post($this->user->id, '2026-09-21', [
                ['waste_item_id' => $this->wasteItem->id, 'quantity' => '0'],
            ]);
        } finally {
            $this->assertSame(0, DB::table('waste_deposits')->count());
            $this->assertSame(0, DB::table('waste_deposit_items')->count());
            $this->assertSame(0, DB::table('account_ledger_entries')->count());
            $this->assertSame(0, $this->user->refresh()->balance);
        }
    }

    public function test_negative_quantity_creates_no_financial_records(): void
    {
        $this->expectException(ValidationException::class);

        try {
            app(DepositService::class)->post($this->user->id, '2026-09-21', [
                ['waste_item_id' => $this->wasteItem->id, 'quantity' => '-1.000'],
            ]);
        } finally {
            $this->assertSame(0, DB::table('waste_deposits')->count());
            $this->assertSame(0, DB::table('waste_deposit_items')->count());
            $this->assertSame(0, DB::table('account_ledger_entries')->count());
            $this->assertSame(0, $this->user->refresh()->balance);
        }
    }

    public function test_invalid_waste_item_creates_no_financial_records(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        try {
            app(DepositService::class)->post($this->user->id, '2026-09-21', [
                ['waste_item_id' => 999999, 'quantity' => '1.000'],
            ]);
        } finally {
            $this->assertSame(0, DB::table('waste_deposits')->count());
            $this->assertSame(0, $this->user->refresh()->balance);
        }
    }

    public function test_unsupported_unit_creates_no_financial_records(): void
    {
        $this->wasteItem->update(['unit' => 'Gram']);
        $this->expectException(ValidationException::class);

        try {
            app(DepositService::class)->post($this->user->id, '2026-09-21', [
                ['waste_item_id' => $this->wasteItem->id, 'quantity' => '1.000'],
            ]);
        } finally {
            $this->assertSame(0, DB::table('waste_deposits')->count());
            $this->assertSame(0, $this->user->refresh()->balance);
        }
    }

    public function test_failure_during_ledger_creation_rolls_back_everything(): void
    {
        LedgerEntry::creating(static function (): never {
            throw new LogicException('forced test failure');
        });

        try {
            $this->expectException(LogicException::class);
            app(DepositService::class)->post($this->user->id, '2026-09-21', [
                ['waste_item_id' => $this->wasteItem->id, 'quantity' => '1.000'],
            ]);
        } finally {
            LedgerEntry::flushEventListeners();
        }

        $this->assertSame(0, DB::table('waste_deposits')->count());
        $this->assertSame(0, DB::table('waste_deposit_items')->count());
        $this->assertSame(0, DB::table('account_ledger_entries')->count());
        $this->assertSame(0, $this->user->refresh()->balance);
    }

    public function test_posted_deposit_has_one_credit_and_cannot_be_deleted_by_business_path(): void
    {
        $deposit = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '1.000'],
        ]);

        $this->assertSame(1, LedgerEntry::query()
            ->where('reference_type', $deposit::class)
            ->where('reference_id', $deposit->id)
            ->where('type', 'deposit_credit')
            ->count());
        $this->assertFalse(WasteDepositResource::canEdit($deposit));
        $this->assertFalse(WasteDepositResource::canDelete($deposit));
        $this->expectException(LogicException::class);
        $deposit->delete();
    }

    public function test_cancels_posted_deposit_with_one_reversal_and_restores_cached_balance(): void
    {
        $deposit = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '2.000'],
        ]);

        $cancelled = app(DepositService::class)->cancel($deposit, 'Duplicate weighing', $this->admin->id);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('Duplicate weighing', $cancelled->cancellation_reason);
        $this->assertDatabaseHas('account_ledger_entries', [
            'type' => 'deposit_credit',
            'direction' => 'credit',
            'amount' => 6000,
            'reference_id' => $deposit->id,
        ]);
        $this->assertDatabaseHas('account_ledger_entries', [
            'type' => 'deposit_reversal',
            'direction' => 'debit',
            'amount' => 6000,
            'reference_id' => $deposit->id,
        ]);
        $this->assertSame(0, $this->user->refresh()->balance);
    }

    public function test_cancellation_preserves_original_history_and_snapshots(): void
    {
        $deposit = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '2.000'],
        ]);
        $original = $deposit->load('items')->toArray();

        app(DepositService::class)->cancel($deposit, 'Correction required');
        $reloaded = $deposit->fresh('items');

        $this->assertNotNull($reloaded);
        $this->assertSame('cancelled', $reloaded->status);
        $this->assertSame($original['total_amount'], $reloaded->total_amount);
        $this->assertSame($original['items'][0]['waste_name_snapshot'], $reloaded->items->sole()->waste_name_snapshot);
        $this->assertSame($original['items'][0]['category_snapshot'], $reloaded->items->sole()->category_snapshot);
        $this->assertSame($original['items'][0]['unit_snapshot'], $reloaded->items->sole()->unit_snapshot);
        $this->assertSame($original['items'][0]['unit_price_snapshot'], $reloaded->items->sole()->unit_price_snapshot);
        $this->assertSame($original['items'][0]['quantity'], $reloaded->items->sole()->quantity);
        $this->assertSame($original['items'][0]['subtotal'], $reloaded->items->sole()->subtotal);
        $this->assertSame(1, LedgerEntry::query()->where('type', 'deposit_credit')->count());
    }

    public function test_cancellation_cannot_run_twice(): void
    {
        $deposit = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '1.000'],
        ]);
        app(DepositService::class)->cancel($deposit, 'First cancellation');

        try {
            app(DepositService::class)->cancel($deposit->fresh(), 'Second cancellation');
            $this->fail('Second cancellation should fail.');
        } catch (LogicException $exception) {
            $this->assertSame('Only posted deposits can be cancelled.', $exception->getMessage());
        }

        $this->assertSame(1, LedgerEntry::query()->where('type', 'deposit_reversal')->count());
        $this->assertSame(0, $this->user->refresh()->balance);
    }

    public function test_draft_deposit_cannot_be_cancelled(): void
    {
        $draft = new WasteDeposit;
        $draft->forceFill([
            'user_id' => $this->user->id,
            'deposit_date' => '2026-09-21',
            'total_amount' => 0,
            'status' => 'draft',
        ]);
        $draft->save();

        try {
            app(DepositService::class)->cancel($draft, 'Invalid draft cancellation');
            $this->fail('Draft cancellation should fail.');
        } catch (LogicException $exception) {
            $this->assertSame('Only posted deposits can be cancelled.', $exception->getMessage());
        }

        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(0, $this->user->refresh()->balance);
    }

    public function test_empty_or_whitespace_cancellation_reason_is_rejected(): void
    {
        $deposit = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '1.000'],
        ]);

        $this->expectException(ValidationException::class);
        try {
            app(DepositService::class)->cancel($deposit, '   ');
        } finally {
            $this->assertSame('posted', $deposit->fresh()->status);
            $this->assertSame(1, LedgerEntry::query()->where('type', 'deposit_credit')->count());
            $this->assertSame(0, LedgerEntry::query()->where('type', 'deposit_reversal')->count());
            $this->assertSame(3000, $this->user->refresh()->balance);
        }
    }

    public function test_missing_original_credit_blocks_cancellation(): void
    {
        $deposit = new WasteDeposit;
        $deposit->forceFill([
            'user_id' => $this->user->id,
            'waste_bank_id' => DB::table('waste_banks')->where('code', 'BS001')->value('id'),
            'deposit_date' => '2026-09-21',
            'total_amount' => 6000,
            'status' => 'posted',
            'posted_at' => now(),
        ]);
        $deposit->save();

        try {
            app(DepositService::class)->cancel($deposit, 'Missing credit test');
            $this->fail('Cancellation should fail without the original credit.');
        } catch (LogicException $exception) {
            $this->assertSame('The original deposit credit is missing or inconsistent.', $exception->getMessage());
        }

        $this->assertSame('posted', $deposit->fresh()->status);
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(0, $this->user->refresh()->balance);
    }

    public function test_inconsistent_original_credit_amount_blocks_cancellation(): void
    {
        $deposit = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '2.000'],
        ]);
        LedgerEntry::query()->where('type', 'deposit_credit')->update(['amount' => 1]);

        try {
            app(DepositService::class)->cancel($deposit, 'Corrupt credit test');
            $this->fail('Cancellation should fail for an inconsistent credit.');
        } catch (LogicException $exception) {
            $this->assertSame('The original deposit credit is missing or inconsistent.', $exception->getMessage());
        }

        $this->assertSame('posted', $deposit->fresh()->status);
        $this->assertSame(0, LedgerEntry::query()->where('type', 'deposit_reversal')->count());
        $this->assertSame(6000, $this->user->refresh()->balance);
    }

    public function test_insufficient_cached_balance_blocks_cancellation(): void
    {
        $deposit = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '2.000'],
        ]);
        $this->user->forceFill(['balance' => 5000])->save();

        try {
            app(DepositService::class)->cancel($deposit, 'Insufficient balance test');
            $this->fail('Cancellation should fail with insufficient cached balance.');
        } catch (LogicException $exception) {
            $this->assertSame('The cached balance is insufficient for this reversal.', $exception->getMessage());
        }

        $this->assertSame('posted', $deposit->fresh()->status);
        $this->assertSame(0, LedgerEntry::query()->where('type', 'deposit_reversal')->count());
        $this->assertSame(5000, $this->user->refresh()->balance);
    }

    public function test_cancellation_rolls_back_when_status_update_fails(): void
    {
        $deposit = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '1.000'],
        ]);
        $event = 'eloquent.updating: '.WasteDeposit::class;
        Event::listen($event, static function (): never {
            throw new LogicException('forced cancellation failure');
        });

        try {
            app(DepositService::class)->cancel($deposit, 'Rollback test');
            $this->fail('Cancellation should roll back on failure.');
        } catch (LogicException $exception) {
            $this->assertSame('forced cancellation failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $fresh = $deposit->fresh();
        $this->assertSame('posted', $fresh->status);
        $this->assertNull($fresh->cancelled_at);
        $this->assertNull($fresh->cancellation_reason);
        $this->assertSame(0, LedgerEntry::query()->where('type', 'deposit_reversal')->count());
        $this->assertSame(3000, $this->user->refresh()->balance);
    }

    public function test_cancel_then_post_replacement_preserves_correction_history(): void
    {
        $original = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '2.000'],
        ]);
        app(DepositService::class)->cancel($original, 'Correction replacement');
        $replacement = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '1.000'],
        ]);

        $this->assertSame('cancelled', $original->fresh()->status);
        $this->assertSame('posted', $replacement->fresh()->status);
        $this->assertSame(2, LedgerEntry::query()->where('type', 'deposit_credit')->count());
        $this->assertSame(1, LedgerEntry::query()->where('type', 'deposit_reversal')->count());
        $this->assertSame(3000, $this->user->refresh()->balance);
    }

    public function test_cancelled_deposit_cannot_be_hard_deleted(): void
    {
        $deposit = app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '1.000'],
        ]);
        $cancelled = app(DepositService::class)->cancel($deposit, 'Delete protection test');

        $this->assertFalse(WasteDepositResource::canEdit($cancelled));
        $this->assertFalse(WasteDepositResource::canDelete($cancelled));
        $this->expectException(LogicException::class);
        $cancelled->delete();
    }

    public function test_deposit_requires_an_active_membership_in_the_current_bank(): void
    {
        $this->user->bankMemberships()->update(['status' => 'inactive']);

        $this->expectException(ValidationException::class);
        app(DepositService::class)->post($this->user->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '1.000'],
        ]);
    }

    public function test_deposit_rejects_admin_accounts_even_if_they_are_selected(): void
    {
        $this->expectException(ValidationException::class);
        app(DepositService::class)->post($this->admin->id, '2026-09-21', [
            ['waste_item_id' => $this->wasteItem->id, 'quantity' => '1.000'],
        ]);
    }
}
