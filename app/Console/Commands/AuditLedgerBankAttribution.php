<?php

namespace App\Console\Commands;

use App\Services\LedgerBankAttributionService;
use Illuminate\Console\Command;

class AuditLedgerBankAttribution extends Command
{
    protected $signature = 'finance:audit-bank-attribution';

    protected $description = 'Audit ledger entries for provable Waste Bank ownership without changing data';

    public function handle(LedgerBankAttributionService $service): int
    {
        $results = $service->analyzeAll();
        $summary = $service->summary($results);

        $this->table(
            ['METRIC', 'COUNT'],
            [
                ['Total ledger entries', $summary['total']],
                ['Already attributed', $summary['already_attributed']],
                ['Derivable', $summary['derivable']],
                ['Ambiguous', $summary['ambiguous']],
                ['Missing/orphaned', $summary['missing']],
                ['Mismatch', $summary['mismatch']],
            ],
        );

        $this->line('By ledger type:');
        $this->table(
            ['TYPE', 'TOTAL', 'DERIVABLE', 'AMBIGUOUS', 'MISSING', 'MISMATCH'],
            collect($summary['by_type'])->map(fn (array $row, string $type): array => [
                $type,
                $row['total'],
                $row['derivable'],
                $row['ambiguous'],
                $row['missing'],
                $row['mismatch'],
            ])->values()->all(),
        );

        $problems = $results->filter(fn (array $result): bool => $result['status'] !== 'DERIVABLE');
        if ($problems->isNotEmpty()) {
            $this->error('Unsafe ledger entries:');
            $this->table(
                ['LEDGER', 'TYPE', 'STATUS', 'SOURCE', 'REASON'],
                $problems->map(fn (array $result): array => [
                    $result['ledger_id'],
                    $result['type'],
                    $result['status'],
                    $result['source_type'].'#'.$result['source_id'],
                    $result['reason'],
                ])->values()->all(),
            );

            return self::FAILURE;
        }

        $this->info('All ledger entries have provable Waste Bank ownership.');

        return self::SUCCESS;
    }
}
