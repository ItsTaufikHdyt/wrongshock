<?php

namespace App\Policies;

use App\Models\District;
use App\Models\User;

class DistrictPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->isPlatformAdmin() ? true : null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function view(User $actor, District $district): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function update(User $actor, District $district): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function delete(User $actor, District $district): bool
    {
        return $actor->isPlatformAdmin();
    }
}
