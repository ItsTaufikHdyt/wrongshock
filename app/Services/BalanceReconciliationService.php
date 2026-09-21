<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BalanceReconciliationService
{
    /**
     * @return array{user_id:int,cached_balance:int,ledger_credit_total:int,ledger_debit_total:int,ledger_net:int,difference:int,status:string}
     */
    public function reconcileUser(User|int $user): array
    {
        $user = $user instanceof User ? $user : User::query()->findOrFail($user);
        $totals = $this->ledgerTotals($user);
        $difference = (int) $user->balance - $totals['ledger_net'];

        return [
            'user_id' => (int) $user->id,
            'cached_balance' => (int) $user->balance,
            'ledger_credit_total' => $totals['credit'],
            'ledger_debit_total' => $totals['debit'],
            'ledger_net' => $totals['ledger_net'],
            'difference' => $difference,
            'status' => $this->statusFor($user, $totals, $difference),
        ];
    }

    /**
     * @return list<array{user_id:int,cached_balance:int,ledger_credit_total:int,ledger_debit_total:int,ledger_net:int,difference:int,status:string}>
     */
    public function report(): array
    {
        return User::query()
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => $this->reconcileUser($user))
            ->all();
    }

    /**
     * @return array{created:int,skipped:int,review:int}
     */
    public function createOpeningBalances(): array
    {
        $summary = ['created' => 0, 'skipped' => 0, 'review' => 0];

        User::query()->orderBy('id')->pluck('id')->each(function (int $userId) use (&$summary): void {
            $result = DB::transaction(function () use ($userId): string {
                $user = User::query()->lockForUpdate()->findOrFail($userId);
                $reconciliation = $this->reconcileUser($user);

                if ($reconciliation['status'] !== 'OPENING_BALANCE_CANDIDATE') {
                    return $reconciliation['status'] === 'MISMATCH_REQUIRES_REVIEW' ? 'review' : 'skipped';
                }

                (new LedgerEntry)->forceFill([
                    'user_id' => $user->id,
                    'type' => 'opening_balance',
                    'direction' => 'credit',
                    'amount' => (int) $user->balance,
                    'reference_type' => User::class,
                    'reference_id' => $user->id,
                    'description' => 'Legacy opening balance migration',
                    'created_by' => null,
                ])->save();

                return 'created';
            });

            $summary[$result]++;
        });

        return $summary;
    }

    /**
     * Repair only an existing ledger-backed mismatch. Candidate opening
     * balances and negative ledger results are intentionally not repaired.
     *
     * @return array{repaired:int,skipped:int,review:int}
     */
    public function repairCache(): array
    {
        $summary = ['repaired' => 0, 'skipped' => 0, 'review' => 0];

        User::query()->orderBy('id')->pluck('id')->each(function (int $userId) use (&$summary): void {
            $result = DB::transaction(function () use ($userId): string {
                $user = User::query()->lockForUpdate()->findOrFail($userId);
                $reconciliation = $this->reconcileUser($user);

                if ($reconciliation['difference'] === 0) {
                    return 'skipped';
                }

                if ($reconciliation['ledger_net'] < 0 || $reconciliation['status'] === 'OPENING_BALANCE_CANDIDATE') {
                    return 'review';
                }

                $user->forceFill(['balance' => $reconciliation['ledger_net']])->save();

                return 'repaired';
            });

            $summary[$result]++;
        });

        return $summary;
    }

    /**
     * @return array{credit:int,debit:int,ledger_net:int,count:int}
     */
    private function ledgerTotals(User $user): array
    {
        $entries = LedgerEntry::query()->where('user_id', $user->id);
        $credit = (int) (clone $entries)->where('direction', 'credit')->sum('amount');
        $debit = (int) (clone $entries)->where('direction', 'debit')->sum('amount');

        return [
            'credit' => $credit,
            'debit' => $debit,
            'ledger_net' => $credit - $debit,
            'count' => (clone $entries)->count(),
        ];
    }

    private function statusFor(User $user, array $totals, int $difference): string
    {
        if ($difference === 0 && $totals['ledger_net'] >= 0) {
            return 'MATCH';
        }

        if (
            (int) $user->balance > 0
            && $totals['count'] === 0
            && $user->wasteDeposits()->count() === 0
            && $user->withdrawals()->count() === 0
        ) {
            return 'OPENING_BALANCE_CANDIDATE';
        }

        return 'MISMATCH_REQUIRES_REVIEW';
    }
}
