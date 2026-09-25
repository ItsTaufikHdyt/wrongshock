<?php

namespace App\Console\Commands;

use App\Services\WasteBankAccountBackfillService;
use Illuminate\Console\Command;
use LogicException;

class BackfillWasteBankAccounts extends Command
{
    protected $signature = 'finance:backfill-bank-accounts';

    protected $description = 'Create and verify shadow per-bank financial accounts from attributed ledger history';

    public function handle(WasteBankAccountBackfillService $backfill): int
    {
        try {
            $result = $backfill->backfill();
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Bank account backfill complete: {$result['created']} created, {$result['unchanged']} unchanged, {$result['zero_balance']} zero-balance accounts.");

        return self::SUCCESS;
    }
}
