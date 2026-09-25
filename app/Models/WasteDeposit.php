<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class WasteDeposit extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (WasteDeposit $deposit): void {
            if (in_array($deposit->status, ['posted', 'cancelled'], true)) {
                throw new LogicException('Posted or cancelled deposits cannot be deleted.');
            }
        });

        static::updating(function (WasteDeposit $deposit): void {
            if ($deposit->isDirty('waste_bank_id') && in_array($deposit->status, ['posted', 'cancelled'], true)) {
                throw new LogicException('Posted deposit bank ownership cannot be changed.');
            }
        });
    }

    protected $table = 'waste_deposits';

    protected $fillable = [
        'user_id',
        'waste_bank_id',
        'deposit_date',
    ];

    protected function casts(): array
    {
        return [
            'deposit_date' => 'date',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'total_amount' => 'integer',
        ];
    }

    public function items()
    {
        return $this->hasMany(\App\Models\WasteDepositItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wasteBank()
    {
        return $this->belongsTo(WasteBank::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
