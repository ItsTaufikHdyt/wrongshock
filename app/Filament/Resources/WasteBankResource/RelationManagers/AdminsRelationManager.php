<?php

namespace App\Filament\Resources\WasteBankResource\RelationManagers;

use App\Models\District;
use App\Models\SubDistrict;
use App\Models\User;
use App\Services\AdminMembershipService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class AdminsRelationManager extends RelationManager
{
    protected static string $relationship = 'staffAssignments';

    protected static ?string $title = 'Admin Bank Sampah';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Nama')->searchable(),
                Tables\Columns\TextColumn::make('user.email')->label('Email')->searchable(),
                Tables\Columns\TextColumn::make('user.status')
                    ->label('Status Akun')
                    ->formatStateUsing(fn ($state): string => (int) $state === 1 ? 'Aktif' : 'Nonaktif')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')->label('Ditugaskan')->dateTime('d M Y H:i'),
            ])
            ->actions([
                Tables\Actions\Action::make('removeAdmin')
                    ->label('Lepas Admin')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->isPlatformAdmin() ?? false)
                    ->action(fn ($record): mixed => app(AdminMembershipService::class)->removeBankAdmin(auth()->user(), $record)),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createAdmin')
                    ->label('Tambah Admin Baru')
                    ->form([
                        Forms\Components\TextInput::make('name')->label('Nama')->required(),
                        Forms\Components\TextInput::make('number')->label('Nomor Anggota')->required()->unique('users', 'number'),
                        Forms\Components\TextInput::make('email')->label('Email')->email()->required()->unique('users', 'email'),
                        Forms\Components\TextInput::make('password')->label('Kata Sandi')->password()->required(),
                        Forms\Components\TextInput::make('password_confirmation')->label('Konfirmasi Kata Sandi')->password()->required()->same('password'),
                        Forms\Components\Select::make('district_id')
                            ->label('Kecamatan')
                            ->options(fn () => District::pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('sub_district_id', null)),
                        Forms\Components\Select::make('sub_district_id')
                            ->label('Kelurahan')
                            ->options(fn (Forms\Get $get): array => $get('district_id')
                                ? SubDistrict::where('district_id', $get('district_id'))->pluck('name', 'id')->all()
                                : [])
                            ->rules(fn (Forms\Get $get): array => [Rule::exists('sub_districts', 'id')->where('district_id', $get('district_id'))])
                            ->required(),
                        Forms\Components\Textarea::make('address')->label('Alamat'),
                        Forms\Components\Toggle::make('status')->label('Aktif')->default(true),
                    ])
                    ->authorize(fn (): bool => auth()->user()?->isPlatformAdmin() ?? false)
                    ->action(function (array $data): void {
                        unset($data['password_confirmation']);
                        app(AdminMembershipService::class)->createBankAdmin(auth()->user(), $this->getOwnerRecord(), $data);
                    }),
            ])
            ->bulkActions([]);
    }
}
