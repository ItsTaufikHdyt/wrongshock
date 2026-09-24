<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\BankMembershipService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return 'Tambah Anggota';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan Anggota');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['password_confirmation'], $data['role'], $data['balance'], $data['status']);

        $data['balance'] = 0;
        $data['status'] = 1;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(BankMembershipService::class)->createMember(auth()->user(), $data);
    }

    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
