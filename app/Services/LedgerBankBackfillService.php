<?php

namespace App\Services;

use App\Models\LedgerEntry;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use LogicException;

class LedgerBankBackfillService
{
    public function __construct(
        private LedgerBankAttributionService $attribution,
        private DatabaseManager $database,
    ) {}

    /**
     * @return array{analyzed:int,updated:int,unchanged:int}
     */
    public function backfill(): array
    {
        $results = $this->attribution->analyzeAll();
        if (! $this->attribution->isSafe($results)) {
            throw new LogicException($this->unsafeMessage($results));
        }

        $updated = 0;
        $unchanged = 0;

        $this->database->transaction(function () use ($results, &$updated, &$unchanged): void {
            foreach ($results as $result) {
                $entry = LedgerEntry::query()->findOrFail($result['ledger_id']);
                if ($entry->waste_bank_id === null) {
                    LedgerEntry::query()->whereKey($entry->id)->update([
                        'waste_bank_id' => $result['waste_bank_id'],
                    ]);
                    $updated++;
                } else {
                    $unchanged++;
                }
            }
        });

        return [
            'analyzed' => $results->count(),
            'updated' => $updated,
            'unchanged' => $unchanged,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $results */
    private function unsafeMessage(Collection $results): string
    {
        $problem = $results->first(fn (array $result): bool => $result['status'] !== 'DERIVABLE');

        return sprintf(
            'Ledger bank backfill aborted: ledger #%d is %s (%s).',
            $problem['ledger_id'],
            $problem['status'],
            $problem['reason'],
        );
    }
}
