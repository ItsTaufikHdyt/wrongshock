<?php

namespace App\Filament\UserPanel\Pages;

use App\Models\WasteDepositItem;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

abstract class UserDepositPage extends Page
{
    protected function depositQuery(Builder $query): Builder
    {
        return $query->with([
            'items' => fn ($query) => $query->select([
                'id',
                'waste_deposit_id',
                'waste_item_id',
                'waste_name_snapshot',
                'category_snapshot',
                'unit_snapshot',
                'unit_price_snapshot',
                'quantity',
                'subtotal',
            ]),
            'items.wasteItem:id,category',
        ]);
    }

    public function formatRupiah(?int $amount): string
    {
        return 'Rp'.number_format($amount ?? 0, 0, ',', '.');
    }

    public function formatDate(mixed $date, bool $long = false): string
    {
        $months = $long
            ? [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember']
            : [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
        $date = Carbon::parse($date);

        return $date->day.' '.$months[$date->month].' '.$date->year;
    }

    public function formatQuantity(mixed $quantity): string
    {
        $value = trim((string) $quantity);
        if ($value === '') {
            return '-';
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return $whole.'.'.str_pad(substr($fraction, 0, 3), 3, '0');
    }

    public function itemName(WasteDepositItem $item): string
    {
        return $item->waste_name_snapshot
            ?? $item->category_snapshot
            ?? $item->wasteItem?->category
            ?? '-';
    }

    public function itemUnit(WasteDepositItem $item): string
    {
        return match ($item->unit_snapshot) {
            'Kilogram (Kg)' => 'Kg',
            default => $item->unit_snapshot ?: 'Unit',
        };
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'posted' => 'Berhasil',
            'cancelled' => 'Dibatalkan',
            default => 'Draft',
        };
    }

    public function statusClasses(?string $status): string
    {
        return match ($status) {
            'posted' => 'ws-status-success',
            'cancelled' => 'ws-status-danger',
            default => 'ws-status-warning',
        };
    }
}
