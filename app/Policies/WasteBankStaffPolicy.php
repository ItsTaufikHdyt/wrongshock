<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WasteBankStaff;

class WasteBankStaffPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function view(User $actor, WasteBankStaff $assignment): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function delete(User $actor, WasteBankStaff $assignment): bool
    {
        return $actor->isPlatformAdmin();
    }
}
