<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Withdrawal;

class WithdrawalPolicy
{
    public function before(User $actor): ?bool
    {
        return null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformAdmin() || $actor->isBankAdmin();
    }

    public function view(User $actor, Withdrawal $withdrawal): bool
    {
        return $actor->isPlatformAdmin()
            || ($actor->isBankAdmin() && $actor->wasteBanksAsStaff()->whereKey($withdrawal->waste_bank_id)->exists())
            || $actor->is($withdrawal->user);
    }

    public function create(User $actor): bool
    {
        return $actor->isBankAdmin()
            && $actor->wasteBanksAsStaff()->where('status', true)->exists();
    }

    public function update(User $actor, Withdrawal $withdrawal): bool
    {
        return false;
    }

    public function delete(User $actor, Withdrawal $withdrawal): bool
    {
        return false;
    }
}
