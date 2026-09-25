<?php

namespace App\Services;

use App\Models\User;
use App\Models\WasteBank;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MemberResolutionService
{
    public function __construct(
        private WasteBankContext $bankContext,
        private CitizenIdentityService $identity,
    ) {}

    /** @return Collection<int, User> */
    public function search(string $term, ?User $actor = null): Collection
    {
        $bank = $this->bankContext->current($actor?->id);
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        return $this->scopeEligible(User::query(), $bank)
            ->where(function (Builder $query) use ($term): void {
                $query->where('number', 'like', $term.'%')
                    ->orWhere('name', 'like', '%'.$term.'%');
            })
            ->orderByRaw('CASE WHEN number = ? THEN 0 ELSE 1 END', [$term])
            ->orderBy('name')
            ->orderBy('number')
            ->limit(50)
            ->get();
    }

    public function resolveQr(string $payload, ?User $actor = null): MemberResolutionResult
    {
        $bank = $this->bankForResolver($actor);
        if ($bank instanceof MemberResolutionResult) {
            return $bank;
        }

        $token = $this->identity->parseQrPayload($payload);
        if ($token === null) {
            return new MemberResolutionResult(MemberResolutionResult::INVALID_QR);
        }

        $user = User::query()->where('qr_token', $token)->first();
        if (! $user) {
            return new MemberResolutionResult(MemberResolutionResult::MEMBER_NOT_FOUND);
        }

        return $this->evaluate($user, $bank);
    }

    public function evaluate(User $user, WasteBank $bank): MemberResolutionResult
    {
        if (! $bank->status) {
            return new MemberResolutionResult(MemberResolutionResult::BANK_INACTIVE, $user);
        }

        if (! $user->hasRole('user') || $user->hasAnyRole(['admin', 'super_admin'])) {
            return new MemberResolutionResult(MemberResolutionResult::NOT_CITIZEN, $user);
        }

        if ((int) $user->status !== 1) {
            return new MemberResolutionResult(MemberResolutionResult::USER_INACTIVE, $user);
        }

        $membership = $user->bankMemberships()
            ->where('waste_bank_id', $bank->id)
            ->first();

        if (! $membership) {
            return new MemberResolutionResult(MemberResolutionResult::MEMBERSHIP_NOT_FOUND, $user);
        }

        if ($membership->status !== 'active') {
            return new MemberResolutionResult(MemberResolutionResult::MEMBERSHIP_INACTIVE, $user, $membership);
        }

        return new MemberResolutionResult(MemberResolutionResult::ELIGIBLE, $user, $membership);
    }

    public function scopeEligible(Builder $query, WasteBank $bank): Builder
    {
        return $query
            ->where('status', 1)
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', 'user'))
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('name', ['admin', 'super_admin']))
            ->whereHas('bankMemberships', fn (Builder $membership) => $membership
                ->where('waste_bank_id', $bank->id)
                ->where('status', 'active'));
    }

    private function bankForResolver(?User $actor): WasteBank|MemberResolutionResult
    {
        try {
            return $this->bankContext->current($actor?->id);
        } catch (AuthorizationException) {
            $actor ??= auth()->user();

            if ($actor?->status === 1 && $actor->isBankAdmin()) {
                $assignedBanks = $actor->wasteBanksAsStaff()->get();
                if ($assignedBanks->count() === 1 && ! $assignedBanks->sole()->status) {
                    return new MemberResolutionResult(MemberResolutionResult::BANK_INACTIVE);
                }
            }

            return new MemberResolutionResult(MemberResolutionResult::UNAUTHORIZED);
        }
    }
}
