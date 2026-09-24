<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WasteBank;

class WasteBankPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function view(User $actor, WasteBank $bank): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function update(User $actor, WasteBank $bank): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function delete(User $actor, WasteBank $bank): bool
    {
        return false;
    }
}
