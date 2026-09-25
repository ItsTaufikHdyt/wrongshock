<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteDeposit;
use App\Models\WasteItem;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepositService
{
    /**
     * Post a deposit using only server-resolved prices and master data.
     *
     * @param  array<int, array{waste_item_id:mixed, quantity:mixed}>  $items
     */
    public function post(int $userId, string $depositDate, array $items, ?int $actorId = null, WasteBank|int|null $wasteBank = null): WasteDeposit
    {
        $actorId = $this->authorizeAdmin($actorId);
        $wasteBank = $wasteBank === null
            ? app(WasteBankContext::class)->current($actorId)
            : app(WasteBankContext::class)->assertCanOperate($wasteBank, $actorId);
        $this->validateDate($depositDate);

        if ($items === []) {
            $this->fail('items', 'At least one deposit item is required.');
        }

        return DB::transaction(function () use ($userId, $depositDate, $items, $actorId, $wasteBank): WasteDeposit {
            $user = User::query()->lockForUpdate()->find($userId);

            if (! $user) {
                throw (new ModelNotFoundException)->setModel(User::class, [$userId]);
            }

            if ($actorId !== null && ! User::query()->find($actorId)) {
                throw (new ModelNotFoundException)->setModel(User::class, [$actorId]);
            }

            $this->assertEligibleMember($user, $wasteBank->id);
            $account = app(WasteBankAccountService::class)->lockOrCreate($user, $wasteBank);

            $itemIds = [];
            foreach ($items as $index => $item) {
                if (! is_array($item) || ! array_key_exists('waste_item_id', $item)) {
                    $this->fail("items.{$index}.waste_item_id", 'A waste item is required.');
                }

                $itemId = filter_var($item['waste_item_id'], FILTER_VALIDATE_INT);
                if ($itemId === false || $itemId < 1) {
                    $this->fail("items.{$index}.waste_item_id", 'The waste item is invalid.');
                }

                $itemIds[] = $itemId;
            }

            $masters = WasteItem::query()
                ->whereIn('id', array_unique($itemIds))
                ->get()
                ->keyBy('id');

            $preparedItems = [];
            $total = 0;

            foreach ($items as $index => $item) {
                $itemId = (int) $item['waste_item_id'];
                $master = $masters->get($itemId);

                if (! $master) {
                    throw (new ModelNotFoundException)->setModel(WasteItem::class, [$itemId]);
                }

                if ($master->unit !== 'Kilogram (Kg)') {
                    $this->fail("items.{$index}.quantity", 'Only Kilogram (Kg) waste items are supported.');
                }

                if (! is_int($master->price) && ! ctype_digit((string) $master->price)) {
                    $this->fail("items.{$index}.waste_item_id", 'The waste item price is invalid.');
                }

                $price = (int) $master->price;
                if ($price < 0) {
                    $this->fail("items.{$index}.waste_item_id", 'The waste item price cannot be negative.');
                }

                [$quantity, $quantityScaled] = $this->normalizeQuantity($item['quantity'] ?? null, $index);
                $subtotal = $this->calculateSubtotal($price, $quantityScaled, $index);

                $preparedItems[] = [
                    'waste_item_id' => $itemId,
                    'waste_name_snapshot' => $master->category,
                    'category_snapshot' => $master->output,
                    'unit_snapshot' => $master->unit,
                    'unit_price_snapshot' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ];
                $total += $subtotal;
            }

            $now = now();
            $deposit = new WasteDeposit;
            $deposit->forceFill([
                'user_id' => $user->id,
                'waste_bank_id' => $wasteBank->id,
                'deposit_date' => $depositDate,
                'total_amount' => $total,
                'status' => 'posted',
                'posted_at' => $now,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ])->save();

            foreach ($preparedItems as $preparedItem) {
                $item = $deposit->items()->make();
                $item->forceFill($preparedItem)->save();
            }

            (new LedgerEntry)->forceFill([
                'user_id' => $user->id,
                'waste_bank_id' => $wasteBank->id,
                'type' => 'deposit_credit',
                'direction' => 'credit',
                'amount' => $total,
                'reference_type' => WasteDeposit::class,
                'reference_id' => $deposit->id,
                'description' => "Waste deposit #{$deposit->id}",
                'created_by' => $actorId,
            ])->save();

            $account->increment('balance', $total);
            app(WasteBankAccountService::class)->syncAggregateBalance($user);

            return $deposit->load('items');
        });
    }

    public function cancel(WasteDeposit|int $deposit, string $reason, ?int $actorId = null): WasteDeposit
    {
        $actorId = $this->authorizeAdmin($actorId);
        $reason = trim($reason);
        if ($reason === '') {
            $this->fail('cancellation_reason', 'A cancellation reason is required.');
        }

        return DB::transaction(function () use ($deposit, $reason, $actorId): WasteDeposit {
            $depositId = $deposit instanceof WasteDeposit ? $deposit->getKey() : $deposit;
            $lockedDeposit = WasteDeposit::query()->lockForUpdate()->find($depositId);

            if (! $lockedDeposit) {
                throw (new ModelNotFoundException)->setModel(WasteDeposit::class, [$depositId]);
            }

            if ($lockedDeposit->status !== 'posted') {
                throw new \LogicException('Only posted deposits can be cancelled.');
            }

            app(WasteBankContext::class)->assertCanOperate($lockedDeposit->waste_bank_id, $actorId);

            $user = User::query()->lockForUpdate()->find($lockedDeposit->user_id);
            if (! $user) {
                throw (new ModelNotFoundException)->setModel(User::class, [$lockedDeposit->user_id]);
            }

            if ($actorId !== null && ! User::query()->find($actorId)) {
                throw (new ModelNotFoundException)->setModel(User::class, [$actorId]);
            }

            $accountService = app(WasteBankAccountService::class);
            $account = $accountService->lockExisting($user, $lockedDeposit->waste_bank_id);

            $credit = LedgerEntry::query()
                ->where('user_id', $user->id)
                ->where('reference_type', WasteDeposit::class)
                ->where('reference_id', $lockedDeposit->id)
                ->where('type', 'deposit_credit')
                ->lockForUpdate()
                ->first();

            if (
                ! $credit
                || $credit->direction !== 'credit'
                || (int) $credit->waste_bank_id !== (int) $lockedDeposit->waste_bank_id
                || (int) $credit->amount !== (int) $lockedDeposit->total_amount
            ) {
                throw new \LogicException('The original deposit credit is missing or inconsistent.');
            }

            $reversal = LedgerEntry::query()
                ->where('user_id', $user->id)
                ->where('reference_type', WasteDeposit::class)
                ->where('reference_id', $lockedDeposit->id)
                ->where('type', 'deposit_reversal')
                ->lockForUpdate()
                ->first();

            if ($reversal) {
                if ((int) $reversal->waste_bank_id !== (int) $lockedDeposit->waste_bank_id) {
                    throw new \LogicException('The existing reversal bank does not match the original deposit bank.');
                }
                throw new \LogicException('This deposit has already been cancelled.');
            }

            $amount = (int) $lockedDeposit->total_amount;
            if ($amount < 0 || (int) $account->balance < $amount) {
                throw new \LogicException('The bank account balance is insufficient for this reversal.');
            }

            (new LedgerEntry)->forceFill([
                'user_id' => $user->id,
                'waste_bank_id' => $lockedDeposit->waste_bank_id,
                'type' => 'deposit_reversal',
                'direction' => 'debit',
                'amount' => $amount,
                'reference_type' => WasteDeposit::class,
                'reference_id' => $lockedDeposit->id,
                'description' => "Cancellation reversal for waste deposit #{$lockedDeposit->id}",
                'created_by' => $actorId,
            ])->save();

            $account->decrement('balance', $amount);
            $accountService->syncAggregateBalance($user);

            $lockedDeposit->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'updated_by' => $actorId,
            ])->save();

            return $lockedDeposit->load('items');
        });
    }

    private function validateDate(string $date): void
    {
        $parsed = Carbon::createFromFormat('Y-m-d', $date);

        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            $this->fail('deposit_date', 'The deposit date is invalid.');
        }
    }

    /** @return array{0:string, 1:int} */
    private function normalizeQuantity(mixed $quantity, int $index): array
    {
        if (! is_string($quantity) && ! is_int($quantity)) {
            $this->fail("items.{$index}.quantity", 'Quantity must be a decimal string.');
        }

        $quantity = trim((string) $quantity);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,3})?$/', $quantity)) {
            $this->fail("items.{$index}.quantity", 'Quantity must have at most 3 decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
        if (strlen($whole) > 9) {
            $this->fail("items.{$index}.quantity", 'Quantity is too large.');
        }

        $scaled = ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
        if ($scaled < 1) {
            $this->fail("items.{$index}.quantity", 'Quantity must be greater than zero.');
        }

        return [
            $whole.'.'.str_pad($fraction, 3, '0'),
            $scaled,
        ];
    }

    private function calculateSubtotal(int $price, int $quantityScaled, int $index): int
    {
        if ($price > intdiv(PHP_INT_MAX - 500, max(1, $quantityScaled))) {
            $this->fail("items.{$index}.quantity", 'The calculated subtotal is too large.');
        }

        return intdiv(($price * $quantityScaled) + 500, 1000);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function assertEligibleMember(User $user, int $wasteBankId): void
    {
        if ((int) $user->status !== 1 || ! $user->hasRole('user') || $user->hasAnyRole(['admin', 'super_admin'])) {
            $this->fail('user_id', 'Setoran hanya dapat dicatat untuk akun anggota User yang aktif.');
        }

        if (! $user->bankMemberships()
            ->where('waste_bank_id', $wasteBankId)
            ->where('status', 'active')
            ->exists()) {
            $this->fail('user_id', 'Anggota tidak aktif atau tidak terdaftar di bank sampah ini.');
        }
    }

    private function authorizeAdmin(?int $actorId): int
    {
        $actorId ??= Auth::id();
        $actor = $actorId !== null ? User::query()->find($actorId) : null;

        if (! $actor || (int) $actor->status !== 1 || ! $actor->isBankAdmin()) {
            throw new AuthorizationException('An active admin actor is required for deposit financial actions.');
        }

        return $actor->id;
    }
}
