<?php

namespace App\Filament\Resources\WasteItemResource\Pages;

use App\Filament\Resources\WasteItemResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditWasteItem extends EditRecord
{
    protected static string $resource = WasteItemResource::class;

    public function getTitle(): string
    {
        return 'Edit Jenis Sampah';
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Simpan Perubahan');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Hapus Jenis Sampah'),
        ];
    }

    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
