<?php

namespace App\Filament\Resources\WasteItemResource\Pages;

use App\Filament\Resources\WasteItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWasteItems extends ListRecords
{
    protected static string $resource = WasteItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Jenis Sampah'),
        ];
    }
}
