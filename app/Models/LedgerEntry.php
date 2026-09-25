<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $table = 'account_ledger_entries';

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            if ($entry->waste_bank_id === null) {
                throw new \LogicException('Ledger entries require explicit Waste Bank ownership.');
            }
        });

        static::updating(function (self $entry): void {
            $immutable = [
                'user_id',
                'waste_bank_id',
                'type',
                'direction',
                'amount',
                'reference_type',
                'reference_id',
            ];

            if ($entry->isDirty($immutable)) {
                throw new \LogicException('Ledger financial ownership and values are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new \LogicException('Ledger history cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function wasteBank()
    {
        return $this->belongsTo(WasteBank::class);
    }
}
