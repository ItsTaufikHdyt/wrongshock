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
        return $actor->hasRole('admin');
    }

    public function view(User $actor, Withdrawal $withdrawal): bool
    {
        return $actor->hasRole('admin') || $actor->is($withdrawal->user);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole('admin');
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
