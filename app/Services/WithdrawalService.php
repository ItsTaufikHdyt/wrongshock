<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function request(int $userId, mixed $amount, ?int $actorId = null, ?string $note = null): Withdrawal
    {
        $actorId = $this->authorizeAdmin($actorId);
        $wasteBank = app(WasteBankContext::class)->current($actorId);
        $amount = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($userId, $amount, $actorId, $note, $wasteBank): Withdrawal {
            $user = User::query()->lockForUpdate()->find($userId);
            if (! $user) {
                throw (new ModelNotFoundException)->setModel(User::class, [$userId]);
            }

            $this->assertActorExists($actorId);
            $this->assertEligibleMember($user, $wasteBank->id);
            $account = app(WasteBankAccountService::class)->lockOrCreate($user, $wasteBank);
            if ((int) $account->balance < $amount) {
                $this->fail('amount', 'The withdrawal amount exceeds the available bank balance.');
            }

            $withdrawal = new Withdrawal;
            $withdrawal->forceFill([
                'user_id' => $user->id,
                'waste_bank_id' => $wasteBank->id,
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

            app(WasteBankContext::class)->assertCanOperate($lockedWithdrawal->waste_bank_id, $actorId);

            $this->assertActorExists($actorId);
            $user = User::query()->lockForUpdate()->find($lockedWithdrawal->user_id);
            if (! $user) {
                throw (new ModelNotFoundException)->setModel(User::class, [$lockedWithdrawal->user_id]);
            }

            $this->assertEligibleMember($user, $lockedWithdrawal->waste_bank_id);
            $account = app(WasteBankAccountService::class)->lockOrCreate($user, $lockedWithdrawal->waste_bank_id);

            $debit = LedgerEntry::query()
                ->where('user_id', $user->id)
                ->where('reference_type', Withdrawal::class)
                ->where('reference_id', $lockedWithdrawal->id)
                ->where('type', 'withdrawal_debit')
                ->lockForUpdate()
                ->first();

            if ($debit) {
                if ((int) $debit->waste_bank_id !== (int) $lockedWithdrawal->waste_bank_id) {
                    throw new \LogicException('The existing withdrawal debit bank does not match the withdrawal bank.');
                }
                throw new \LogicException('This withdrawal already has a debit.');
            }

            $amount = $this->normalizeAmount($lockedWithdrawal->amount);
            if ((int) $account->balance < $amount) {
                throw new \LogicException('The bank account balance is insufficient for this withdrawal.');
            }

            (new LedgerEntry)->forceFill([
                'user_id' => $user->id,
                'waste_bank_id' => $lockedWithdrawal->waste_bank_id,
                'type' => 'withdrawal_debit',
                'direction' => 'debit',
                'amount' => $amount,
                'reference_type' => Withdrawal::class,
                'reference_id' => $lockedWithdrawal->id,
                'description' => "Withdrawal approval #{$lockedWithdrawal->id}",
                'created_by' => $actorId,
            ])->save();

            $account->decrement('balance', $amount);
            app(WasteBankAccountService::class)->syncAggregateBalance($user);
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

            app(WasteBankContext::class)->assertCanOperate($lockedWithdrawal->waste_bank_id, $actorId);

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

    private function assertEligibleMember(User $user, int $wasteBankId): void
    {
        if ((int) $user->status !== 1 || ! $user->hasRole('user') || $user->hasAnyRole(['admin', 'super_admin'])) {
            $this->fail('user_id', 'Penarikan hanya dapat dicatat untuk akun anggota User yang aktif.');
        }

        if (! $user->bankMemberships()
            ->where('waste_bank_id', $wasteBankId)
            ->where('status', 'active')
            ->exists()) {
            $this->fail('user_id', 'Anggota tidak aktif atau tidak terdaftar di bank sampah ini.');
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

        if (! $actor || (int) $actor->status !== 1 || ! $actor->isBankAdmin()) {
            throw new AuthorizationException('An active admin actor is required for withdrawal financial actions.');
        }

        return $actor->id;
    }
}
