<?php

namespace App\Filament\Resources\PlatformUserResource\Pages;

use App\Filament\Resources\PlatformUserResource;
use App\Services\UserRoleService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPlatformUser extends EditRecord
{
    protected static string $resource = PlatformUserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->getRoleNames()->first();
        $data['waste_bank_id'] = $this->record->wasteBanksAsStaff()->where('status', true)->value('waste_banks.id');

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data['role'] ??= $record->getRoleNames()->first();
        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        }

        unset($data['password_confirmation']);

        return app(UserRoleService::class)->update(auth()->user(), $record, $data);
    }
}
