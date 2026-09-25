<?php

namespace App\Console\Commands;

use App\Services\BalanceReconciliationService;
use Illuminate\Console\Command;

class ReconcileFinance extends Command
{
    protected $signature = 'finance:reconcile
                            {--repair-cache : Repair cached balances from existing non-negative ledger totals}
                            {--per-bank : Report shadow per-bank account reconciliation}
                            {--aggregate : Report shadow aggregate account reconciliation}';

    protected $description = 'Report and explicitly reconcile cached balances with the financial ledger';

    public function handle(BalanceReconciliationService $service): int
    {
        $this->renderReport($service->report(), 'Before');

        if ($this->option('repair-cache')) {
            $summary = $service->repairCache();
            $this->info("Cache repaired: {$summary['repaired']}; skipped: {$summary['skipped']}; review: {$summary['review']}.");
            $this->renderReport($service->report(), 'After');
        }

        if ($this->option('per-bank')) {
            $this->renderBankReport($service->reportByBank());
        }

        if ($this->option('aggregate')) {
            $this->renderAggregateReport($service->reportAggregate());
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string,int|string>>  $rows
     */
    private function renderReport(array $rows, string $label): void
    {
        $this->line($label.' reconciliation:');
        $this->table(
            ['USER', 'CACHED', 'LEDGER', 'DIFFERENCE', 'STATUS'],
            array_map(fn (array $row): array => [
                $row['user_id'],
                $row['cached_balance'],
                $row['ledger_net'],
                $row['difference'],
                $row['status'],
            ], $rows),
        );
    }

    /** @param list<array<string, int|string>> $rows */
    private function renderBankReport(array $rows): void
    {
        $this->line('Per-bank reconciliation:');
        $this->table(
            ['USER', 'BANK', 'ACCOUNT', 'LEDGER', 'DIFFERENCE', 'STATUS'],
            array_map(fn (array $row): array => [
                $row['user_id'],
                $row['waste_bank_id'],
                $row['cached_account_balance'],
                $row['ledger_net'],
                $row['difference'],
                $row['status'],
            ], $rows),
        );
    }

    /** @param list<array<string, int|string>> $rows */
    private function renderAggregateReport(array $rows): void
    {
        $this->line('Aggregate account reconciliation:');
        $this->table(
            ['USER', 'LEGACY', 'ACCOUNT TOTAL', 'GLOBAL LEDGER', 'ACCOUNT STATUS', 'LEGACY STATUS'],
            array_map(fn (array $row): array => [
                $row['user_id'],
                $row['legacy_balance'],
                $row['account_balance_total'],
                $row['global_ledger_net'],
                $row['account_status'],
                $row['legacy_status'],
            ], $rows),
        );
    }
}
