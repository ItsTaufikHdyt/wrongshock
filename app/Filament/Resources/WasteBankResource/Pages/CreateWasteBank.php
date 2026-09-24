<?php

namespace App\Filament\Resources\WasteBankResource\Pages;

use App\Filament\Resources\WasteBankResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWasteBank extends CreateRecord
{
    protected static string $resource = WasteBankResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['code'] = strtoupper(trim($data['code']));

        return $data;
    }
}
