<?php

namespace App\Console\Commands;

use App\Services\LedgerBankAttributionService;
use App\Services\LedgerBankBackfillService;
use Illuminate\Console\Command;

class BackfillLedgerBanks extends Command
{
    protected $signature = 'finance:backfill-ledger-banks';

    protected $description = 'Backfill provable Waste Bank ownership on ledger entries';

    public function handle(LedgerBankAttributionService $attribution, LedgerBankBackfillService $backfill): int
    {
        $results = $attribution->analyzeAll();
        $summary = $attribution->summary($results);
        $this->line("Analyzed {$summary['total']} ledger entries: {$summary['derivable']} derivable, {$summary['ambiguous']} ambiguous, {$summary['missing']} missing, {$summary['mismatch']} mismatched.");

        if (! $attribution->isSafe($results)) {
            $this->error('Backfill aborted. Resolve every unsafe ledger entry before changing bank ownership.');

            return self::FAILURE;
        }

        $result = $backfill->backfill();
        $this->info("Ledger bank backfill complete: {$result['updated']} updated, {$result['unchanged']} unchanged.");

        return self::SUCCESS;
    }
}
