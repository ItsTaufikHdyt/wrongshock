<?php

namespace App\Filament\UserPanel\Resources;

use App\Filament\UserPanel\Resources\UserResource\Pages;
use App\Filament\UserPanel\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\District;
use App\Models\SubDistrict;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'User Area';
    protected static ?int $navigationSort = 2;



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nama')
                                ->required(),
                            Forms\Components\TextInput::make('number')
                                ->label('ID Pengguna')
                                ->required()
                                ->disabled(true)
                                ->default(function () {
                                    $lastNumber = \App\Models\User::max('id') ?? 0;
                                    $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                                    return 'BSK-2025-' . $nextNumber;
                                })
                                ->dehydrated(), // pastikan tetap dikirim ke database
                        ]),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('email')
                                ->required(),
                            Forms\Components\TextInput::make('password')
                                ->label('Password')
                                ->password()
                                ->required(fn($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord)
                                ->nullable()
                                ->dehydrated(fn($state) => filled($state))
                                ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null),
                        ]),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('district_id')
                                ->label('Kecamatan')
                                ->required()
                                ->options(fn() => District::pluck('name', 'id'))
                                ->searchable(),
                            Forms\Components\Select::make('sub_district_id')
                                ->label('Kelurahan')
                                ->required()
                                ->reactive()
                                ->options(
                                    fn($get) =>
                                    SubDistrict::where('district_id', $get('district_id'))->pluck('name', 'id')
                                )
                                ->searchable(),
                        ]),
                    Forms\Components\Textarea::make('address')
                        ->label('Alamat')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\FileUpload::make('image')
                        ->required()
                        ->image(),

                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\ImageColumn::make('image')
                    ->label('Foto Profil')
                    ->circular(),
                Tables\Columns\TextColumn::make('number')
                    ->label('ID Pengguna')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('district.name')
                    ->label('Kecamatan')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('subDistrict.name')
                    ->label('Kelurahan')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('address')
                    ->label('Alamat')
                    ->formatStateUsing(fn($state) => Str::limit($state, 30)),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Saldo')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn($state) => $state !== null ? 'Rp ' . number_format($state, 0, '', '.') : ''),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereKey(Auth::id());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
