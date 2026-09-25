<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

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
     * @return list<array{user_id:int,waste_bank_id:int,cached_account_balance:int,ledger_credit_total:int,ledger_debit_total:int,ledger_net:int,difference:int,status:string}>
     */
    public function reconcileUserByBank(User|int $user): array
    {
        $user = $user instanceof User ? $user : User::query()->findOrFail($user);
        $accounts = $user->wasteBankAccounts()->get()->keyBy('waste_bank_id');
        $bankIds = LedgerEntry::query()
            ->where('user_id', $user->id)
            ->whereNotNull('waste_bank_id')
            ->distinct()
            ->pluck('waste_bank_id')
            ->merge($accounts->keys())
            ->unique()
            ->sort()
            ->values();

        return $bankIds->map(function (int|string $bankId) use ($user, $accounts): array {
            $account = $accounts->get($bankId);
            $entries = LedgerEntry::query()
                ->where('user_id', $user->id)
                ->where('waste_bank_id', $bankId);
            $credit = (int) (clone $entries)->where('direction', 'credit')->sum('amount');
            $debit = (int) (clone $entries)->where('direction', 'debit')->sum('amount');
            $net = $credit - $debit;
            $cached = $account ? (int) $account->balance : 0;
            $difference = $cached - $net;

            return [
                'user_id' => (int) $user->id,
                'waste_bank_id' => (int) $bankId,
                'cached_account_balance' => $cached,
                'ledger_credit_total' => $credit,
                'ledger_debit_total' => $debit,
                'ledger_net' => $net,
                'difference' => $difference,
                'status' => $account && $difference === 0 && $net >= 0 ? 'MATCH' : 'MISMATCH_REQUIRES_REVIEW',
            ];
        })->all();
    }

    /** @return list<array<string, int|string>> */
    public function reportByBank(): array
    {
        return User::query()
            ->orderBy('id')
            ->get()
            ->flatMap(fn (User $user): array => $this->reconcileUserByBank($user))
            ->values()
            ->all();
    }

    /**
     * @return array{user_id:int,legacy_balance:int,account_balance_total:int,global_ledger_net:int,account_difference:int,legacy_difference:int,account_status:string,legacy_status:string}
     */
    public function reconcileAggregate(User|int $user): array
    {
        $user = $user instanceof User ? $user : User::query()->findOrFail($user);
        $accountTotal = (int) $user->wasteBankAccounts()->sum('balance');
        $ledgerNet = $this->ledgerTotals($user)['ledger_net'];
        $legacyDifference = (int) $user->balance - $ledgerNet;
        $accountDifference = $accountTotal - $ledgerNet;

        return [
            'user_id' => (int) $user->id,
            'legacy_balance' => (int) $user->balance,
            'account_balance_total' => $accountTotal,
            'global_ledger_net' => $ledgerNet,
            'account_difference' => $accountDifference,
            'legacy_difference' => $legacyDifference,
            'account_status' => $accountDifference === 0 ? 'MATCH' : 'MISMATCH_REQUIRES_REVIEW',
            'legacy_status' => $legacyDifference === 0 ? 'MATCH' : 'MISMATCH_REQUIRES_REVIEW',
        ];
    }

    /** @return list<array<string, int|string>> */
    public function reportAggregate(): array
    {
        return User::query()
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => $this->reconcileAggregate($user))
            ->all();
    }

    /**
     * @return array{created:int,skipped:int,review:int}
     */
    public function createOpeningBalances(): array
    {
        throw new LogicException('Global opening balance creation is disabled; an explicit User + WasteBank source is required.');
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

        $attribution = app(LedgerBankAttributionService::class)->analyzeAll();
        if (! app(LedgerBankAttributionService::class)->isSafe($attribution)) {
            throw new LogicException('Cache repair aborted: ledger bank attribution is incomplete or inconsistent.');
        }

        User::query()->orderBy('id')->pluck('id')->each(function (int $userId) use (&$summary): void {
            $result = DB::transaction(function () use ($userId): string {
                $user = User::query()->lockForUpdate()->findOrFail($userId);
                $accounts = $user->wasteBankAccounts()->lockForUpdate()->get();
                $ledgerByBank = LedgerEntry::query()
                    ->where('user_id', $user->id)
                    ->selectRaw('waste_bank_id, SUM(CASE WHEN direction = ? THEN amount ELSE 0 END) AS credits, SUM(CASE WHEN direction = ? THEN amount ELSE 0 END) AS debits', ['credit', 'debit'])
                    ->groupBy('waste_bank_id')
                    ->get()
                    ->keyBy('waste_bank_id');

                $changed = false;
                foreach ($accounts as $account) {
                    $totals = $ledgerByBank->get($account->waste_bank_id);
                    $expected = $totals ? (int) $totals->credits - (int) $totals->debits : 0;
                    if ($expected < 0) {
                        throw new LogicException("Cache repair aborted: negative ledger net for user {$user->id}, bank {$account->waste_bank_id}.");
                    }
                    if ((int) $account->balance !== $expected) {
                        $account->forceFill(['balance' => $expected])->save();
                        $changed = true;
                    }
                    $ledgerByBank->forget($account->waste_bank_id);
                }

                if ($ledgerByBank->isNotEmpty()) {
                    throw new LogicException("Cache repair aborted: missing account for user {$user->id}.");
                }

                $aggregate = (int) $user->wasteBankAccounts()->sum('balance');
                if ((int) $user->balance !== $aggregate) {
                    User::query()->whereKey($user->id)->update(['balance' => $aggregate]);
                    $user->setAttribute('balance', $aggregate);
                    $changed = true;
                }

                return $changed ? 'repaired' : 'skipped';
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
