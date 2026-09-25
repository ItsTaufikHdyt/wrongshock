<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Models\WasteBank;
use App\Services\BankMembershipService;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Anggota'),
            // Actions\Action::make('tambahkanExisting')
            //     ->label('Tambahkan Akun Existing')
            //     ->visible(fn (): bool => auth()->user()?->isBankAdmin() || auth()->user()?->isPlatformAdmin())
            //     ->form([
            //         TextInput::make('number')
            //             ->label('Nomor Anggota')
            //             ->required(),
            //         \Filament\Forms\Components\Select::make('waste_bank_id')
            //             ->label('Bank Sampah')
            //             ->options(fn () => WasteBank::query()->where('status', true)->pluck('name', 'id'))
            //             ->visible(fn (): bool => auth()->user()?->isPlatformAdmin() ?? false)
            //             ->required(fn (): bool => auth()->user()?->isPlatformAdmin() ?? false),
            //     ])
            //     ->action(function (array $data): void {
            //         $member = User::query()
            //             ->where('number', $data['number'])
            //             ->whereHas('roles', fn ($query) => $query->where('name', 'user'))
            //             ->first();

            //         if (! $member) {
            //             throw ValidationException::withMessages([
            //                 'number' => 'Akun anggota tidak ditemukan.',
            //             ]);
            //         }

            //         $bank = ! empty($data['waste_bank_id']) ? WasteBank::query()->findOrFail($data['waste_bank_id']) : null;
            //         app(BankMembershipService::class)->addMember(auth()->user(), $member, $bank);
            //         Notification::make()
            //             ->title('Anggota berhasil ditambahkan ke bank ini.')
            //             ->success()
            //             ->send();
            //     }),
        ];
    }
}
