<?php

namespace App\Filament\UserPanel\Pages;

use App\Services\MemberQrCodeService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class MemberCard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationLabel = 'Kartu Anggota';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'kartu-anggota';

    protected static ?string $title = 'Kartu Anggota';

    protected static string $view = 'filament.user-panel.pages.member-card';

    public function user()
    {
        return Auth::user();
    }

    public function qrCode(): ?string
    {
        $user = $this->user();

        if (! filled($user?->qr_token)) {
            return null;
        }

        return app(MemberQrCodeService::class)->dataUri($user);
    }
}
