<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WasteItem;

class WasteItemPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->isPlatformAdmin() ? true : null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function view(User $actor, WasteItem $item): bool
    {
        return $actor->isPlatformAdmin() || $actor->isBankAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function update(User $actor, WasteItem $item): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function delete(User $actor, WasteItem $item): bool
    {
        return $actor->isPlatformAdmin();
    }
}
