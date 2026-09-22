<?php

namespace App\Filament\Resources\WasteDepositResource\Pages;

use App\Filament\Resources\WasteDepositResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditWasteDeposit extends EditRecord
{
    protected static string $resource = WasteDepositResource::class;

    public function getTitle(): string
    {
        return 'Edit Setoran';
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Simpan Perubahan');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Hapus Setoran')
                ->visible(fn (): bool => $this->record->status === 'draft'),
        ];
    }

    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
