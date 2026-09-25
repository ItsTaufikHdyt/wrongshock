<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlatformUserResource\Pages;
use App\Models\District;
use App\Models\SubDistrict;
use App\Models\User;
use App\Models\WasteBank;
use App\Services\CitizenIdentityService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PlatformUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $navigationGroup = 'Manajemen Platform';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isPlatformAdmin() ?? false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['roles', 'district', 'subDistrict', 'wasteBanksAsStaff', 'wasteBanksAsMember']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nama')->required(),
            Forms\Components\TextInput::make('number')
                ->label('Nomor')
                ->required(fn (Forms\Get $get): bool => $get('role') !== 'user')
                ->disabled(fn (Forms\Get $get, $livewire): bool => $get('role') === 'user' && $livewire instanceof Pages\CreatePlatformUser)
                ->dehydrated(fn (Forms\Get $get): bool => $get('role') !== 'user')
                ->unique('users', 'number', ignoreRecord: true),
            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->unique('users', 'email', ignoreRecord: true),
            Forms\Components\TextInput::make('password')
                ->label('Password')
                ->password()
                ->required(fn ($livewire): bool => $livewire instanceof Pages\CreatePlatformUser)
                ->dehydrated(fn ($state): bool => filled($state)),
            Forms\Components\TextInput::make('password_confirmation')
                ->label('Konfirmasi Password')
                ->password()
                ->required(fn ($livewire): bool => $livewire instanceof Pages\CreatePlatformUser)
                ->same('password')
                ->dehydrated(false),
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
            Forms\Components\Select::make('role')
                ->label('Role')
                ->options([
                    'super_admin' => 'Super Admin',
                    'admin' => 'Admin Bank Sampah',
                    'user' => 'User / Citizen',
                ])
                ->required()
                ->live()
                ->afterStateUpdated(fn (Forms\Set $set) => $set('waste_bank_id', null)),
            Forms\Components\Select::make('waste_bank_id')
                ->label('Bank Sampah Operasional')
                ->options(fn () => WasteBank::query()->where('status', true)->orderBy('name')->pluck('name', 'id'))
                ->visible(fn (Forms\Get $get): bool => $get('role') === 'admin')
                ->required(fn (Forms\Get $get): bool => $get('role') === 'admin'),
            Forms\Components\Toggle::make('status')->label('Aktif')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('Nomor')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable(),
                Tables\Columns\TextColumn::make('roles.name')->label('Role')->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => (int) $state === 1 ? 'Aktif' : 'Nonaktif'),
                Tables\Columns\TextColumn::make('district.name')->label('Kecamatan'),
                Tables\Columns\TextColumn::make('subDistrict.name')->label('Kelurahan'),
                Tables\Columns\TextColumn::make('wasteBanksAsStaff.name')->label('Bank Admin'),
                Tables\Columns\TextColumn::make('wasteBanksAsMember.name')->label('Keanggotaan'),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Edit'),
                Tables\Actions\Action::make('rotateQr')
                    ->label('Regenerate QR')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate QR anggota?')
                    ->modalDescription('QR lama tidak akan dapat digunakan lagi.')
                    ->visible(fn (User $record): bool => auth()->user()?->isPlatformAdmin() === true && $record->hasRole('user'))
                    ->action(function (User $record): void {
                        Gate::authorize('update', $record);
                        app(CitizenIdentityService::class)->rotateQrToken($record);
                        Notification::make()->success()->title('QR anggota berhasil diperbarui.')->send();
                    }),
            ])
            ->bulkActions([])
            ->searchPlaceholder('Cari pengguna...');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformUsers::route('/'),
            'create' => Pages\CreatePlatformUser::route('/create'),
            'edit' => Pages\EditPlatformUser::route('/{record}/edit'),
        ];
    }
}
