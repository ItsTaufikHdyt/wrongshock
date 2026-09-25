<?php

namespace App\Services;

use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteBankStaff;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserRoleService
{
    public function __construct(private DatabaseManager $database) {}

    public function create(User $actor, array $attributes, string $role, ?WasteBank $bank = null): User
    {
        $this->assertSuperAdmin($actor);
        $this->validateRole($role);

        return $this->database->transaction(function () use ($attributes, $role, $bank): User {
            if ($role === 'admin' && ! $bank) {
                $this->fail('waste_bank_id', 'Bank Sampah wajib dipilih untuk Admin Bank Sampah.');
            }

            if ($role === 'user') {
                return app(CitizenIdentityService::class)->createCitizen($attributes);
            }

            $user = User::query()->create($attributes);
            $user->syncRoles([Role::findOrCreate($role, 'web')]);

            if ($role === 'admin') {
                $this->syncStaffAssignment($user, $bank);
            }

            return $user->refresh();
        });
    }

    public function changeRole(User $actor, User $target, string $role, ?WasteBank $bank = null): User
    {
        $this->assertSuperAdmin($actor);
        $this->validateRole($role);

        return $this->database->transaction(function () use ($target, $role, $bank): User {
            $this->protectLastSuperAdmin($target, $role, null);

            if ($role === 'admin' && ! $bank) {
                $this->fail('waste_bank_id', 'Bank Sampah wajib dipilih untuk Admin Bank Sampah.');
            }

            if ($role === 'admin') {
                $activeAssignments = $target->wasteBanksAsStaff()->where('status', true)->get();
                if ($activeAssignments->isNotEmpty() && ! $activeAssignments->contains('id', $bank->id)) {
                    $this->fail('waste_bank_id', 'Admin ini masih ditugaskan pada bank lain. Pindahkan assignment terlebih dahulu.');
                }
            }

            $target->syncRoles([Role::findOrCreate($role, 'web')]);

            if ($role === 'admin') {
                $this->syncStaffAssignment($target->refresh(), $bank);
            } else {
                $target->wasteBanksAsStaff()->detach();
            }

            if ($role === 'user') {
                app(CitizenIdentityService::class)->ensureQrToken($target->refresh());
            }

            return $target->refresh();
        });
    }

    public function update(User $actor, User $target, array $attributes): User
    {
        $this->assertSuperAdmin($actor);

        return $this->database->transaction(function () use ($target, $attributes): User {
            $role = $attributes['role'] ?? $target->getRoleNames()->first();
            $this->validateRole($role);
            $this->protectLastSuperAdmin($target, $role, $attributes['status'] ?? null);

            $target->fill(array_diff_key($attributes, array_flip(['role', 'number', 'waste_bank_id', 'password_confirmation'])));
            $target->save();

            if ($role !== $target->getRoleNames()->first()) {
                $target->syncRoles([Role::findOrCreate($role, 'web')]);
            }

            if ($role === 'admin') {
                $bankId = $attributes['waste_bank_id'] ?? null;
                $bank = $bankId ? WasteBank::query()->find($bankId) : null;
                if (! $bank) {
                    $this->fail('waste_bank_id', 'Bank Sampah wajib dipilih untuk Admin Bank Sampah.');
                }
                $this->syncStaffAssignment($target->refresh(), $bank);
            } elseif ($role !== 'super_admin') {
                $target->wasteBanksAsStaff()->detach();
            } else {
                $target->wasteBanksAsStaff()->detach();
            }

            if ($role === 'user') {
                app(CitizenIdentityService::class)->ensureQrToken($target->refresh());
            }

            return $target->refresh();
        });
    }

    private function syncStaffAssignment(User $user, WasteBank $bank): void
    {
        if (! $bank->exists || ! $bank->status) {
            $this->fail('waste_bank_id', 'Bank Sampah harus aktif.');
        }

        $assignments = $user->wasteBanksAsStaff()->where('status', true)->get();
        if ($assignments->isNotEmpty() && ! $assignments->contains('id', $bank->id)) {
            $this->fail('waste_bank_id', 'Admin ini masih ditugaskan pada bank lain.');
        }

        if (! $assignments->contains('id', $bank->id)) {
            WasteBankStaff::query()->create([
                'waste_bank_id' => $bank->id,
                'user_id' => $user->id,
            ]);
        }
    }

    private function protectLastSuperAdmin(User $target, ?string $role, ?int $status): void
    {
        if (! $target->hasRole('super_admin') || ($role === 'super_admin' && $status !== 0)) {
            return;
        }

        $remaining = User::query()
            ->where('status', 1)
            ->whereHas('roles', fn ($query) => $query->where('name', 'super_admin'))
            ->where('id', '!=', $target->id)
            ->exists();

        if (! $remaining) {
            throw ValidationException::withMessages([
                'role' => 'Minimal harus ada satu Super Admin aktif.',
            ]);
        }
    }

    private function assertSuperAdmin(User $actor): void
    {
        if ((int) $actor->status !== 1 || ! $actor->isPlatformAdmin()) {
            throw new AuthorizationException('An active super admin is required.');
        }
    }

    private function validateRole(string $role): void
    {
        if (! in_array($role, ['super_admin', 'admin', 'user'], true)) {
            $this->fail('role', 'Role tidak valid.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
