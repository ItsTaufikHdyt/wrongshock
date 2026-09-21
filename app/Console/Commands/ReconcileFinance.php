<?php

namespace App\Console\Commands;

use App\Services\BalanceReconciliationService;
use Illuminate\Console\Command;

class ReconcileFinance extends Command
{
    protected $signature = 'finance:reconcile
                            {--create-opening-balances : Create eligible opening balance ledger entries}
                            {--repair-cache : Repair cached balances from existing non-negative ledger totals}';

    protected $description = 'Report and explicitly reconcile cached balances with the financial ledger';

    public function handle(BalanceReconciliationService $service): int
    {
        if ($this->option('create-opening-balances') && $this->option('repair-cache')) {
            $this->error('Choose one mutation mode at a time.');

            return self::INVALID;
        }

        $this->renderReport($service->report(), 'Before');

        if ($this->option('create-opening-balances')) {
            $summary = $service->createOpeningBalances();
            $this->info("Opening balances created: {$summary['created']}; skipped: {$summary['skipped']}; review: {$summary['review']}.");
            $this->renderReport($service->report(), 'After');
        }

        if ($this->option('repair-cache')) {
            $summary = $service->repairCache();
            $this->info("Cache repaired: {$summary['repaired']}; skipped: {$summary['skipped']}; review: {$summary['review']}.");
            $this->renderReport($service->report(), 'After');
        }

        return self::SUCCESS;
    }

    /**
     * @param list<array<string,int|string>> $rows
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
}
