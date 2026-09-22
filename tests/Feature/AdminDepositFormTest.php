<?php

namespace Tests\Feature;

use App\Filament\Resources\WasteDepositResource;
use App\Filament\Resources\WasteDepositResource\Pages\CreateWasteDeposit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDepositFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_uses_current_master_price_and_decimal_quantity(): void
    {
        $itemId = $this->createWasteItem('Botol Plastik', 'Kilogram (Kg)', 3000);

        $preview = WasteDepositResource::calculatePreview([
            ['waste_item_id' => $itemId, 'quantity' => '2.500'],
        ]);

        $this->assertSame(7500, $preview['total']);
        $this->assertSame([
            'unit' => 'Kilogram (Kg)',
            'price' => 3000,
            'subtotal' => 7500,
        ], $preview['rows'][0]);
    }

    public function test_preview_updates_total_for_multiple_rows_changes_and_removal(): void
    {
        $firstId = $this->createWasteItem('Kertas', 'Kilogram (Kg)', 1000);
        $secondId = $this->createWasteItem('Kardus', 'Gram (g)', 2000);

        $twoRows = WasteDepositResource::calculatePreview([
            ['waste_item_id' => $firstId, 'quantity' => '2.500'],
            ['waste_item_id' => $secondId, 'quantity' => '1.5'],
        ]);
        $changedSecondRow = WasteDepositResource::calculatePreview([
            ['waste_item_id' => $firstId, 'quantity' => '2.500'],
            ['waste_item_id' => $secondId, 'quantity' => '2.000'],
        ]);
        $oneRow = WasteDepositResource::calculatePreview([
            ['waste_item_id' => $firstId, 'quantity' => '2.500'],
        ]);

        $this->assertSame(5500, $twoRows['total']);
        $this->assertSame(6500, $changedSecondRow['total']);
        $this->assertSame(2500, $oneRow['total']);
        $this->assertSame('Gram (g)', $twoRows['rows'][1]['unit']);
    }

    public function test_preview_handles_blank_zero_and_unknown_rows_without_nan(): void
    {
        $itemId = $this->createWasteItem('Kardus', 'Kilogram (Kg)', 400);

        $preview = WasteDepositResource::calculatePreview([
            ['waste_item_id' => $itemId, 'quantity' => ''],
            ['waste_item_id' => 999999, 'quantity' => '0'],
        ]);

        $this->assertSame(0, $preview['total']);
        $this->assertSame(0, $preview['rows'][0]['subtotal']);
        $this->assertSame(0, $preview['rows'][1]['subtotal']);
    }

    public function test_live_form_updates_total_when_quantity_changes_without_adding_a_row(): void
    {
        $admin = $this->createAdmin();
        $itemId = $this->createWasteItem('Minyak Jelantah', 'Kilogram (Kg)', 3000);
        Filament::setCurrentPanel(Filament::getPanel('adminPanel'));

        $form = Livewire::actingAs($admin)->test(CreateWasteDeposit::class);
        $itemKey = array_key_first($form->get('data.items'));

        $form
            ->set("data.items.{$itemKey}.waste_item_id", $itemId)
            ->set("data.items.{$itemKey}.quantity", '2.500')
            ->assertSet("data.items.{$itemKey}.unit", 'Kilogram (Kg)')
            ->assertSet("data.items.{$itemKey}.price", 3000)
            ->assertSet("data.items.{$itemKey}.subtotal", 7500)
            ->assertSet('data.total_amount', 7500);
    }

    private function createWasteItem(string $category, string $unit, int $price): int
    {
        return DB::table('waste_items')->insertGetId([
            'category' => $category,
            'output' => 'Kriya',
            'unit' => $unit,
            'price' => $price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAdmin(): User
    {
        $districtId = DB::table('districts')->insertGetId([
            'name' => 'Deposit Form District '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => 'Deposit Form Subdistrict '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin = User::query()->create([
            'name' => 'Deposit Form Admin',
            'number' => 'ADMIN-'.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => Hash::make('password123'),
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => 1,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));

        return $admin->refresh();
    }
}
