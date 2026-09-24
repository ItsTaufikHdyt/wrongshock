<?php

namespace App\Filament\Resources\WasteBankStaffResource\Pages;

use App\Filament\Resources\WasteBankStaffResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWasteBankStaff extends ListRecords
{
    protected static string $resource = WasteBankStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Tambah Admin')];
    }
}
