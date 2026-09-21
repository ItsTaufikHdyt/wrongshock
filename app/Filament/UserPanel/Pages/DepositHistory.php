<?php

namespace App\Filament\UserPanel\Pages;

use App\Models\WasteDeposit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class DepositHistory extends UserDepositPage
{
    use WithPagination;

    protected static string $view = 'filament.user-panel.pages.deposit-history';

    protected static ?string $slug = 'setoran';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Riwayat Setoran';

    protected static ?string $title = 'Riwayat Setoran';

    protected static ?int $navigationSort = 2;

    public static function getNavigationItemActiveRoutePattern(): string
    {
        return static::getRouteName().'*';
    }

    public string $status = 'all';

    protected $queryString = [
        'status' => ['except' => 'all'],
    ];

    public function setStatus(string $status): void
    {
        if (! in_array($status, ['all', 'posted', 'cancelled', 'draft'], true)) {
            return;
        }

        $this->status = $status;
        $this->resetPage();
    }

    public function activeStatus(): string
    {
        return in_array($this->status, ['all', 'posted', 'cancelled', 'draft'], true)
            ? $this->status
            : 'all';
    }

    /** @return array<string, string> */
    public function filterOptions(): array
    {
        return [
            'all' => 'Semua',
            'posted' => 'Berhasil',
            'cancelled' => 'Dibatalkan',
            'draft' => 'Draft',
        ];
    }

    public function detailUrl(WasteDeposit $deposit): string
    {
        return DepositDetail::getUrl(['depositId' => $deposit->getKey()], panel: 'userPanel');
    }

    public function deposits(): LengthAwarePaginator
    {
        $query = $this->depositQuery(
            WasteDeposit::query()->where('user_id', Auth::id())
        )
            ->latest('deposit_date')
            ->latest('id');

        if ($this->activeStatus() !== 'all') {
            $query->where('status', $this->activeStatus());
        }

        return $query->paginate(10)->withQueryString();
    }

    /** @return array{posted_count: int, posted_value: int, cancelled_count: int} */
    public function summary(): array
    {
        $summary = WasteDeposit::query()
            ->where('user_id', Auth::id())
            ->selectRaw("SUM(CASE WHEN status = 'posted' THEN 1 ELSE 0 END) AS posted_count")
            ->selectRaw("SUM(CASE WHEN status = 'posted' THEN total_amount ELSE 0 END) AS posted_value")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count")
            ->first();

        return [
            'posted_count' => (int) $summary->posted_count,
            'posted_value' => (int) $summary->posted_value,
            'cancelled_count' => (int) $summary->cancelled_count,
        ];
    }
}
