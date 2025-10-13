<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WasteDepositResource\Pages;
use App\Filament\Resources\WasteDepositResource\RelationManagers;
use App\Models\WasteDeposit;
use App\Models\WasteItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WasteDepositResource extends Resource
{
    protected static ?string $model = WasteDeposit::class;
    protected static ?string $pluralLabel = 'Setor Limbah';
    protected static ?int $navigationSort = 1;


    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Bank Sampah';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->searchable()
                    ->label('Pengguna')
                    ->relationship('user', 'name')
                    ->getSearchResultsUsing(function ($search) {
                        return User::query()
                            ->where(function ($query) use ($search) {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('number', 'like', "%{$search}%");
                            })
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($user) {
                                return [$user->id => "{$user->name} | {$user->number}"];
                            });
                    })
                    ->required(),

                Forms\Components\TextInput::make('deposit_date')
                    ->label('Tanggal Setor')
                    ->default(now()->toDateString())
                    ->required()
                    ->type('date'),

                Forms\Components\Repeater::make('items')
                    ->label('Item Sampah')
                    ->relationship()
                    ->schema([
                        Forms\Components\Select::make('waste_item_id')
                            ->label('Jenis Sampah')
                            ->options(WasteItem::all()->pluck('category', 'id'))
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $wasteItem = WasteItem::find($state);
                                $quantity = $get('quantity') ?? 0;
                                $subtotal = $wasteItem ? $wasteItem->price * $quantity : 0;
                                $price = $wasteItem?->price ?? 0;
                                $set('price', $price);
                                $set('subtotal', $subtotal);
                            }),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Jumlah (Kg)')
                            ->numeric()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $wasteItem = WasteItem::find($get('waste_item_id'));
                                $subtotal = $wasteItem ? $wasteItem->price * ($state ?? 0) : 0;
                                $set('subtotal', $subtotal);
                            }),

                        Forms\Components\TextInput::make('price')
                            ->label('Harga (Rp)')
                            ->disabled(),

                        Forms\Components\TextInput::make('subtotal')
                            ->label('Subtotal (Rp)')
                            ->disabled()
                            ->dehydrated(), // Penting agar disimpan
                    ])
                    ->defaultItems(1)
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Hitung ulang total dari semua item
                        $total = 0;
                        foreach ($state as $item) {
                            $total += $item['subtotal'] ?? 0;
                        }
                        $set('total_amount', $total);
                    }),

                Forms\Components\TextInput::make('total_amount')
                    ->label('Total Setoran (Rp)')
                    ->disabled()
                    ->dehydrated() // penting agar ikut tersimpan di DB
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama Anggota')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('deposit_date')
                    ->label('Tanggal Setor')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('IDR')->label('Total Setor')
                    ->sortable()
                    ->searchable(),
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
            'index' => Pages\ListWasteDeposits::route('/'),
            'create' => Pages\CreateWasteDeposit::route('/create'),
            'edit' => Pages\EditWasteDeposit::route('/{record}/edit'),
        ];
    }

    protected static function afterSave(Model $record): void {}
}
