<?php

namespace App\Filament\UserPanel\Pages\Auth;

use App\Models\District;
use App\Models\SubDistrict;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Auth\EditProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class Profile extends EditProfile
{
    protected static string $view = 'filament.user-panel.pages.profile';

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make()
                ->extraAttributes(['class' => 'ws-profile-hero'])
                ->schema([
                    ViewField::make('profileIdentity')
                        ->view('filament.user-panel.partials.profile-identity')
                        ->dehydrated(false),
                    FileUpload::make('image')
                        ->label(fn (): string => filled($this->getUser()->image) ? 'Ganti Foto' : 'Upload Foto')
                        ->helperText('JPEG, PNG, atau WebP. Maksimal 2 MB. Foto baru disimpan bersama perubahan profil.')
                        ->avatar()
                        ->image()
                        ->disk('public')
                        ->directory(fn (): string => 'profile-images/'.Filament::auth()->id())
                        ->visibility('public')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(2048)
                        ->nullable(),
                ])
                ->columns(['default' => 1, 'md' => 2]),

            Grid::make(['default' => 1, 'lg' => 2])
                ->schema([
                    Section::make('Informasi Pribadi')
                        ->description('Informasi utama yang digunakan pada akun Anda.')
                        ->icon('heroicon-o-user-circle')
                        ->extraAttributes(['class' => 'ws-profile-card'])
                        ->schema([
                            $this->getNameFormComponent()->label('Nama Lengkap'),
                            $this->getEmailFormComponent()->label('Email'),
                            Placeholder::make('memberNumber')
                                ->label('Nomor Anggota')
                                ->content(fn (): string => (string) $this->getUser()->number),
                        ]),

                    Section::make('Alamat')
                        ->description('Pastikan wilayah dan alamat lengkap Anda sesuai.')
                        ->icon('heroicon-o-map-pin')
                        ->extraAttributes(['class' => 'ws-profile-card'])
                        ->schema([
                            Select::make('district_id')
                                ->label('Kecamatan')
                                ->options(fn () => District::query()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->exists('districts', 'id')
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('sub_district_id', null)),
                            Select::make('sub_district_id')
                                ->label('Kelurahan')
                                ->options(fn (Get $get) => SubDistrict::query()
                                    ->where('district_id', $get('district_id'))
                                    ->orderBy('name')
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->rules(fn (Get $get): array => [
                                    Rule::exists('sub_districts', 'id')->where('district_id', $get('district_id')),
                                ]),
                            Textarea::make('address')
                                ->label('Alamat Lengkap')
                                ->rows(3)
                                ->maxLength(255),
                        ]),
                ]),

            Section::make('Keamanan Akun')
                ->description('Kosongkan jika Anda tidak ingin mengubah password.')
                ->icon('heroicon-o-lock-closed')
                ->extraAttributes(['class' => 'ws-profile-card ws-profile-security'])
                ->schema([
                    $this->getPasswordFormComponent()->label('Password Baru'),
                    $this->getPasswordConfirmationFormComponent()->label('Konfirmasi Password Baru'),
                ])
                ->columns(['default' => 1, 'md' => 2]),
        ]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        Gate::authorize('update', $record);

        $data = Arr::only($data, [
            'name',
            'email',
            'district_id',
            'sub_district_id',
            'address',
            'image',
            'password',
        ]);

        if (isset($data['image']) && $data['image'] !== $record->image) {
            $ownedDirectory = 'profile-images/'.$record->getKey().'/';

            abort_unless(str_starts_with($data['image'], $ownedDirectory), 403);
        }

        $record->update($data);

        return $record;
    }

    protected function getFormActions(): array
    {
        return [$this->getSaveFormAction()];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Simpan Perubahan')
            ->icon('heroicon-o-check')
            ->extraAttributes(['class' => 'ws-profile-save']);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Profil berhasil diperbarui.';
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getUrl(panel: 'userPanel');
    }
}
