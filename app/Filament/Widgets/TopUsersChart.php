<?php

namespace App\Filament\Widgets;

use App\Services\WasteBankContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopUsersChart extends ChartWidget
{
    protected static ?string $heading = '5 Pengguna Teraktif (Setoran Terbanyak)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $topUsers = DB::table('users')
            ->join('waste_deposits', 'users.id', '=', 'waste_deposits.user_id')
            ->select('users.name', DB::raw('SUM(waste_deposits.total_amount) as total_setor'))
            ->where('waste_deposits.waste_bank_id', app(WasteBankContext::class)->current()->id)
            ->where('waste_deposits.status', 'posted')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_setor')
            ->limit(5)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Total Setoran (Rp)',
                    'data' => $topUsers->pluck('total_setor'),
                    'backgroundColor' => [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                    ],
                ],
            ],
            'labels' => $topUsers->pluck('name'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {

        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'indexAxis' => 'y', // Bar horizontal
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return "Rp " + value.toLocaleString(); }',
                    ],
                ],
            ],
        ];
    }
}
