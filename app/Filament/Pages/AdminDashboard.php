<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DistrictResource;
use App\Filament\Resources\SubDistrictResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\WasteDepositResource;
use App\Filament\Resources\WasteItemResource;
use App\Filament\Resources\WithdrawalResource;
use App\Models\User;
use App\Models\WasteDeposit;
use App\Services\WasteBankContext;
use Filament\Pages\Dashboard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboard extends Dashboard
{
    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = -2;

    protected static string $view = 'filament.admin.dashboard';

    public function getColumns(): int|string|array
    {
        return 1;
    }

    public function getDashboardData(): array
    {
        if (auth()->user()?->isPlatformAdmin()) {
            return $this->getPlatformDashboardData();
        }

        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $trendStart = $monthStart->copy()->subMonths(5);
        $wasteBank = app(WasteBankContext::class)->current();

        $memberQuery = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'user'))
            ->whereHas('bankMemberships', fn ($query) => $query
                ->where('waste_bank_id', $wasteBank->id)
                ->where('status', 'active'));

        $monthlyDeposits = WasteDeposit::query()
            ->where('waste_bank_id', $wasteBank->id)
            ->where('status', 'posted')
            ->whereBetween('deposit_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);

        $trendRows = WasteDeposit::query()
            ->selectRaw('deposit_date, COUNT(*) as deposit_count, SUM(total_amount) as total_amount')
            ->where('status', 'posted')
            ->where('waste_bank_id', $wasteBank->id)
            ->whereBetween('deposit_date', [$trendStart->toDateString(), $monthEnd->toDateString()])
            ->groupBy('deposit_date')
            ->get();

        $trend = collect(range(0, 5))->map(function (int $offset) use ($trendStart, $trendRows): array {
            $month = $trendStart->copy()->addMonths($offset);
            $rows = $trendRows->filter(fn ($row) => Carbon::parse($row->deposit_date)->isSameMonth($month));

            return [
                'label' => $this->monthLabel($month),
                'amount' => (int) $rows->sum('total_amount'),
                'count' => (int) $rows->sum('deposit_count'),
            ];
        })->values()->all();

        $composition = DB::table('waste_deposit_items')
            ->join('waste_deposits', 'waste_deposit_items.waste_deposit_id', '=', 'waste_deposits.id')
            ->leftJoin('waste_items', 'waste_deposit_items.waste_item_id', '=', 'waste_items.id')
            ->selectRaw("COALESCE(waste_deposit_items.category_snapshot, waste_items.category, 'Tanpa kategori') as category, SUM(waste_deposit_items.subtotal) as total_amount")
            ->where('waste_deposits.status', 'posted')
            ->where('waste_deposits.waste_bank_id', $wasteBank->id)
            ->whereBetween('waste_deposits.deposit_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->groupByRaw("COALESCE(waste_deposit_items.category_snapshot, waste_items.category, 'Tanpa kategori')")
            ->orderByDesc('total_amount')
            ->limit(6)
            ->get()
            ->map(fn ($row): array => [
                'category' => $row->category,
                'amount' => (int) $row->total_amount,
            ])
            ->all();

        $recentDeposits = WasteDeposit::query()
            ->where('waste_bank_id', $wasteBank->id)
            ->with('user:id,name,number')
            ->latest('deposit_date')
            ->latest('id')
            ->limit(6)
            ->get();

        $members = $memberQuery->count();
        $activeMembers = (clone $memberQuery)->where('status', 1)->count();
        $activeDepositors = (clone $memberQuery)
            ->whereHas('wasteDeposits', function ($query) use ($monthStart, $monthEnd): void {
                $query->where('status', 'posted')
                    ->whereBetween('deposit_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            })
            ->count();

        return [
            'platform' => false,
            'admin' => auth()->user(),
            'waste_bank' => $wasteBank,
            'period' => $monthStart->translatedFormat('F Y'),
            'members' => $members,
            'active_members' => $activeMembers,
            'active_depositors' => $activeDepositors,
            'monthly_deposit_count' => (clone $monthlyDeposits)->count(),
            'monthly_deposit_amount' => (int) (clone $monthlyDeposits)->sum('total_amount'),
            'trend' => $trend,
            'has_trend_data' => collect($trend)->sum('amount') > 0,
            'composition' => $composition,
            'has_composition_data' => $composition !== [],
            'recent_deposits' => $recentDeposits,
            'inactive_members' => (clone $memberQuery)->where('status', 0)->count(),
            'pending_withdrawals' => DB::table('withdrawals')->where('waste_bank_id', $wasteBank->id)->where('status', 'pending')->count(),
            'links' => [
                'members' => UserResource::getUrl('index', panel: 'adminPanel'),
                'deposits' => WasteDepositResource::getUrl('index', panel: 'adminPanel'),
                'waste_items' => WasteItemResource::getUrl('index', panel: 'adminPanel'),
                'withdrawals' => WithdrawalResource::getUrl('index', panel: 'adminPanel'),
                'districts' => DistrictResource::getUrl('index', panel: 'adminPanel'),
                'sub_districts' => SubDistrictResource::getUrl('index', panel: 'adminPanel'),
            ],
        ];
    }

    private function getPlatformDashboardData(): array
    {
        $postedDeposits = WasteDeposit::query()->where('status', 'posted');

        return [
            'platform' => true,
            'admin' => auth()->user(),
            'bank_count' => \App\Models\WasteBank::query()->count(),
            'active_bank_count' => \App\Models\WasteBank::query()->where('status', true)->count(),
            'member_count' => User::query()->whereHas('roles', fn ($query) => $query->where('name', 'user'))->count(),
            'deposit_count' => $postedDeposits->count(),
            'deposit_amount' => (int) (clone $postedDeposits)->sum('total_amount'),
            'links' => [
                'banks' => \App\Filament\Resources\WasteBankResource::getUrl('index', panel: 'adminPanel'),
                'staff' => \App\Filament\Resources\WasteBankStaffResource::getUrl('index', panel: 'adminPanel'),
                'members' => UserResource::getUrl('index', panel: 'adminPanel'),
                'deposits' => WasteDepositResource::getUrl('index', panel: 'adminPanel'),
            ],
        ];
    }

    public function formatCurrency(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'posted' => 'Berhasil',
            'cancelled' => 'Dibatalkan',
            default => 'Draft',
        };
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'posted' => 'is-success',
            'cancelled' => 'is-danger',
            default => 'is-neutral',
        };
    }

    private function monthLabel(Carbon $month): string
    {
        return [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ][$month->month].' '.$month->year;
    }
}
