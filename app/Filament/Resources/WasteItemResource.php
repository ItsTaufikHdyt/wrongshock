<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WasteItemResource\Pages;
use App\Models\WasteItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WasteItemResource extends Resource
{
    protected static ?string $model = WasteItem::class;

    protected static ?string $modelLabel = 'Jenis Sampah';

    protected static ?string $pluralModelLabel = 'Jenis Sampah';

    protected static ?string $navigationLabel = 'Jenis Sampah';

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('category')
                                ->label('Kategori Sampah')
                                ->required(),
                            Forms\Components\Select::make('output')
                                ->options([
                                    'Kompos' => 'Kompos',
                                    'Kriya' => 'Kriya',
                                ])
                                ->label('Hasil Pengolahan')
                                ->required(),
                        ]),
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('unit')
                                ->options([
                                    'Kilogram (Kg)' => 'Kilogram (Kg)',
                                    'Gram (g)' => 'Gram (g)',
                                ])
                                ->label('Satuan')
                                ->required(),
                            Forms\Components\TextInput::make('price')
                                ->numeric()
                                ->prefix('Rp')
                                ->label('Harga per Satuan')
                                ->required(),
                        ]),

                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori Sampah')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('output')
                    ->label('Hasil Pengolahan')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('unit')
                    ->label('Satuan')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Harga per Satuan')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state !== null ? 'Rp '.number_format($state, 0, '', '.') : ''),
            ])
            ->filters([
                //
            ])
            ->searchPlaceholder('Cari jenis sampah...')
            ->actions([
                Tables\Actions\ViewAction::make()->label('Lihat Detail'),
                Tables\Actions\EditAction::make()->label('Edit Jenis Sampah'),
                Tables\Actions\DeleteAction::make()->label('Hapus Jenis Sampah'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Hapus Jenis Sampah Terpilih'),
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
            'index' => Pages\ListWasteItems::route('/'),
            'create' => Pages\CreateWasteItem::route('/create'),
            'edit' => Pages\EditWasteItem::route('/{record}/edit'),
        ];
    }
}
