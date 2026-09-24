<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WasteBankStaffResource\Pages;
use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteBankStaff;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
                ->label('Admin')
                ->options(fn () => User::query()
                    ->where('status', 1)
                    ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
                    ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'super_admin'))
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} | {$user->email}"])
                    ->all())
                ->searchable()
                ->required(),
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
                Tables\Actions\DeleteAction::make()->label('Lepas Admin'),
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
