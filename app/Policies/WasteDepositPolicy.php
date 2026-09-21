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
        return $actor->hasRole('admin');
    }

    public function view(User $actor, WasteDeposit $deposit): bool
    {
        return $actor->hasRole('admin') || $actor->is($deposit->user);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole('admin');
    }

    public function update(User $actor, WasteDeposit $deposit): bool
    {
        return $actor->hasRole('admin') && $deposit->status === 'draft';
    }

    public function delete(User $actor, WasteDeposit $deposit): bool
    {
        return $actor->hasRole('admin') && $deposit->status === 'draft';
    }
}
