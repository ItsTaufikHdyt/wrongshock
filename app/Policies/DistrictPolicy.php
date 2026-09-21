<?php

namespace App\Policies;

use App\Models\District;
use App\Models\User;

class DistrictPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->hasRole('admin') ? true : false;
    }
}
