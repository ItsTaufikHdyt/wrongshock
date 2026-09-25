<?php

namespace App\Services;

use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteBankAccount;
use Illuminate\Database\QueryException;

class WasteBankAccountService
{
    /**
     * Callers must lock the user first. That lock serializes account
     * provisioning and aggregate cache updates for the same citizen.
     */
    public function lockOrCreate(User $user, WasteBank|int $wasteBank): WasteBankAccount
    {
        $bankId = $wasteBank instanceof WasteBank ? $wasteBank->id : $wasteBank;
        $account = WasteBankAccount::query()
            ->where('user_id', $user->id)
            ->where('waste_bank_id', $bankId)
            ->lockForUpdate()
            ->first();

        if (! $account) {
            try {
                $newAccount = new WasteBankAccount;
                $newAccount->forceFill([
                    'user_id' => $user->id,
                    'waste_bank_id' => $bankId,
                    'balance' => 0,
                ])->save();
            } catch (QueryException $exception) {
                $account = WasteBankAccount::query()
                    ->where('user_id', $user->id)
                    ->where('waste_bank_id', $bankId)
                    ->lockForUpdate()
                    ->first();

                if (! $account) {
                    throw $exception;
                }
            }

            $account ??= WasteBankAccount::query()
                ->where('user_id', $user->id)
                ->where('waste_bank_id', $bankId)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $account;
    }

    public function lockExisting(User $user, WasteBank|int $wasteBank): WasteBankAccount
    {
        $bankId = $wasteBank instanceof WasteBank ? $wasteBank->id : $wasteBank;

        return WasteBankAccount::query()
            ->where('user_id', $user->id)
            ->where('waste_bank_id', $bankId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function syncAggregateBalance(User $user): int
    {
        $balance = (int) WasteBankAccount::query()
            ->where('user_id', $user->id)
            ->sum('balance');

        User::query()->whereKey($user->id)->update(['balance' => $balance]);
        $user->setAttribute('balance', $balance);

        return $balance;
    }
}
