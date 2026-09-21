<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawalResource\Pages;
use App\Models\User;
use App\Models\Withdrawal;
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

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Bank Sampah';
    protected static ?string $pluralLabel = 'Penarikan Saldo';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('Pengguna')
                ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('amount')
                ->label('Jumlah (Rp)')
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
                    ->label('Pengguna')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('requested_date')
                    ->label('Diajukan')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('processed_date')
                    ->label('Diproses')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('note')
                    ->label('Catatan')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Setujui')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Withdrawal $record): bool => $record->status === 'pending')
                    ->authorize(fn (): bool => Auth::user()?->hasRole('admin') ?? false)
                    ->requiresConfirmation()
                    ->action(function (Withdrawal $record): void {
                        abort_unless(Auth::user()?->hasRole('admin'), 403);

                        app(WithdrawalService::class)->approve($record, Auth::id());
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (Withdrawal $record): bool => $record->status === 'pending')
                    ->authorize(fn (): bool => Auth::user()?->hasRole('admin') ?? false)
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Alasan penolakan')
                            ->required()
                            ->minLength(3)
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Withdrawal $record, array $data): void {
                        abort_unless(Auth::user()?->hasRole('admin'), 403);

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
