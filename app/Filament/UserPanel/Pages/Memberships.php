<?php

namespace App\Filament\UserPanel\Pages;

use App\Models\WasteBankMember;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Memberships extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Keanggotaan Saya';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Keanggotaan Bank Sampah';

    protected static string $view = 'filament.user-panel.pages.memberships';

    public function memberships()
    {
        return WasteBankMember::query()
            ->with(['wasteBank.district', 'wasteBank.subDistrict'])
            ->where('user_id', Auth::id())
            ->orderByDesc('joined_at')
            ->get();
    }
}
