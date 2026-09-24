<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WasteBankStaffResource\Pages;
use App\Models\District;
use App\Models\SubDistrict;
use App\Models\WasteBank;
use App\Models\WasteBankStaff;
use App\Services\AdminMembershipService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class WasteBankStaffResource extends Resource
{
    protected static ?string $model = WasteBankStaff::class;

    protected static ?string $modelLabel = 'Admin Bank Sampah';

    protected static ?string $pluralModelLabel = 'Admin Bank Sampah';

    protected static ?string $navigationLabel = 'Admin Bank Sampah';

    protected static ?string $navigationGroup = 'Manajemen Platform';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isPlatformAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->hidden(),
            Forms\Components\TextInput::make('name')->label('Nama')->required(),
            Forms\Components\TextInput::make('number')
                ->label('Nomor Anggota')
                ->required()
                ->unique('users', 'number'),
            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->unique('users', 'email'),
            Forms\Components\TextInput::make('password')
                ->label('Kata Sandi')
                ->password()
                ->required()
                ->dehydrated(fn ($state): bool => filled($state)),
            Forms\Components\TextInput::make('password_confirmation')
                ->label('Konfirmasi Kata Sandi')
                ->password()
                ->required()
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
                ->rules(fn (Forms\Get $get): array => [
                    Rule::exists('sub_districts', 'id')->where('district_id', $get('district_id')),
                ])
                ->required(),
            Forms\Components\Textarea::make('address')->label('Alamat'),
            Forms\Components\Toggle::make('status')->label('Aktif')->default(true),
            Forms\Components\Select::make('waste_bank_id')
                ->label('Bank Sampah')
                ->options(fn () => WasteBank::query()->where('status', true)->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'wasteBank']))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Nama')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('user.email')->label('Email')->searchable(),
                Tables\Columns\TextColumn::make('wasteBank.name')->label('Bank Sampah')->sortable(),
                Tables\Columns\TextColumn::make('user.status')->label('Status User')->badge()->formatStateUsing(fn ($state) => (int) $state === 1 ? 'Aktif' : 'Nonaktif'),
                Tables\Columns\IconColumn::make('wasteBank.status')->label('Status Bank')->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('removeAdmin')
                    ->label('Lepas Admin')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->isPlatformAdmin() ?? false)
                    ->action(fn (WasteBankStaff $record): mixed => app(AdminMembershipService::class)->removeBankAdmin(auth()->user(), $record)),
            ])
            ->bulkActions([])
            ->searchPlaceholder('Cari admin bank sampah...');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWasteBankStaff::route('/'),
            'create' => Pages\CreateWasteBankStaff::route('/create'),
        ];
    }
}
