<?php

namespace App\Filament\Resources\WithdrawalResource\Pages;

use App\Filament\Resources\WithdrawalResource;
use App\Services\WithdrawalService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateWithdrawal extends CreateRecord
{
    protected static string $resource = WithdrawalResource::class;

    public function getTitle(): string
    {
        return 'Catat Penarikan';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan Penarikan');
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(WithdrawalService::class)->request(
            (int) $data['user_id'],
            $data['amount'],
            Auth::id(),
            $data['note'] ?? null,
        );
    }
}
