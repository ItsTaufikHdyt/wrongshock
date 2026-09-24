<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $actor): ?bool
    {
        return null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->hasRole('user') || $actor->isPlatformAdmin() || $actor->isBankAdmin();
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->isPlatformAdmin()
            || ($actor->isBankAdmin() && $actor->wasteBanks()->whereHas('deposits', fn ($query) => $query->where('user_id', $user->id))->exists())
            || $actor->is($user);
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->isPlatformAdmin() || $actor->is($user);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function delete(User $actor, User $user): bool
    {
        return false;
    }
}
