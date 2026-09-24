<?php

namespace App\Filament\Resources\WasteBankResource\Pages;

use App\Filament\Resources\WasteBankResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWasteBanks extends ListRecords
{
    protected static string $resource = WasteBankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Bank Sampah'),
        ];
    }
}
