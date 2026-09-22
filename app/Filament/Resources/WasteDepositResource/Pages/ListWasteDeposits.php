<?php

namespace App\Filament\Resources\WasteDepositResource\Pages;

use App\Filament\Resources\WasteDepositResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWasteDeposits extends ListRecords
{
    protected static string $resource = WasteDepositResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Catat Setoran'),
        ];
    }
}
