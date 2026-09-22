<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WasteDepositItemResource\Pages;
use App\Models\WasteDepositItem;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WasteDepositItemResource extends Resource
{
    protected static ?string $model = WasteDepositItem::class;

    protected static ?string $modelLabel = 'Rincian Setoran';

    protected static ?string $pluralModelLabel = 'Rincian Setoran';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Transaksi';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('wasteDeposit.user.name')
                    ->label('Nama Anggota')
                    ->searchable(),

                Tables\Columns\TextColumn::make('wasteItem.category')
                    ->label('Jenis Sampah')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah'),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotal (Rp)')
                    ->money('IDR'),

                Tables\Columns\TextColumn::make('wasteDeposit.deposit_date')
                    ->label('Tanggal Setoran')
                    ->date(),
            ])
            ->filters([
                //
            ])
            ->searchPlaceholder('Cari rincian setoran...')
            ->actions([

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

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
            'index' => Pages\ListWasteDepositItems::route('/'),
            'create' => Pages\CreateWasteDepositItem::route('/create'),
            'edit' => Pages\EditWasteDepositItem::route('/{record}/edit'),
        ];
    }
}
