<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function request(int $userId, mixed $amount, ?int $actorId = null, ?string $note = null): Withdrawal
    {
        $actorId = $this->authorizeAdmin($actorId);
        $amount = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($userId, $amount, $actorId, $note): Withdrawal {
            $user = User::query()->lockForUpdate()->find($userId);
            if (! $user) {
                throw (new ModelNotFoundException)->setModel(User::class, [$userId]);
            }

            $this->assertActorExists($actorId);
            if ((int) $user->balance < $amount) {
                $this->fail('amount', 'The withdrawal amount exceeds the available cached balance.');
            }

            $withdrawal = new Withdrawal;
            $withdrawal->forceFill([
                'user_id' => $user->id,
                'amount' => $amount,
                'status' => 'pending',
                'withdrawal_date' => today(),
                'requested_date' => today(),
                'note' => $note !== null ? trim($note) : null,
            ])->save();

            return $withdrawal;
        });
    }

    public function approve(Withdrawal|int $withdrawal, ?int $actorId = null): Withdrawal
    {
        $actorId = $this->authorizeAdmin($actorId);
        return DB::transaction(function () use ($withdrawal, $actorId): Withdrawal {
            $lockedWithdrawal = $this->lockWithdrawal($withdrawal);
            if ($lockedWithdrawal->status !== 'pending') {
                throw new \LogicException('Only pending withdrawals can be approved.');
            }

            $this->assertActorExists($actorId);
            $user = User::query()->lockForUpdate()->find($lockedWithdrawal->user_id);
            if (! $user) {
                throw (new ModelNotFoundException)->setModel(User::class, [$lockedWithdrawal->user_id]);
            }

            $debitExists = LedgerEntry::query()
                ->where('user_id', $user->id)
                ->where('reference_type', Withdrawal::class)
                ->where('reference_id', $lockedWithdrawal->id)
                ->where('type', 'withdrawal_debit')
                ->lockForUpdate()
                ->exists();

            if ($debitExists) {
                throw new \LogicException('This withdrawal already has a debit.');
            }

            $amount = $this->normalizeAmount($lockedWithdrawal->amount);
            if ((int) $user->balance < $amount) {
                throw new \LogicException('The cached balance is insufficient for this withdrawal.');
            }

            (new LedgerEntry)->forceFill([
                'user_id' => $user->id,
                'type' => 'withdrawal_debit',
                'direction' => 'debit',
                'amount' => $amount,
                'reference_type' => Withdrawal::class,
                'reference_id' => $lockedWithdrawal->id,
                'description' => "Withdrawal approval #{$lockedWithdrawal->id}",
                'created_by' => $actorId,
            ])->save();

            $user->decrement('balance', $amount);
            $lockedWithdrawal->forceFill([
                'status' => 'approved',
                'processed_date' => today(),
            ])->save();

            return $lockedWithdrawal;
        });
    }

    public function reject(Withdrawal|int $withdrawal, string $reason, ?int $actorId = null): Withdrawal
    {
        $actorId = $this->authorizeAdmin($actorId);
        $reason = trim($reason);
        if ($reason === '') {
            $this->fail('reason', 'A rejection reason is required.');
        }

        return DB::transaction(function () use ($withdrawal, $reason, $actorId): Withdrawal {
            $lockedWithdrawal = $this->lockWithdrawal($withdrawal);
            if ($lockedWithdrawal->status !== 'pending') {
                throw new \LogicException('Only pending withdrawals can be rejected.');
            }

            $this->assertActorExists($actorId);
            $lockedWithdrawal->forceFill([
                'status' => 'rejected',
                'processed_date' => today(),
                'note' => $reason,
            ])->save();

            return $lockedWithdrawal;
        });
    }

    private function lockWithdrawal(Withdrawal|int $withdrawal): Withdrawal
    {
        $withdrawalId = $withdrawal instanceof Withdrawal ? $withdrawal->getKey() : $withdrawal;
        $lockedWithdrawal = Withdrawal::query()->lockForUpdate()->find($withdrawalId);

        if (! $lockedWithdrawal) {
            throw (new ModelNotFoundException)->setModel(Withdrawal::class, [$withdrawalId]);
        }

        return $lockedWithdrawal;
    }

    private function assertActorExists(?int $actorId): void
    {
        if ($actorId !== null && ! User::query()->find($actorId)) {
            throw (new ModelNotFoundException)->setModel(User::class, [$actorId]);
        }
    }

    private function normalizeAmount(mixed $amount): int
    {
        if (is_int($amount)) {
            $normalized = $amount;
        } elseif (is_string($amount) && preg_match('/^[1-9]\d*$/D', $amount)) {
            $normalized = (int) $amount;
        } else {
            $this->fail('amount', 'The withdrawal amount must be a positive integer Rupiah amount.');
        }

        if ($normalized < 1) {
            $this->fail('amount', 'The withdrawal amount must be greater than zero.');
        }

        return $normalized;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function authorizeAdmin(?int $actorId): int
    {
        $actorId ??= Auth::id();
        $actor = $actorId !== null ? User::query()->find($actorId) : null;

        if (! $actor || (int) $actor->status !== 1 || ! $actor->hasRole('admin')) {
            throw new AuthorizationException('An active admin actor is required for withdrawal financial actions.');
        }

        return $actor->id;
    }
}
