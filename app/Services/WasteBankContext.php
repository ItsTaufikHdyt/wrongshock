<?php

namespace App\Services;

use App\Models\User;
use App\Models\WasteBank;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class WasteBankContext
{
    public function current(?int $actorId = null): WasteBank
    {
        return $this->forOperationalAdmin($actorId);
    }

    public function forOperationalAdmin(?int $actorId = null): WasteBank
    {
        $actorId ??= Auth::id();
        $actor = $actorId !== null ? User::query()->find($actorId) : null;

        if (! $actor || (int) $actor->status !== 1 || ! $actor->isBankAdmin()) {
            throw new AuthorizationException('An active admin with a waste bank assignment is required.');
        }

        $banks = $actor->wasteBanks()->where('status', true)->get();

        if ($banks->count() !== 1) {
            throw new AuthorizationException('Exactly one active waste bank assignment is required.');
        }

        return $banks->sole();
    }

    public function isPlatformAdmin(?int $actorId = null): bool
    {
        $actorId ??= Auth::id();
        $actor = $actorId !== null ? User::query()->find($actorId) : null;

        return (bool) $actor && (int) $actor->status === 1 && $actor->isPlatformAdmin();
    }

    public function assertCanOperate(WasteBank|int $bank, ?int $actorId = null): WasteBank
    {
        $current = $this->current($actorId);
        $bankId = $bank instanceof WasteBank ? $bank->getKey() : $bank;

        if ((int) $current->getKey() !== (int) $bankId) {
            throw new AuthorizationException('The operator is not assigned to this waste bank.');
        }

        return $current;
    }
}
