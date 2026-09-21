<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WasteItem;

class WasteItemPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->hasRole('admin') ? true : false;
    }
}
