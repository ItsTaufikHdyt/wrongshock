<?php

namespace App\Filament\Resources\WasteDepositResource\Pages;

use App\Filament\Resources\WasteDepositResource;
use App\Services\DepositService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateWasteDeposit extends CreateRecord
{
    protected static string $resource = WasteDepositResource::class;

    public function getTitle(): string
    {
        return 'Catat Setoran';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan Setoran');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Batal');
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(DepositService::class)->post(
            (int) $data['user_id'],
            (string) $data['deposit_date'],
            $data['items'] ?? [],
            Auth::id(),
        );
    }

    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
