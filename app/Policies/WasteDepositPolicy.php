<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WasteDeposit;

class WasteDepositPolicy
{
    public function before(User $actor): ?bool
    {
        return null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformAdmin() || $actor->isBankAdmin();
    }

    public function view(User $actor, WasteDeposit $deposit): bool
    {
        return $actor->isPlatformAdmin()
            || ($actor->isBankAdmin() && $actor->wasteBanks()->whereKey($deposit->waste_bank_id)->exists())
            || $actor->is($deposit->user);
    }

    public function create(User $actor): bool
    {
        return $actor->isBankAdmin()
            && $actor->wasteBanks()->where('status', true)->exists();
    }

    public function update(User $actor, WasteDeposit $deposit): bool
    {
        return $actor->isBankAdmin()
            && $actor->wasteBanks()->whereKey($deposit->waste_bank_id)->where('status', true)->exists()
            && $deposit->status === 'draft';
    }

    public function delete(User $actor, WasteDeposit $deposit): bool
    {
        return $actor->isBankAdmin()
            && $actor->wasteBanks()->whereKey($deposit->waste_bank_id)->where('status', true)->exists()
            && $deposit->status === 'draft';
    }
}
