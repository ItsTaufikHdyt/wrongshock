<?php

namespace App\Filament\Resources\WasteBankStaffResource\Pages;

use App\Filament\Resources\WasteBankStaffResource;
use App\Services\AdminMembershipService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWasteBankStaff extends CreateRecord
{
    protected static string $resource = WasteBankStaffResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        unset($data['password_confirmation'], $data['user_id']);

        $bank = \App\Models\WasteBank::query()->findOrFail($data['waste_bank_id']);

        return app(AdminMembershipService::class)->createBankAdmin(auth()->user(), $bank, $data);
    }
}
