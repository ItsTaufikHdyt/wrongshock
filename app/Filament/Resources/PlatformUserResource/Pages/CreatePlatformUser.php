<?php

namespace App\Filament\Resources\PlatformUserResource\Pages;

use App\Filament\Resources\PlatformUserResource;
use App\Services\UserRoleService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePlatformUser extends CreateRecord
{
    protected static string $resource = PlatformUserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $role = $data['role'];
        $bank = ! empty($data['waste_bank_id'])
            ? \App\Models\WasteBank::query()->findOrFail($data['waste_bank_id'])
            : null;
        unset($data['role'], $data['waste_bank_id'], $data['password_confirmation']);
        $data['status'] = (int) ($data['status'] ?? 1);

        return app(UserRoleService::class)->create(auth()->user(), $data, $role, $bank);
    }
}
