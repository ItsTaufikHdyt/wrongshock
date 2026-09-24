<?php

namespace App\Services;

use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteBankMember;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

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

        return $this->database->transaction(function () use ($bank, $attributes): User {
            $user = User::query()->create([
                'name' => $attributes['name'],
                'number' => $attributes['number'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'district_id' => $attributes['district_id'],
                'sub_district_id' => $attributes['sub_district_id'],
                'address' => $attributes['address'],
                'image' => $attributes['image'] ?? null,
                'status' => 1,
            ]);

            $user->assignRole(Role::findOrCreate('user', 'web'));
            WasteBankMember::query()->create([
                'waste_bank_id' => $bank->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'status' => 'active',
            ]);

            return $user->refresh();
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
