<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WasteDepositResource\Pages;
use App\Models\User;
use App\Models\WasteDeposit;
use App\Models\WasteItem;
use App\Services\DepositService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class WasteDepositResource extends Resource
{
    protected static ?string $model = WasteDeposit::class;

    protected static ?string $modelLabel = 'Setoran';

    protected static ?string $pluralModelLabel = 'Setoran';

    protected static ?string $navigationLabel = 'Setoran';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Transaksi';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Setoran')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->searchable()
                            ->label('Anggota')
                            ->relationship('user', 'name', fn ($query) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'user')))
                            ->getSearchResultsUsing(function ($search) {
                                return User::query()
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('number', 'like', "%{$search}%");
                                    })
                                    ->whereHas('roles', fn ($query) => $query->where('name', 'user'))
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($user) => [$user->id => "{$user->name} | {$user->number}"]);
                            })
                            ->getOptionLabelFromRecordUsing(fn (User $user): string => "{$user->name} | {$user->number}")
                            ->required(),

                        Forms\Components\TextInput::make('deposit_date')
                            ->label('Tanggal Setoran')
                            ->default(now()->toDateString())
                            ->required()
                            ->type('date'),
                    ]),
                Forms\Components\Section::make('Detail Sampah')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('Jenis Sampah')
                            ->schema([
                                Forms\Components\Select::make('waste_item_id')
                                    ->label('Jenis Sampah')
                                    ->options(fn () => WasteItem::query()->orderBy('category')->pluck('category', 'id'))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::syncCurrentPreview($get, $set)),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Jumlah')
                                    ->helperText('Gunakan maksimal 3 angka desimal.')
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->required()
                                    ->live(debounce: 250)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::syncCurrentPreview($get, $set)),
                                Forms\Components\TextInput::make('unit')
                                    ->label('Satuan')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('price')
                                    ->label('Harga per Satuan')
                                    ->prefix('Rp')
                                    ->formatStateUsing(fn ($state): string => self::formatMoney($state))
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->prefix('Rp')
                                    ->formatStateUsing(fn ($state): string => self::formatMoney($state))
                                    ->disabled()
                                    ->dehydrated(false),
                            ])
                            ->columns(['default' => 1, 'md' => 2, 'xl' => 5])
                            ->defaultItems(1)
                            ->addActionLabel('Tambah Jenis Sampah')
                            ->deleteAction(fn (Forms\Components\Actions\Action $action) => $action->label('Hapus Jenis Sampah')->tooltip('Hapus jenis sampah dari setoran'))
                            ->live()
                            ->afterStateHydrated(fn (?array $state, Set $set) => self::syncAllPreview($state ?? [], $set))
                            ->afterStateUpdated(fn (?array $state, Set $set) => self::syncAllPreview($state ?? [], $set)),
                    ]),
                Forms\Components\Section::make('Ringkasan')
                    ->schema([
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Setoran')
                            ->prefix('Rp')
                            ->formatStateUsing(fn ($state): string => self::formatMoney($state))
                            ->disabled()
                            ->dehydrated(false),
                    ]),
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
                    ->label('Tanggal Setoran')
                    ->sortable()
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.number')
                    ->label('Nomor Anggota')
                    ->searchable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Jumlah Item')
                    ->counts('items'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('IDR')->label('Total Setoran')
                    ->sortable()
                    ->searchable()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'posted' => 'Berhasil',
                        'cancelled' => 'Dibatalkan',
                        default => 'Draft',
                    })
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'posted',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('cancelled_at')
                    ->label('Dibatalkan Pada')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cancellation_reason')
                    ->label('Alasan Pembatalan')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->searchPlaceholder('Cari setoran...')
            ->actions([
                Tables\Actions\ViewAction::make()->label('Lihat Detail'),
                Tables\Actions\EditAction::make()
                    ->label('Edit Setoran')
                    ->visible(fn (WasteDeposit $record): bool => $record->status === 'draft'),
                Tables\Actions\DeleteAction::make()
                    ->label('Hapus')
                    ->visible(fn (WasteDeposit $record): bool => $record->status === 'draft'),
                Tables\Actions\Action::make('cancel')
                    ->label('Batalkan Setoran')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (WasteDeposit $record): bool => $record->status === 'posted')
                    ->authorize(fn (): bool => Auth::user()?->hasRole('admin') ?? false)
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Alasan pembatalan')
                            ->required()
                            ->minLength(3)
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan Setoran')
                    ->modalDescription('Setoran tidak akan dihapus. Saldo anggota akan dikoreksi melalui pembalikan transaksi.')
                    ->modalSubmitActionLabel('Batalkan Setoran')
                    ->action(function (WasteDeposit $record, array $data): void {
                        abort_unless(Auth::user()?->hasRole('admin'), 403);

                        app(DepositService::class)->cancel($record, $data['cancellation_reason'], Auth::id());
                    }),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Informasi Setoran')
                ->schema([
                    TextEntry::make('user.name')->label('Anggota'),
                    TextEntry::make('user.number')->label('Nomor Anggota'),
                    TextEntry::make('deposit_date')->label('Tanggal Setoran')->date('d M Y'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'posted' => 'Berhasil',
                            'cancelled' => 'Dibatalkan',
                            default => 'Draft',
                        })
                        ->color(fn (string $state): string => match ($state) {
                            'posted' => 'success',
                            'cancelled' => 'danger',
                            default => 'gray',
                        }),
                ])
                ->columns(2),
            InfolistSection::make('Detail Sampah')
                ->schema([
                    RepeatableEntry::make('items')
                        ->label('Jenis Sampah')
                        ->schema([
                            TextEntry::make('waste_name_snapshot')->label('Jenis Sampah')->placeholder('Tidak tersedia'),
                            TextEntry::make('quantity')
                                ->label('Jumlah')
                                ->formatStateUsing(fn ($state): string => self::formatQuantity($state)),
                            TextEntry::make('unit_snapshot')->label('Satuan'),
                            TextEntry::make('unit_price_snapshot')
                                ->label('Harga per Satuan')
                                ->formatStateUsing(fn ($state): string => self::formatDisplayMoney($state)),
                            TextEntry::make('subtotal')
                                ->label('Subtotal')
                                ->formatStateUsing(fn ($state): string => self::formatDisplayMoney($state)),
                        ])
                        ->columns(5),
                    TextEntry::make('items_empty')
                        ->label('')
                        ->state('Detail item setoran tidak tersedia.')
                        ->visible(fn ($record): bool => $record?->items?->isEmpty() ?? true),
                ]),
            InfolistSection::make('Ringkasan')
                ->schema([
                    TextEntry::make('total_amount')
                        ->label('Total Setoran')
                        ->formatStateUsing(fn ($state): string => self::formatDisplayMoney($state)),
                    TextEntry::make('cancellation_reason')->label('Alasan Pembatalan')->visible(fn ($record): bool => filled($record?->cancellation_reason)),
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

    public static function canEdit(Model $record): bool
    {
        return $record->status === 'draft';
    }

    public static function canDelete(Model $record): bool
    {
        return $record->status === 'draft';
    }

    /**
     * Calculate display-only values. DepositService recalculates financial truth.
     *
     * @return array{rows: array<string|int, array<string, mixed>>, total: int}
     */
    public static function calculatePreview(array $items): array
    {
        $itemIds = collect($items)->pluck('waste_item_id')->filter()->map(fn ($id): int => (int) $id)->unique();
        $masters = WasteItem::query()->whereIn('id', $itemIds)->get()->keyBy('id');
        $total = 0;
        $rows = [];

        foreach ($items as $key => $item) {
            $master = $masters->get((int) ($item['waste_item_id'] ?? 0));
            $quantityScaled = self::quantityScaled($item['quantity'] ?? null);
            $price = (int) ($master?->price ?? 0);
            $subtotal = $quantityScaled === null ? 0 : intdiv(($price * $quantityScaled) + 500, 1000);
            $total += $subtotal;
            $rows[$key] = [
                'unit' => $master?->unit ?? '',
                'price' => $price,
                'subtotal' => $subtotal,
            ];
        }

        return ['rows' => $rows, 'total' => $total];
    }

    private static function syncCurrentPreview(Get $get, Set $set): void
    {
        $preview = self::calculatePreview([[
            'waste_item_id' => $get('waste_item_id'),
            'quantity' => $get('quantity'),
        ]]);
        $row = $preview['rows'][0] ?? ['unit' => '', 'price' => 0, 'subtotal' => 0];

        $set('unit', $row['unit']);
        $set('price', $row['price']);
        $set('subtotal', $row['subtotal']);
        $set('../../total_amount', self::calculatePreview($get('../../items') ?? [])['total']);
    }

    private static function syncAllPreview(array $items, Set $set): void
    {
        $preview = self::calculatePreview($items);

        foreach ($preview['rows'] as $key => $row) {
            $set("{$key}.unit", $row['unit']);
            $set("{$key}.price", $row['price']);
            $set("{$key}.subtotal", $row['subtotal']);
        }

        $set('../total_amount', $preview['total']);
    }

    private static function quantityScaled(mixed $quantity): ?int
    {
        $quantity = trim((string) $quantity);

        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,3})?$/', $quantity)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }

    private static function formatMoney(mixed $amount): string
    {
        return number_format((int) $amount, 0, ',', '.');
    }

    private static function formatDisplayMoney(mixed $amount): string
    {
        return 'Rp'.self::formatMoney($amount);
    }

    private static function formatQuantity(mixed $quantity): string
    {
        $value = trim((string) $quantity);

        if ($value === '') {
            return '0';
        }

        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return str_replace('.', ',', $value);
    }
}
