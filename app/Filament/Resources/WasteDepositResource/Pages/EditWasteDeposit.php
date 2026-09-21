<?php

namespace App\Filament\Resources\WasteDepositResource\Pages;

use App\Filament\Resources\WasteDepositResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWasteDeposit extends EditRecord
{
    protected static string $resource = WasteDepositResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->record->status === 'draft'),
        ];
    }

    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
