<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class CitizenIdentityService
{
    private const MAX_ATTEMPTS = 5;

    public function createCitizen(array $attributes, ?Closure $afterCreate = null): User
    {
        unset($attributes['number'], $attributes['qr_token']);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes, $afterCreate): User {
                    $user = User::query()->create([
                        ...$attributes,
                        'number' => $this->generateMemberNumber(
                            (int) $attributes['district_id'],
                            (int) $attributes['sub_district_id'],
                        ),
                    ]);

                    $user->assignRole(Role::findOrCreate('user', 'web'));
                    $this->ensureQrToken($user);

                    if ($afterCreate) {
                        $afterCreate($user);
                    }

                    return $user->refresh();
                });
            } catch (QueryException $exception) {
                if (! $this->isNumberCollision($exception) || $attempt === self::MAX_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Unable to generate a unique member number.');
    }

    public function generateMemberNumber(int $districtId, int $subDistrictId): string
    {
        return '001'
            .str_pad((string) $districtId, 2, '0', STR_PAD_LEFT)
            .str_pad((string) $subDistrictId, 2, '0', STR_PAD_LEFT)
            .now()->format('Y')
            .str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function ensureQrToken(User $user): string
    {
        if (filled($user->qr_token)) {
            return $user->qr_token;
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $user->forceFill(['qr_token' => Str::random(32)])->save();

                return (string) $user->qr_token;
            } catch (QueryException $exception) {
                if (! $this->isQrTokenCollision($exception) || $attempt === self::MAX_ATTEMPTS) {
                    throw $exception;
                }

                $user->refresh();
                if (filled($user->qr_token)) {
                    return $user->qr_token;
                }
            }
        }

        throw new RuntimeException('Unable to generate a unique QR token.');
    }

    public function rotateQrToken(User $user): string
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $token = DB::transaction(function () use ($user): string {
                    $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                    $lockedUser->forceFill(['qr_token' => Str::random(32)])->save();

                    return (string) $lockedUser->qr_token;
                });

                $user->refresh();

                return $token;
            } catch (QueryException $exception) {
                if (! $this->isQrTokenCollision($exception) || $attempt === self::MAX_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Unable to rotate the QR token.');
    }

    public function qrPayload(User $user): string
    {
        return 'WRG:M:'.$this->ensureQrToken($user);
    }

    public function parseQrPayload(string $payload): ?string
    {
        if (! str_starts_with($payload, 'WRG:M:')) {
            return null;
        }

        $token = substr($payload, 6);

        return preg_match('/^[A-Za-z0-9]{32}$/', $token) === 1 ? $token : null;
    }

    private function isNumberCollision(QueryException $exception): bool
    {
        return $this->isUniqueViolation($exception)
            && preg_match('/users(?:[._])number|users_number_unique/i', $exception->getMessage()) === 1;
    }

    private function isQrTokenCollision(QueryException $exception): bool
    {
        return $this->isUniqueViolation($exception)
            && preg_match('/users(?:[._])qr_token|users_qr_token_unique/i', $exception->getMessage()) === 1;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['19', '23000'], true);
    }
}
