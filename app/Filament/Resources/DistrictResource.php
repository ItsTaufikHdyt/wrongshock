<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DistrictResource\Pages;
use App\Models\District;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DistrictResource extends Resource
{
    protected static ?string $model = District::class;

    protected static ?string $modelLabel = 'Kecamatan';

    protected static ?string $pluralModelLabel = 'Kecamatan';

    protected static ?string $pluralLabel = 'Kecamatan';

    protected static ?string $navigationLabel = 'Kecamatan';

    protected static ?string $navigationIcon = 'heroicon-o-globe-americas';

    protected static ?string $navigationGroup = 'Master Data';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isPlatformAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([
                    Forms\Components\Grid::make(1)->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Kecamatan')
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Kecamatan')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->searchPlaceholder('Cari kecamatan...')
            ->actions([
                Tables\Actions\ViewAction::make()->label('Lihat Detail'),
                Tables\Actions\EditAction::make()->label('Edit Kecamatan'),
                Tables\Actions\DeleteAction::make()->label('Hapus Kecamatan'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Hapus Kecamatan Terpilih'),
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
            'index' => Pages\ListDistricts::route('/'),
            'create' => Pages\CreateDistrict::route('/create'),
            'edit' => Pages\EditDistrict::route('/{record}/edit'),
        ];
    }
}
