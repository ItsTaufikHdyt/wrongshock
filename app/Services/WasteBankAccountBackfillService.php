<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\WasteBankAccount;
use App\Models\WasteBankMember;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use LogicException;

class WasteBankAccountBackfillService
{
    public function __construct(
        private LedgerBankAttributionService $attribution,
        private DatabaseManager $database,
    ) {}

    /**
     * @return array{expected:int,created:int,unchanged:int,zero_balance:int}
     */
    public function backfill(): array
    {
        $results = $this->attribution->analyzeAll();
        if (! $this->attribution->isSafe($results)) {
            throw new LogicException('Account backfill aborted: ledger bank attribution is incomplete.');
        }

        $expected = $this->expectedAccounts($results);
        $this->assertNonNegative($expected);
        $this->assertExistingAccountsMatch($expected);

        $created = 0;
        $unchanged = 0;
        $zeroBalance = 0;

        $this->database->transaction(function () use ($expected, &$created, &$unchanged, &$zeroBalance): void {
            foreach ($expected as $key => $balance) {
                [$userId, $bankId] = array_map('intval', explode(':', $key));
                $account = WasteBankAccount::query()
                    ->where('user_id', $userId)
                    ->where('waste_bank_id', $bankId)
                    ->first();

                if ($account) {
                    $unchanged++;
                } else {
                    $account = new WasteBankAccount;
                    $account->forceFill([
                        'user_id' => $userId,
                        'waste_bank_id' => $bankId,
                        'balance' => $balance,
                    ])->save();
                    $created++;
                }

                if ($balance === 0) {
                    $zeroBalance++;
                }
            }
        });

        return [
            'expected' => count($expected),
            'created' => $created,
            'unchanged' => $unchanged,
            'zero_balance' => $zeroBalance,
        ];
    }

    /** @return array<string, int> */
    public function expectedAccounts(Collection $results): array
    {
        $accounts = [];

        WasteBankMember::query()
            ->select(['user_id', 'waste_bank_id'])
            ->distinct()
            ->get()
            ->each(function (WasteBankMember $membership) use (&$accounts): void {
                $accounts[$this->key($membership->user_id, $membership->waste_bank_id)] ??= 0;
            });

        foreach ($results as $result) {
            $key = $this->key($result['user_id'] ?? null, $result['waste_bank_id']);
            if (! isset($accounts[$key])) {
                $accounts[$key] = 0;
            }
        }

        foreach ($results as $result) {
            $entry = LedgerEntry::query()->findOrFail($result['ledger_id']);
            $key = $this->key($entry->user_id, $result['waste_bank_id']);
            $accounts[$key] += $entry->direction === 'credit'
                ? (int) $entry->amount
                : -(int) $entry->amount;
        }

        ksort($accounts);

        return $accounts;
    }

    /** @param array<string, int> $expected */
    private function assertNonNegative(array $expected): void
    {
        foreach ($expected as $key => $balance) {
            if ($balance < 0) {
                throw new LogicException("Account backfill aborted: negative calculated balance for {$key}.");
            }
        }
    }

    /** @param array<string, int> $expected */
    private function assertExistingAccountsMatch(array $expected): void
    {
        $existing = WasteBankAccount::query()
            ->get(['user_id', 'waste_bank_id', 'balance'])
            ->keyBy(fn (WasteBankAccount $account): string => $this->key($account->user_id, $account->waste_bank_id));

        foreach ($existing as $key => $account) {
            if (! array_key_exists($key, $expected) || (int) $account->balance !== $expected[$key]) {
                throw new LogicException("ACCOUNT MISMATCH for {$key}: existing={$account->balance}, expected=".($expected[$key] ?? 'missing').'.');
            }
        }
    }

    private function key(?int $userId, ?int $bankId): string
    {
        return (int) $userId.':'.(int) $bankId;
    }
}
