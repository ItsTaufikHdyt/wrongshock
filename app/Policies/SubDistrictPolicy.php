<?php

namespace App\Policies;

use App\Models\SubDistrict;
use App\Models\User;

class SubDistrictPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->isPlatformAdmin() ? true : null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function view(User $actor, SubDistrict $subDistrict): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function update(User $actor, SubDistrict $subDistrict): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function delete(User $actor, SubDistrict $subDistrict): bool
    {
        return $actor->isPlatformAdmin();
    }
}
