<?php

namespace App\Policies;

use App\Models\LedgerEntry;
use App\Models\User;

class LedgerEntryPolicy
{
    public function before(User $actor): ?bool
    {
        return null;
    }

    public function viewAny(User $actor): bool
    {
        return false;
    }

    public function view(User $actor, LedgerEntry $entry): bool
    {
        return $actor->hasRole('admin') || $actor->is($entry->user);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, LedgerEntry $entry): bool
    {
        return false;
    }

    public function delete(User $actor, LedgerEntry $entry): bool
    {
        return false;
    }
}
