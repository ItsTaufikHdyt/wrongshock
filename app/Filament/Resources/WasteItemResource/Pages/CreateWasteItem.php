<?php

namespace App\Filament\Resources\WasteItemResource\Pages;

use App\Filament\Resources\WasteItemResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateWasteItem extends CreateRecord
{
    protected static string $resource = WasteItemResource::class;

    public function getTitle(): string
    {
        return 'Tambah Jenis Sampah';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan Jenis Sampah');
    }

    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
