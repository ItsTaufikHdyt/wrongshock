<?php

namespace App\Filament\UserPanel\Pages;

use App\Filament\UserPanel\Pages\Auth\Profile;
use App\Models\WasteDeposit;
use Illuminate\Support\Facades\Auth;

class UserDashboard extends UserDepositPage
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Beranda';

    protected static string $view = 'filament.user-panel.pages.user-dashboard';

    protected static ?string $title = 'Beranda';

    protected static ?int $navigationSort = 1;

    public $user;

    public $deposits;

    public function mount(): void
    {
        $this->user = Auth::user();
        $this->deposits = $this->depositQuery(WasteDeposit::query())
            ->where('user_id', $this->user->id)
            ->latest('deposit_date')
            ->latest('id')
            ->take(5)
            ->get();
    }

    public function historyUrl(): string
    {
        return DepositHistory::getUrl(panel: 'userPanel');
    }

    public function profileUrl(): string
    {
        return Profile::getUrl(panel: 'userPanel');
    }
}
