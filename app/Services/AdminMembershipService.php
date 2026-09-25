<?php

namespace App\Services;

use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteBankStaff;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AdminMembershipService
{
    public function __construct(private DatabaseManager $database) {}

    public function assignBankAdmin(User $actor, User $user, WasteBank $bank): WasteBankStaff
    {
        $this->assertSuperAdmin($actor);

        return $this->database->transaction(function () use ($user, $bank): WasteBankStaff {
            if (! $user->hasRole('admin') || $user->hasRole('super_admin')) {
                $this->fail('user_id', 'Pilih akun Admin Bank Sampah yang valid.');
            }

            if ((int) $user->status !== 1) {
                $this->fail('user_id', 'Akun admin harus aktif.');
            }

            if (! $bank->status) {
                $this->fail('waste_bank_id', 'Bank Sampah harus aktif.');
            }

            $activeBankIds = $user->wasteBanksAsStaff()->where('status', true)->pluck('waste_banks.id');
            if ($activeBankIds->isNotEmpty() && ! $activeBankIds->contains($bank->id)) {
                $this->fail('user_id', 'Admin Bank Sampah hanya boleh memiliki satu bank aktif.');
            }

            if (WasteBankStaff::query()->where('waste_bank_id', $bank->id)->where('user_id', $user->id)->exists()) {
                $this->fail('user_id', 'Admin sudah terdaftar pada bank ini.');
            }

            return WasteBankStaff::query()->create([
                'waste_bank_id' => $bank->id,
                'user_id' => $user->id,
            ]);
        });
    }

    public function createBankAdmin(User $actor, WasteBank $bank, array $attributes): WasteBankStaff
    {
        $this->assertSuperAdmin($actor);

        return $this->database->transaction(function () use ($bank, $attributes): WasteBankStaff {
            if (! $bank->status) {
                $this->fail('waste_bank_id', 'Bank Sampah harus aktif.');
            }

            $user = User::query()->create([
                'name' => $attributes['name'],
                'number' => $attributes['number'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'district_id' => $attributes['district_id'],
                'sub_district_id' => $attributes['sub_district_id'],
                'address' => $attributes['address'] ?? null,
                'status' => (int) ($attributes['status'] ?? 1),
            ]);
            $user->assignRole(Role::findOrCreate('admin', 'web'));

            return WasteBankStaff::query()->create([
                'waste_bank_id' => $bank->id,
                'user_id' => $user->id,
            ]);
        });
    }

    public function removeBankAdmin(User $actor, WasteBankStaff $assignment): void
    {
        $this->assertSuperAdmin($actor);
        $assignment->delete();
    }

    public function promoteToSuperAdmin(User $actor, User $user): User
    {
        $this->assertSuperAdmin($actor);

        return $this->database->transaction(function () use ($user): User {
            $user->assignRole(Role::findOrCreate('super_admin', 'web'));
            $user->removeRole('admin');
            $user->removeRole('user');
            $user->wasteBanksAsStaff()->detach();

            return $user->refresh();
        });
    }

    public function promoteToBankAdmin(User $actor, User $user, WasteBank $bank): User
    {
        $this->assertSuperAdmin($actor);

        $this->assertNotLastSuperAdmin($user);

        return $this->database->transaction(function () use ($actor, $user, $bank): User {
            $user->removeRole('super_admin');
            $user->removeRole('user');
            $user->assignRole(Role::findOrCreate('admin', 'web'));
            $this->assignBankAdmin($actor, $user->refresh(), $bank);

            return $user->refresh();
        });
    }

    public function demoteToCitizen(User $actor, User $user): User
    {
        $this->assertSuperAdmin($actor);

        $this->assertNotLastSuperAdmin($user);

        return $this->database->transaction(function () use ($user): User {
            $user->removeRole('super_admin');
            $user->removeRole('admin');
            $user->assignRole(Role::findOrCreate('user', 'web'));
            app(CitizenIdentityService::class)->ensureQrToken($user->refresh());
            $user->wasteBanksAsStaff()->detach();

            return $user->refresh();
        });
    }

    private function assertSuperAdmin(User $actor): void
    {
        if ((int) $actor->status !== 1 || ! $actor->hasRole('super_admin')) {
            throw new AuthorizationException('An active super admin is required.');
        }
    }

    private function assertNotLastSuperAdmin(User $user): void
    {
        if (! $user->hasRole('super_admin')) {
            return;
        }

        $remaining = User::query()
            ->where('status', 1)
            ->whereHas('roles', fn ($query) => $query->where('name', 'super_admin'))
            ->where('id', '!=', $user->id)
            ->exists();

        if (! $remaining) {
            throw ValidationException::withMessages([
                'user_id' => 'Minimal harus ada satu Super Admin aktif.',
            ]);
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
