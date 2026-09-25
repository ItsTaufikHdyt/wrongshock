<?php

namespace App\Filament\UserPanel\Pages;

use App\Filament\UserPanel\Pages\Auth\Profile;
use App\Models\WasteBankAccount;
use App\Models\WasteBankMember;
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

    public $memberships;

    public $accounts;

    public function mount(): void
    {
        $this->user = Auth::user();
        $this->deposits = $this->depositQuery(WasteDeposit::query())
            ->where('user_id', $this->user->id)
            ->latest('deposit_date')
            ->latest('id')
            ->take(5)
            ->get();
        $this->memberships = WasteBankMember::query()
            ->with('wasteBank')
            ->where('user_id', $this->user->id)
            ->orderByDesc('joined_at')
            ->get();
        $this->accounts = WasteBankAccount::query()
            ->with('wasteBank')
            ->where('user_id', $this->user->id)
            ->orderBy('waste_bank_id')
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

    public function membershipsUrl(): string
    {
        return Memberships::getUrl(panel: 'userPanel');
    }
}
