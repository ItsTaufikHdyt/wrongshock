<?php

namespace App\Services;

use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteBankMember;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class BankMembershipService
{
    public function __construct(private DatabaseManager $database) {}

    public function addMember(User $actor, User $user, ?WasteBank $bank = null): WasteBankMember
    {
        if ((int) $actor->status !== 1 || ! $actor->isBankAdmin() && ! $actor->isPlatformAdmin()) {
            throw new AuthorizationException('An active platform or bank admin is required.');
        }

        $bank = $this->resolveBank($actor, $bank);

        if (! $user->hasRole('user') || $user->hasAnyRole(['admin', 'super_admin'])) {
            throw ValidationException::withMessages([
                'user_id' => 'Hanya akun User/Citizen yang dapat menjadi anggota bank sampah.',
            ]);
        }

        if ((int) $user->status !== 1) {
            throw ValidationException::withMessages([
                'user_id' => 'Akun anggota harus aktif.',
            ]);
        }

        return $this->database->transaction(function () use ($bank, $user): WasteBankMember {
            app(CitizenIdentityService::class)->ensureQrToken($user);

            return WasteBankMember::query()->firstOrCreate(
                ['waste_bank_id' => $bank->id, 'user_id' => $user->id],
                ['joined_at' => now(), 'status' => 'active'],
            );
        });
    }

    public function deactivateMembership(User $actor, User $user, ?WasteBank $bank = null): WasteBankMember
    {
        $membership = $this->membershipFor($actor, $user, $bank);
        $membership->update(['status' => 'inactive']);

        return $membership->refresh();
    }

    public function reactivateMembership(User $actor, User $user, ?WasteBank $bank = null): WasteBankMember
    {
        if (! $user->hasRole('user') || $user->hasAnyRole(['admin', 'super_admin']) || (int) $user->status !== 1) {
            throw ValidationException::withMessages([
                'user_id' => 'Hanya akun User/Citizen aktif yang dapat menjadi anggota bank sampah.',
            ]);
        }

        $membership = $this->membershipFor($actor, $user, $bank);
        $membership->update(['status' => 'active']);

        return $membership->refresh();
    }

    public function createMember(User $actor, array $attributes): User
    {
        if ((int) $actor->status !== 1 || ! $actor->isBankAdmin() && ! $actor->isPlatformAdmin()) {
            throw new AuthorizationException('An active platform or bank admin is required.');
        }

        $bank = $this->resolveBank($actor, ! empty($attributes['waste_bank_id'])
            ? WasteBank::query()->find($attributes['waste_bank_id'])
            : null);

        return app(CitizenIdentityService::class)->createCitizen([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => $attributes['password'],
            'district_id' => $attributes['district_id'],
            'sub_district_id' => $attributes['sub_district_id'],
            'address' => $attributes['address'],
            'image' => $attributes['image'] ?? null,
            'status' => 1,
            'balance' => 0,
        ], function (User $user) use ($bank): void {
            WasteBankMember::query()->create([
                'waste_bank_id' => $bank->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'status' => 'active',
            ]);
        });
    }

    public function registerCitizen(array $attributes, WasteBank|int $bank): User
    {
        $bank = $bank instanceof WasteBank
            ? $bank->fresh()
            : WasteBank::query()->find($bank);

        if (! $bank?->exists || ! $bank->status) {
            throw ValidationException::withMessages([
                'waste_bank_id' => 'Bank Sampah yang dipilih tidak tersedia atau tidak aktif.',
            ]);
        }

        return app(CitizenIdentityService::class)->createCitizen([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'address' => $attributes['address'],
            'district_id' => $attributes['district_id'],
            'sub_district_id' => $attributes['sub_district_id'],
            'password' => $attributes['password'],
            'status' => 0,
            'balance' => 0,
        ], function (User $user) use ($bank): void {
            WasteBankMember::query()->create([
                'waste_bank_id' => $bank->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'status' => 'active',
            ]);
        });
    }

    private function resolveBank(User $actor, ?WasteBank $bank): WasteBank
    {
        if ($actor->isBankAdmin()) {
            return app(WasteBankContext::class)->current($actor->id);
        }

        if ($actor->isPlatformAdmin() && $bank?->status) {
            return $bank;
        }

        throw new AuthorizationException('Een actieve bankcontext is vereist.');
    }

    private function membershipFor(User $actor, User $user, ?WasteBank $bank): WasteBankMember
    {
        if ((int) $actor->status !== 1 || ! $actor->isBankAdmin() && ! $actor->isPlatformAdmin()) {
            throw new AuthorizationException('An active platform or bank admin is required.');
        }

        $bank = $actor->isBankAdmin()
            ? app(WasteBankContext::class)->current($actor->id)
            : $bank;

        if (! $bank?->exists) {
            throw new AuthorizationException('A Waste Bank is required.');
        }

        if (! $user->hasRole('user') || $user->hasAnyRole(['admin', 'super_admin'])) {
            throw ValidationException::withMessages([
                'user_id' => 'Hanya akun User/Citizen yang dapat memiliki membership.',
            ]);
        }

        $membership = WasteBankMember::query()
            ->where('waste_bank_id', $bank->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            throw ValidationException::withMessages([
                'waste_bank_id' => 'Membership untuk bank ini tidak ditemukan.',
            ]);
        }

        return $membership;
    }
}
