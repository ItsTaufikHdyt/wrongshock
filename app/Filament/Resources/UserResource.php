<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\District;
use App\Models\SubDistrict;
use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteBankAccount;
use App\Models\WasteBankMember;
use App\Services\BankMembershipService;
use App\Services\WasteBankContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Anggota';

    protected static ?string $pluralModelLabel = 'Anggota';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Anggota';

    protected static ?string $pluralLabel = 'Anggota';

    protected static ?string $navigationGroup = 'Anggota';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        if (auth()->user()?->isPlatformAdmin()) {
            return parent::getEloquentQuery()->whereHas('roles', fn ($query) => $query->where('name', 'user'));
        }

        $bankId = app(WasteBankContext::class)->current()->id;

        return parent::getEloquentQuery()
            ->addSelect([
                'bank_membership_status' => WasteBankMember::query()
                    ->select('status')
                    ->whereColumn('user_id', 'users.id')
                    ->where('waste_bank_id', $bankId)
                    ->limit(1),
                'bank_joined_at' => WasteBankMember::query()
                    ->select('joined_at')
                    ->whereColumn('user_id', 'users.id')
                    ->where('waste_bank_id', $bankId)
                    ->limit(1),
            ])
            ->whereHas('roles', fn ($query) => $query->where('name', 'user'))
            ->whereHas('bankMemberships', fn ($query) => $query
                ->where('waste_bank_id', $bankId));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nama Lengkap')
                                ->required(),
                            Forms\Components\TextInput::make('number')
                                ->label('Nomor Anggota')
                                ->disabled()
                                ->default('Dibuat otomatis')
                                ->dehydrated(false),
                        ]),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->unique(ignoreRecord: true)
                                ->required(),
                            Forms\Components\TextInput::make('password')
                                ->label('Kata Sandi')
                                ->password()
                                ->required(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord)
                                ->nullable()
                                ->dehydrated(fn ($state) => filled($state))
                                ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null),
                            Forms\Components\TextInput::make('password_confirmation')
                                ->label('Konfirmasi Kata Sandi')
                                ->password()
                                ->required(fn ($livewire) => $livewire instanceof Pages\CreateUser)
                                ->same('password')
                                ->dehydrated(false),
                        ]),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('district_id')
                                ->label('Kecamatan')
                                ->required()
                                ->options(fn () => District::pluck('name', 'id'))
                                ->live()
                                ->afterStateUpdated(fn (Forms\Set $set) => $set('sub_district_id', null))
                                ->searchable(),
                            Forms\Components\Select::make('sub_district_id')
                                ->label('Kelurahan')
                                ->required()
                                ->rules(fn (Forms\Get $get) => [
                                    Rule::exists('sub_districts', 'id')->where('district_id', $get('district_id')),
                                ])
                                ->options(
                                    fn ($get) => SubDistrict::where('district_id', $get('district_id'))->pluck('name', 'id')
                                )
                                ->searchable(),
                        ]),
                    Forms\Components\Textarea::make('address')
                        ->label('Alamat')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\FileUpload::make('image')
                        ->label('Foto Profil')
                        ->image(),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            1 => 'Aktif',
                            0 => 'Nonaktif',
                        ])
                        ->required(fn ($livewire) => ! $livewire instanceof Pages\CreateUser)
                        ->native(false)
                        ->visible(fn ($livewire) => ! $livewire instanceof Pages\CreateUser),
                    Forms\Components\Select::make('waste_bank_id')
                        ->label('Bank Sampah')
                        ->options(fn () => WasteBank::query()->where('status', true)->orderBy('name')->pluck('name', 'id'))
                        ->visible(fn ($livewire): bool => auth()->user()?->isPlatformAdmin()
                            && $livewire instanceof Pages\CreateUser)
                        ->required(fn ($livewire): bool => auth()->user()?->isPlatformAdmin()
                            && $livewire instanceof Pages\CreateUser),
                ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Anggota')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\ImageColumn::make('image')
                    ->label('Foto Profil')
                    ->circular(),
                Tables\Columns\TextColumn::make('number')
                    ->label('Nomor Anggota')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state == 1 ? 'Aktif' : 'Nonaktif')
                    ->colors([
                        'success' => 1,   // hijau untuk status = 1
                        'danger' => 0,   // merah untuk status = 0
                    ]),
                Tables\Columns\TextColumn::make('bank_membership_status')
                    ->label('Status Keanggotaan')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state === 'active' ? 'Aktif' : 'Nonaktif')
                    ->visible(fn (): bool => auth()->user()?->isBankAdmin() ?? false),
                Tables\Columns\TextColumn::make('bank_joined_at')
                    ->label('Tanggal Bergabung')
                    ->date('d M Y')
                    ->visible(fn (): bool => auth()->user()?->isBankAdmin() ?? false),
                Tables\Columns\TextColumn::make('membership_summary')
                    ->label('Keanggotaan')
                    ->state(fn (User $record): string => $record->bankMemberships()
                        ->with('wasteBank')
                        ->get()
                        ->map(fn (WasteBankMember $membership): string => "{$membership->wasteBank->code} (".($membership->status === 'active' ? 'Aktif' : 'Nonaktif').')')
                        ->implode(', '))
                    ->visible(fn (): bool => auth()->user()?->isPlatformAdmin() ?? false),
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
                    ->formatStateUsing(fn ($state) => Str::limit($state, 30)),
                Tables\Columns\TagsColumn::make('roles.name')
                    ->label('Peran')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('balance')
                    ->label(fn (): string => auth()->user()?->isBankAdmin() ? 'Saldo Bank Saat Ini' : 'Total Saldo')
                    ->sortable()
                    ->searchable()
                    ->state(fn (User $record) => auth()->user()?->isBankAdmin()
                        ? WasteBankAccount::query()
                            ->where('user_id', $record->id)
                            ->where('waste_bank_id', app(WasteBankContext::class)->current()->id)
                            ->value('balance')
                        : $record->balance)
                    ->formatStateUsing(fn ($state) => $state !== null ? 'Rp '.number_format($state, 0, '', '.') : 'Rp 0'),
                Tables\Columns\TextColumn::make('bank_balances')
                    ->label('Saldo per Bank Sampah')
                    ->state(fn (User $record): string => WasteBankAccount::query()
                        ->where('user_id', $record->id)
                        ->with('wasteBank')
                        ->get()
                        ->map(fn (WasteBankAccount $account): string => $account->wasteBank->code.' Rp '.number_format($account->balance, 0, '', '.'))
                        ->implode(', '))
                    ->visible(fn (): bool => auth()->user()?->isPlatformAdmin() ?? false),
            ])
            ->filters([
                //
            ])
            ->searchPlaceholder('Cari anggota...')
            ->actions([
                Tables\Actions\ViewAction::make()->label('Lihat Detail'),
                Tables\Actions\EditAction::make()
                    ->label('Edit Anggota')
                    ->visible(fn (): bool => auth()->user()?->isPlatformAdmin() ?? false),
                Tables\Actions\Action::make('nonaktifkanMembership')
                    ->label('Nonaktifkan Keanggotaan')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form(fn (User $record): array => self::membershipBankField($record, 'active'))
                    ->visible(fn (User $record): bool => self::canManageMembership($record, 'active'))
                    ->action(fn (User $record, array $data): WasteBankMember => app(BankMembershipService::class)
                        ->deactivateMembership(auth()->user(), $record, self::actionBank($data))),
                Tables\Actions\Action::make('aktifkanMembership')
                    ->label('Aktifkan Kembali')
                    ->color('success')
                    ->form(fn (User $record): array => self::membershipBankField($record, 'inactive'))
                    ->visible(fn (User $record): bool => self::canManageMembership($record, 'inactive'))
                    ->action(fn (User $record, array $data): WasteBankMember => app(BankMembershipService::class)
                        ->reactivateMembership(auth()->user(), $record, self::actionBank($data))),
                Tables\Actions\Action::make('tambahMembership')
                    ->label('Tambah Keanggotaan')
                    ->visible(fn (): bool => auth()->user()?->isPlatformAdmin() ?? false)
                    ->form([
                        Forms\Components\Select::make('waste_bank_id')
                            ->label('Bank Sampah')
                            ->options(fn (User $record): array => WasteBank::query()
                                ->where('status', true)
                                ->whereDoesntHave('memberMemberships', fn ($query) => $query->where('user_id', $record->id))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        app(BankMembershipService::class)->addMember(
                            auth()->user(),
                            $record,
                            WasteBank::query()->findOrFail($data['waste_bank_id']),
                        );
                    }),
            ])
            ->bulkActions([]);
    }

    private static function membershipBankField(User $record, string $status): array
    {
        return [
            Forms\Components\Select::make('waste_bank_id')
                ->label('Bank Sampah')
                ->options(fn (): array => WasteBankMember::query()
                    ->where('user_id', $record->id)
                    ->where('status', $status)
                    ->with('wasteBank')
                    ->get()
                    ->mapWithKeys(fn (WasteBankMember $membership): array => [$membership->waste_bank_id => $membership->wasteBank->name])
                    ->all())
                ->visible(fn (): bool => auth()->user()?->isPlatformAdmin() ?? false)
                ->required(fn (): bool => auth()->user()?->isPlatformAdmin() ?? false),
        ];
    }

    private static function actionBank(array $data): ?WasteBank
    {
        if (auth()->user()?->isBankAdmin()) {
            return null;
        }

        $bankId = $data['waste_bank_id'] ?? null;

        return $bankId ? WasteBank::query()->findOrFail($bankId) : null;
    }

    private static function canManageMembership(User $record, string $status): bool
    {
        $actor = auth()->user();
        if (! $actor?->isPlatformAdmin() && ! $actor?->isBankAdmin()) {
            return false;
        }

        $query = WasteBankMember::query()->where('user_id', $record->id)->where('status', $status);
        if ($actor->isBankAdmin()) {
            $query->where('waste_bank_id', app(WasteBankContext::class)->current()->id);
        }

        return $query->exists();
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
