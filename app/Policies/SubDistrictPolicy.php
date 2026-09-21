<?php

namespace App\Policies;

use App\Models\SubDistrict;
use App\Models\User;

class SubDistrictPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->hasRole('admin') ? true : false;
    }
}
