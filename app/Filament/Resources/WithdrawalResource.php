<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawalResource\Pages;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\WasteBankContext;
use App\Services\WithdrawalService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class WithdrawalResource extends Resource
{
    protected static ?string $model = Withdrawal::class;

    protected static ?string $modelLabel = 'Penarikan';

    protected static ?string $pluralModelLabel = 'Penarikan';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?string $pluralLabel = 'Penarikan Saldo';

    protected static ?string $navigationLabel = 'Penarikan';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        return auth()->user()?->isPlatformAdmin()
            ? $query
            : $query->where('waste_bank_id', app(WasteBankContext::class)->current()->id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('Anggota')
                ->options(fn () => User::query()
                    ->whereHas('wasteDeposits', fn ($query) => $query->where('waste_bank_id', app(WasteBankContext::class)->current()->id))
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('amount')
                ->label('Jumlah Penarikan')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->required(),
            Forms\Components\Textarea::make('note')
                ->label('Catatan')
                ->maxLength(1000),
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
                Tables\Columns\TextColumn::make('wasteBank.name')
                    ->label('Bank Sampah')
                    ->visible(fn (): bool => auth()->user()?->isPlatformAdmin() ?? false)
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah Penarikan')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Menunggu',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default => $state,
                    })
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('requested_date')
                    ->label('Tanggal Permintaan')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('processed_date')
                    ->label('Tanggal Diproses')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('note')
                    ->label('Catatan')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->searchPlaceholder('Cari penarikan...')
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Setujui Penarikan')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Withdrawal $record): bool => $record->status === 'pending')
                    ->authorize(fn (): bool => Auth::user()?->isBankAdmin() ?? false)
                    ->requiresConfirmation()
                    ->action(function (Withdrawal $record): void {
                        abort_unless(Auth::user()?->isBankAdmin(), 403);

                        app(WithdrawalService::class)->approve($record, Auth::id());
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Tolak Penarikan')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (Withdrawal $record): bool => $record->status === 'pending')
                    ->authorize(fn (): bool => Auth::user()?->isBankAdmin() ?? false)
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Alasan penolakan')
                            ->required()
                            ->minLength(3)
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Withdrawal $record, array $data): void {
                        abort_unless(Auth::user()?->isBankAdmin(), 403);

                        app(WithdrawalService::class)->reject($record, $data['reason'], Auth::id());
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWithdrawals::route('/'),
            'create' => Pages\CreateWithdrawal::route('/create'),
        ];
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
