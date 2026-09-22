<?php

namespace App\Filament\Resources\SubDistrictResource\Pages;

use App\Filament\Resources\SubDistrictResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateSubDistrict extends CreateRecord
{
    protected static string $resource = SubDistrictResource::class;

    public function getTitle(): string
    {
        return 'Tambah Kelurahan';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan Kelurahan');
    }

    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
