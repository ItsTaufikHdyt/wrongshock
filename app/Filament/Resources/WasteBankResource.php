<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WasteBankResource\Pages;
use App\Models\SubDistrict;
use App\Models\WasteBank;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class WasteBankResource extends Resource
{
    protected static ?string $model = WasteBank::class;

    protected static ?string $modelLabel = 'Bank Sampah';

    protected static ?string $pluralModelLabel = 'Bank Sampah';

    protected static ?string $navigationLabel = 'Bank Sampah';

    protected static ?string $navigationGroup = 'Manajemen Platform';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isPlatformAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Bank Sampah')->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Kode Bank')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper(trim($state)) : $state)
                    ->disabled(fn (?WasteBank $record): bool => $record?->deposits()->exists() || $record?->withdrawals()->exists()),
                Forms\Components\TextInput::make('name')
                    ->label('Nama Bank Sampah')
                    ->required()
                    ->maxLength(255),
            ])->columns(2),
            Forms\Components\Section::make('Wilayah')->schema([
                Forms\Components\Select::make('district_id')
                    ->label('Kecamatan')
                    ->relationship('district', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set): mixed => $set('sub_district_id', null)),
                Forms\Components\Select::make('sub_district_id')
                    ->label('Kelurahan')
                    ->required()
                    ->options(fn (Forms\Get $get): array => $get('district_id')
                        ? SubDistrict::query()->where('district_id', $get('district_id'))->orderBy('name')->pluck('name', 'id')->all()
                        : [])
                    ->searchable()
                    ->rules(fn (Forms\Get $get): array => [
                        Rule::exists('sub_districts', 'id')->where('district_id', $get('district_id')),
                    ]),
            ])->columns(2),
            Forms\Components\Section::make('Alamat & Lokasi')->schema([
                Forms\Components\Textarea::make('address')
                    ->label('Alamat')
                    ->rows(3),
                Forms\Components\TextInput::make('latitude')
                    ->label('Latitude')
                    ->numeric()
                    ->minValue(-90)
                    ->maxValue(90),
                Forms\Components\TextInput::make('longitude')
                    ->label('Longitude')
                    ->numeric()
                    ->minValue(-180)
                    ->maxValue(180),
                Forms\Components\Toggle::make('status')
                    ->label('Aktif')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['district', 'subDistrict'])->withCount('staff'))
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Kode')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Nama Bank Sampah')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('district.name')->label('Kecamatan')->sortable(),
                Tables\Columns\TextColumn::make('subDistrict.name')->label('Kelurahan')->sortable(),
                Tables\Columns\IconColumn::make('status')->label('Status')->boolean(),
                Tables\Columns\TextColumn::make('staff_count')->label('Jumlah Admin')->counts('staff'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Edit Bank Sampah'),
                Tables\Actions\Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (WasteBank $record): bool => $record->status)
                    ->action(fn (WasteBank $record) => $record->update(['status' => false])),
                Tables\Actions\Action::make('activate')
                    ->label('Aktifkan')
                    ->color('success')
                    ->visible(fn (WasteBank $record): bool => ! $record->status)
                    ->action(fn (WasteBank $record) => $record->update(['status' => true])),
            ])
            ->bulkActions([])
            ->searchPlaceholder('Cari bank sampah...');
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\WasteBankResource\RelationManagers\AdminsRelationManager::class,
            \App\Filament\Resources\WasteBankResource\RelationManagers\MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWasteBanks::route('/'),
            'create' => Pages\CreateWasteBank::route('/create'),
            'edit' => Pages\EditWasteBank::route('/{record}/edit'),
        ];
    }
}
