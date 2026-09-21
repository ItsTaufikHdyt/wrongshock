<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->hasRole('admin') ? true : null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->hasRole('user');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->is($user);
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->is($user);
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
