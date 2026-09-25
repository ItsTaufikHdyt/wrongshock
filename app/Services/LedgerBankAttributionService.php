<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\WasteBank;
use App\Models\WasteDeposit;
use App\Models\Withdrawal;
use Illuminate\Support\Collection;

class LedgerBankAttributionService
{
    /**
     * @return array{ledger_id:int,user_id:int,type:string,source_type:?string,source_id:?int,status:string,waste_bank_id:?int,already_attributed:bool,reason:string}
     */
    public function analyze(LedgerEntry $entry): array
    {
        $result = [
            'ledger_id' => (int) $entry->id,
            'user_id' => (int) $entry->user_id,
            'type' => (string) $entry->type,
            'source_type' => $entry->reference_type,
            'source_id' => $entry->reference_id !== null ? (int) $entry->reference_id : null,
            'status' => 'AMBIGUOUS',
            'waste_bank_id' => null,
            'already_attributed' => false,
            'reason' => 'No provable Waste Bank ownership exists.',
        ];

        if (in_array($entry->type, ['deposit_credit', 'deposit_reversal'], true)) {
            return $this->analyzeDepositSource($entry, $result);
        }

        if (in_array($entry->type, ['withdrawal_debit', 'withdrawal_reversal'], true)) {
            return $this->analyzeWithdrawalSource($entry, $result);
        }

        if ($entry->waste_bank_id !== null && WasteBank::query()->whereKey($entry->waste_bank_id)->exists()) {
            $result['status'] = 'DERIVABLE';
            $result['waste_bank_id'] = (int) $entry->waste_bank_id;
            $result['already_attributed'] = true;
            $result['reason'] = 'Direct ledger bank ownership is present.';
        } else {
            $result['reason'] = match ($entry->type) {
                'opening_balance' => 'Opening balance has no explicit bank ownership evidence.',
                'adjustment' => 'Adjustment has no explicit bank ownership evidence.',
                default => 'Ledger type has no supported bank attribution rule.',
            };
        }

        return $result;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function analyzeAll(): Collection
    {
        return LedgerEntry::query()
            ->orderBy('id')
            ->get()
            ->map(fn (LedgerEntry $entry): array => $this->analyze($entry));
    }

    /** @param Collection<int, array<string, mixed>> $results */
    public function summary(Collection $results): array
    {
        return [
            'total' => $results->count(),
            'already_attributed' => $results->where('already_attributed', true)->count(),
            'derivable' => $results->where('status', 'DERIVABLE')->count(),
            'ambiguous' => $results->where('status', 'AMBIGUOUS')->count(),
            'missing' => $results->where('status', 'MISSING')->count(),
            'mismatch' => $results->where('status', 'MISMATCH')->count(),
            'by_type' => $results->groupBy('type')->map(fn (Collection $typeResults): array => [
                'total' => $typeResults->count(),
                'derivable' => $typeResults->where('status', 'DERIVABLE')->count(),
                'ambiguous' => $typeResults->where('status', 'AMBIGUOUS')->count(),
                'missing' => $typeResults->where('status', 'MISSING')->count(),
                'mismatch' => $typeResults->where('status', 'MISMATCH')->count(),
            ])->all(),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $results */
    public function isSafe(Collection $results): bool
    {
        return $results->every(fn (array $result): bool => $result['status'] === 'DERIVABLE');
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function analyzeDepositSource(LedgerEntry $entry, array $result): array
    {
        if ($entry->reference_type !== WasteDeposit::class || $entry->reference_id === null) {
            $result['status'] = 'MISSING';
            $result['reason'] = 'Expected a WasteDeposit source reference.';

            return $result;
        }

        $deposit = WasteDeposit::query()->find($entry->reference_id);
        if (! $deposit) {
            $result['status'] = 'MISSING';
            $result['reason'] = 'Referenced WasteDeposit does not exist.';

            return $result;
        }

        if ((int) $deposit->user_id !== (int) $entry->user_id) {
            $result['status'] = 'MISMATCH';
            $result['reason'] = 'Ledger user differs from WasteDeposit user.';

            return $result;
        }

        return $this->withDerivedBank($entry, $result, $deposit->waste_bank_id, 'WasteDeposit source');
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function analyzeWithdrawalSource(LedgerEntry $entry, array $result): array
    {
        if ($entry->reference_type !== Withdrawal::class || $entry->reference_id === null) {
            $result['status'] = 'MISSING';
            $result['reason'] = 'Expected a Withdrawal source reference.';

            return $result;
        }

        $withdrawal = Withdrawal::query()->find($entry->reference_id);
        if (! $withdrawal) {
            $result['status'] = 'MISSING';
            $result['reason'] = 'Referenced Withdrawal does not exist.';

            return $result;
        }

        if ((int) $withdrawal->user_id !== (int) $entry->user_id) {
            $result['status'] = 'MISMATCH';
            $result['reason'] = 'Ledger user differs from Withdrawal user.';

            return $result;
        }

        return $this->withDerivedBank($entry, $result, $withdrawal->waste_bank_id, 'Withdrawal source');
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function withDerivedBank(LedgerEntry $entry, array $result, mixed $bankId, string $source): array
    {
        if ($bankId === null) {
            $result['status'] = 'MISSING';
            $result['reason'] = "{$source} has no Waste Bank ownership.";

            return $result;
        }

        if (! WasteBank::query()->whereKey($bankId)->exists()) {
            $result['status'] = 'MISSING';
            $result['reason'] = "{$source} points to a missing Waste Bank.";

            return $result;
        }

        if ($entry->waste_bank_id !== null && (int) $entry->waste_bank_id !== (int) $bankId) {
            $result['status'] = 'MISMATCH';
            $result['reason'] = 'Ledger bank differs from source bank.';

            return $result;
        }

        $result['status'] = 'DERIVABLE';
        $result['waste_bank_id'] = (int) $bankId;
        $result['already_attributed'] = $entry->waste_bank_id !== null;
        $result['reason'] = "Bank derived from {$source}.";

        return $result;
    }
}
