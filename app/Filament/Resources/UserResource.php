<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Models\District;
use App\Models\SubDistrict;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'System';

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

                                    $user = Auth::user();
                                    $kodeKota = '001'; // Bontang
                                    $kodeDistrict = str_pad($user->district_id, 2, '0', STR_PAD_LEFT);
                                    $kodeSubDistrict = str_pad($user->sub_district_id, 2, '0', STR_PAD_LEFT);
                                    // ambil tahun berjalan
                                    $tahun = date('Y');

                                    // generate angka random 4 digit
                                    $randomNumber = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

                                    // gabungkan jadi format ID
                                    return $kodeKota . $kodeDistrict . $kodeSubDistrict . $tahun . $randomNumber;
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
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            1 => 'Aktif',
                            0 => 'Nonaktif',
                        ])
                        ->required()
                        ->native(false),
                    Forms\Components\Select::make('roles')
                        ->required()
                        ->label('Role')
                        ->relationship('roles', 'name')
                        ->preload()
                        ->searchable()


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
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->formatStateUsing(fn($state) => $state == 1 ? 'Aktif' : 'Nonaktif')
                    ->colors([
                        'success' => 1,   // hijau untuk status = 1
                        'danger'  => 0,   // merah untuk status = 0
                    ]),
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
                Tables\Columns\TagsColumn::make('roles.name')
                    ->label('Role')
                    ->sortable()
                    ->searchable(),

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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
