<?php

namespace App\Filament\UserPanel\Pages;

use App\Models\WasteDeposit;
use Illuminate\Support\Facades\Auth;

class DepositDetail extends UserDepositPage
{
    protected static string $view = 'filament.user-panel.pages.deposit-detail';

    protected static ?string $slug = 'setoran/{depositId}';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Setoran Sampah';

    public WasteDeposit $deposit;

    public function mount(int|string $depositId): void
    {
        $this->deposit = $this->depositQuery(WasteDeposit::query())
            ->where('user_id', Auth::id())
            ->findOrFail($depositId);
    }
}
