<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubDistrictResource\Pages;
use App\Models\District;
use App\Models\SubDistrict;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubDistrictResource extends Resource
{
    protected static ?string $model = SubDistrict::class;

    protected static ?string $modelLabel = 'Kelurahan';

    protected static ?string $pluralModelLabel = 'Kelurahan';

    protected static ?string $pluralLabel = 'Kelurahan';

    protected static ?string $navigationLabel = 'Kelurahan';

    protected static ?string $navigationIcon = 'heroicon-o-globe-americas';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([
                    Forms\Components\Grid::make(1)->schema([
                        Forms\Components\Select::make('district_id')
                            ->label('Kecamatan')
                            ->required()
                            ->options(fn () => District::pluck('name', 'id'))
                            ->searchable(),
                        Forms\Components\TextInput::make('name')
                            ->label('Kelurahan')
                            ->required()
                            ->maxLength(100),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('district.name')
                    ->label('Kecamatan')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Kelurahan')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->searchPlaceholder('Cari kelurahan...')
            ->actions([
                Tables\Actions\ViewAction::make()->label('Lihat Detail'),
                Tables\Actions\EditAction::make()->label('Edit Kelurahan'),
                Tables\Actions\DeleteAction::make()->label('Hapus Kelurahan'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Hapus Kelurahan Terpilih'),
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
            'index' => Pages\ListSubDistricts::route('/'),
            'create' => Pages\CreateSubDistrict::route('/create'),
            'edit' => Pages\EditSubDistrict::route('/{record}/edit'),
        ];
    }
}
